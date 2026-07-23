<?php
namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Pet;
use App\Entity\Produto;
use App\Entity\Servico;
use App\Entity\Venda;
use App\Entity\VendaItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class VendaController extends DefaultController
{
    /**
     * @Route("/pet/{petId}/venda/concluir", name="clinica_concluir_venda", methods={"POST"})
     */
    public function concluirVenda(Request $request, int $petId): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Conexão compartilhada (mesmo objeto usado pelos repositories após switchDB)
        // — usada apenas para o controle transacional; toda escrita roda nos repositories.
        $conn = $this->entityManager->getConnection();

        $vendaRepo              = $this->getRepositorio(Venda::class);
        $vendaItemRepo          = $this->getRepositorio(VendaItem::class);
        $produtoRepo            = $this->getRepositorio(Produto::class);
        $servicoRepo            = $this->getRepositorio(Servico::class);
        $financeiroRepo         = $this->getRepositorio(Financeiro::class);
        $financeiroPendenteRepo = $this->getRepositorio(FinanceiroPendente::class);

        try {
            $petIdFromRequest = $request->get('pet_id');
            if (!$petIdFromRequest) {
                return $this->json(['status' => 'error', 'mensagem' => 'ID do pet não informado.'], 400);
            }

            $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petIdFromRequest);
            if (!$pet) {
                return $this->json(['status' => 'error', 'mensagem' => 'Pet não encontrado.'], 404);
            }

            $metodoPagamento = $request->get('metodo_pagamento', 'pendente');
            $origem = $request->get('origem', 'clinica');
            $status = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

            $consultaIdRaw = $request->get('consulta_id');
            $consultaId = ($consultaIdRaw !== null && $consultaIdRaw !== '')
                ? (int) $consultaIdRaw
                : null;

            // Valida que a consulta pertence a este pet/estabelecimento antes de vincular
            if ($consultaId !== null) {
                $consultaValida = $conn->fetchOne(
                    'SELECT id FROM consulta
                     WHERE id = :id AND pet_id = :pet AND estabelecimento_id = :estab
                     LIMIT 1',
                    ['id' => $consultaId, 'pet' => (int) $petIdFromRequest, 'estab' => $baseId]
                );

                if (!$consultaValida) {
                    return $this->json([
                        'status'   => 'error',
                        'mensagem' => 'Atendimento informado não pertence a este pet.',
                    ], 422);
                }
            }

            // ── Normaliza os itens ANTES de abrir a transação ─────────────────
            // Cada linha do formulário vira uma entrada própria com sua
            // quantidade e seu desconto. É isso que corrige o bug de a
            // quantidade de diárias "vazar" para os outros itens da venda.
            $linhas = $this->normalizarItens($request);

            if ($linhas === []) {
                return $this->json([
                    'status'   => 'error',
                    'mensagem' => 'Nenhum item informado para a venda.',
                ], 422);
            }

            // ── Transação atômica — abre ANTES de qualquer escrita ────────────
            $conn->beginTransaction();

            // 1️⃣ Insere a venda via repository (SQL nativo) para obter o ID
            $vendaId = $vendaRepo->inserirVenda($baseId, [
                'estabelecimento_id' => $baseId,
                'cliente'            => $pet['dono_nome'] ?? 'Consumidor Final',
                'pet_id'             => $petIdFromRequest,
                'consulta_id'        => $consultaId,
                'parcelas'           => $request->get('parcelas', 1),
                'origem'             => $origem,
                'metodo_pagamento'   => $metodoPagamento,
                'status'             => $status,
                'observacao'         => $request->get('observacao'),
            ]);

            // 2️⃣ Processa itens via DBAL
            $valorTotal = 0.0;
            $itensGravados = 0;

            foreach ($linhas as $linha) {
                $tipo   = $linha['tipo'];
                $realId = $linha['id'];

                $nome = 'Item';
                $valorUnitario = 0.0;
                $produto = null;

                if ($tipo === 'servico') {
                    $servico = $servicoRepo->listaServicoPorId($baseId, $realId);
                    if (!$servico) {
                        continue;
                    }

                    $nome = $servico['nome'] ?? 'Serviço';
                    $valorUnitario = (float) $servico['valor'];
                } else {
                    $produto = $conn->fetchAssociative(
                        'SELECT id, nome, preco_venda, estoque_atual
                         FROM produto WHERE id = :id AND estabelecimento_id = :estab',
                        ['id' => (int) $realId, 'estab' => $baseId]
                    );
                    if (!$produto) {
                        continue;
                    }

                    $nome = $produto['nome'];
                    $valorUnitario = (float) $produto['preco_venda'];
                }

                // Quantidade e desconto SÃO DESTA LINHA — nunca herdados de outra
                $quantidade = max(1, (int) $linha['quantidade']);
                $bruto      = $valorUnitario * $quantidade;
                // desconto nunca pode deixar o item negativo
                $desconto   = min(max(0.0, (float) $linha['desconto']), $bruto);
                $valorItem  = round($bruto - $desconto, 2);

                $vendaItemRepo->inserirItem($baseId, [
                    'venda_id'       => $vendaId,
                    'tipo'           => $tipo,
                    'produto_id'     => (int) $realId,
                    'produto'        => $nome,
                    'quantidade'     => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'subtotal'       => $valorItem,
                ]);

                $valorTotal += $valorItem;
                $itensGravados++;

                // Baixa de estoque + movimentação (apenas para produtos físicos)
                if ($tipo === 'produto') {
                    $estoqueAnterior = (int) ($produto['estoque_atual'] ?? 0);
                    $novoEstoque = max(0, $estoqueAnterior - $quantidade);

                    $conn->executeStatement(
                        'UPDATE produto SET estoque_atual = :novo
                         WHERE id = :id AND estabelecimento_id = :estab',
                        ['novo' => $novoEstoque, 'id' => (int) $realId, 'estab' => $baseId]
                    );

                    $conn->executeStatement(
                        'INSERT INTO estoque_movimento
                            (produto_id, estabelecimento_id, quantidade, tipo, origem, data, observacao)
                         VALUES (:pid, :estab, :qtd, :tipo, :origem, :data, :obs)',
                        [
                            'pid'    => (int) $realId,
                            'estab'  => $baseId,
                            'qtd'    => $quantidade,
                            'tipo'   => 'SAIDA',
                            'origem' => 'Venda Clínica #' . $vendaId,
                            'data'   => (new \DateTime())->format('Y-m-d H:i:s'),
                            'obs'    => sprintf(
                                'Venda para: %s | Estoque anterior: %d | Novo estoque: %d',
                                $pet['dono_nome'] ?? 'Consumidor Final',
                                $estoqueAnterior,
                                $novoEstoque
                            ),
                        ]
                    );
                }
            }

            // Se nenhum item pôde ser resolvido, não deixa venda "fantasma" de R$ 0
            if ($itensGravados === 0) {
                $conn->rollBack();

                return $this->json([
                    'status'   => 'error',
                    'mensagem' => 'Nenhum dos itens informados foi encontrado no cadastro.',
                ], 422);
            }

            $valorTotal = round($valorTotal, 2);

            // 3️⃣ Atualiza total da venda
            $vendaRepo->atualizarTotal($baseId, $vendaId, $valorTotal);

            // 4️⃣ Registra no financeiro — dentro da mesma transação DBAL
            $descricaoFinanceiro = sprintf(
                'Venda Clínica - Pet: %s (#%d)',
                $pet['nome'] ?? 'Pet',
                $petIdFromRequest
            );

            if ($metodoPagamento === 'pendente') {
                // FinanceiroPendente via repository (SQL nativo)
                $financeiroPendenteRepo->inserirVendaClinica($baseId, $descricaoFinanceiro, $valorTotal);
            } else {
                // Financeiro (pago) via repository (SQL nativo) — substitui persist()/flush()
                $financeiroRepo->inserirEntrada($baseId, [
                    'descricao'        => $descricaoFinanceiro,
                    'valor'            => $valorTotal,
                    'metodo_pagamento' => $metodoPagamento,
                    'origem'           => 'clinica',
                    'status'           => 'Pago',
                    'tipo'             => 'ENTRADA',
                ]);
            }

            // ── Commit único da transação (todas as escritas usam a mesma conexão)
            $conn->commit();

            $mensagem = ($metodoPagamento === 'pendente')
            ? 'Venda registrada como pendente no financeiro!'
            : 'Venda adicionada ao carrinho! Finalize no PDV.';

            return $this->json([
                'status'      => 'success',
                'mensagem'    => $mensagem,
                'venda_id'    => $vendaId,
                'consulta_id' => $consultaId,
                'total'       => $valorTotal,
                'itens'       => $itensGravados,
            ]);

        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            // Contexto suficiente para achar a venda problemática no log
            $this->logger->error('Erro ao concluir venda', [
                'erro'        => $e->getMessage(),
                'excecao'     => get_class($e),
                'arquivo'     => $e->getFile() . ':' . $e->getLine(),
                'base_id'     => $baseId,
                'pet_id'      => $request->get('pet_id'),
                'consulta_id' => $request->get('consulta_id'),
                'payload'     => $request->request->all(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return $this->json([
                'status' => 'error',
                'mensagem' => 'Erro ao registrar venda: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normaliza os itens enviados pelo formulário em uma lista fechada de linhas:
     *
     *   [['tipo' => 'servico', 'id' => 12, 'quantidade' => 3, 'desconto' => 0.0], ...]
     *
     * ── Por que este método existe ────────────────────────────────────────────
     * O formulário antigo enviava três arrays paralelos: descricao[], desconto[]
     * e quantidade_diarias[]. O backend casava os três pelo índice numérico
     * ($quantidades[$i]). Só que o input de quantidade só era criado para
     * serviços de internação — então o array de quantidades vinha COMPACTADO.
     *
     *   Linha 0: Produto      R$ 45  → sem input de quantidade
     *   Linha 1: Internação   R$ 80  → quantidade_diarias[0] = 3
     *
     * Resultado: o backend lia $quantidades[0] = 3 e aplicava no PRODUTO
     * (45 × 3 = 135), enquanto a internação caía no default 1 (80 × 1).
     * Exatamente o sintoma relatado.
     *
     * Agora o formato preferido é indexado — `itens[0][ref]`, `itens[0][quantidade]`,
     * `itens[0][desconto]` — em que a quantidade está fisicamente amarrada à
     * linha, não à posição num array separado. O formato antigo continua
     * aceito, mas quantidade_diarias[] só é usado quando a contagem bate com
     * descricao[]; caso contrário assume 1 e registra um alerta no log, em vez
     * de multiplicar o item errado silenciosamente.
     *
     * @return array<int, array{tipo: string, id: int, quantidade: int, desconto: float}>
     */
    private function normalizarItens(Request $request): array
    {
        $linhas = [];

        // ── Formato novo: itens[i][ref|quantidade|desconto] ───────────────────
        $itens = $request->get('itens');

        if (is_array($itens) && $itens !== []) {
            foreach ($itens as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $ref = trim((string) ($item['ref'] ?? $item['descricao'] ?? ''));
                if ($ref === '') {
                    continue;
                }

                $resolvido = $this->resolverReferencia($ref);
                if ($resolvido === null) {
                    continue;
                }

                $linhas[] = [
                    'tipo'       => $resolvido['tipo'],
                    'id'         => $resolvido['id'],
                    'quantidade' => max(1, (int) ($item['quantidade'] ?? 1)),
                    'desconto'   => max(0.0, (float) str_replace(',', '.', (string) ($item['desconto'] ?? 0))),
                ];
            }

            return $linhas;
        }

        // ── Formato legado: descricao[] + desconto[] (+ quantidade[]) ─────────
        $descricoes = array_values((array) $request->get('descricao', []));
        $descontos  = array_values((array) $request->get('desconto', []));
        $quantidades = array_values((array) $request->get('quantidade', []));
        $diarias     = array_values((array) $request->get('quantidade_diarias', []));

        // quantidade_diarias[] só é confiável se tiver uma entrada por linha
        $usarDiarias = $quantidades === []
            && $diarias !== []
            && count($diarias) === count($descricoes);

        if ($quantidades === [] && $diarias !== [] && !$usarDiarias) {
            $this->logger->warning(
                'Venda clínica: quantidade_diarias[] desalinhado com descricao[] — quantidades ignoradas.',
                ['descricoes' => count($descricoes), 'diarias' => count($diarias)]
            );
        }

        foreach ($descricoes as $i => $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }

            $resolvido = $this->resolverReferencia($ref);
            if ($resolvido === null) {
                continue;
            }

            if ($usarDiarias) {
                $quantidade = (int) ($diarias[$i] ?? 1);
            } else {
                $quantidade = (int) ($quantidades[$i] ?? 1);
            }

            $linhas[] = [
                'tipo'       => $resolvido['tipo'],
                'id'         => $resolvido['id'],
                'quantidade' => max(1, $quantidade),
                'desconto'   => max(0.0, (float) str_replace(',', '.', (string) ($descontos[$i] ?? 0))),
            ];
        }

        return $linhas;
    }

    /**
     * Interpreta a referência de um item: "S-12" (serviço), "P-7" (produto)
     * ou apenas "12" (serviço, formato legado do select da ficha).
     *
     * @return array{tipo: string, id: int}|null
     */
    private function resolverReferencia(string $ref): ?array
    {
        if (str_starts_with($ref, 'S-')) {
            $tipo = 'servico';
            $id   = substr($ref, 2);
        } elseif (str_starts_with($ref, 'P-')) {
            $tipo = 'produto';
            $id   = substr($ref, 2);
        } else {
            // Sem prefixo = serviço (comportamento histórico do select da ficha)
            $tipo = 'servico';
            $id   = $ref;
        }

        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            return null;
        }

        return ['tipo' => $tipo, 'id' => (int) $id];
    }

    /**
     * @Route("/pet/{petId}/venda/{id}/inativar", name="clinica_inativar_venda", methods={"POST"})
     */
    public function inativarVenda(Request $request, int $petId, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        try {
            $vendaRepo = $this->getRepositorio(Venda::class);
            $venda = $vendaRepo->buscarPorId($baseId, $id);

            if (!$venda) {
                $this->addFlash('danger', 'Venda não encontrada.');
                return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
            }

            $vendaRepo->inativar($baseId, $id);
            $this->addFlash('success', 'Venda inativada com sucesso.');
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao inativar venda: ' . $e->getMessage());
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }
    }

    /**
     * @Route("/clinica/pet/{petId}/venda/{id}/editar", name="clinica_editar_venda", methods={"POST"})
     */
    public function editarVenda(Request $request, int $petId, int $id): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();
            $financeiroRepository = $this->getRepositorio(Financeiro::class);

            $financeiro = $financeiroRepository->findFinanceiro($baseId, $id);
            if (!$financeiro) {
                return new JsonResponse(['status' => 'error', 'mensagem' => 'Venda não encontrada.'], 404);
            }

            $financeiro->setDescricao($request->get('descricao'));
            $financeiro->setValor((float) $request->get('valor'));

            $data = $request->get('data');
            if ($data) {
                $financeiro->setData(new \DateTime($data));
            }

            $financeiro->setMetodoPagamento($request->get('metodo_pagamento') ?: 'pendente');
            $financeiro->setObservacoes($request->get('observacao'));
            $financeiroRepository->update($baseId, $financeiro);

            return new JsonResponse(['status' => 'success', 'mensagem' => 'Venda atualizada com sucesso.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['status' => 'error', 'mensagem' => 'Erro ao editar venda: ' . $e->getMessage()], 500);
        }
    }
}
