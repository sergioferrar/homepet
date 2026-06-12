<?php

namespace App\Controller\Clinica;

use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Servico;
use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Repository\FinanceiroRepository;
use App\Controller\DefaultController;
use Doctrine\ORM\EntityManagerInterface;
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
        $conn   = $this->entityManager->getConnection();

        try {
            $petIdFromRequest = $request->get('pet_id');
            if (!$petIdFromRequest) {
                return $this->json(['status' => 'error', 'mensagem' => 'ID do pet não informado.'], 400);
            }

            $pet = $this->getRepositorio(\App\Entity\Pet::class)->findPetById($baseId, $petIdFromRequest);
            if (!$pet) {
                return $this->json(['status' => 'error', 'mensagem' => 'Pet não encontrado.'], 404);
            }

            $metodoPagamento = $request->get('metodo_pagamento', 'pendente');
            $origem          = $request->get('origem', 'clinica');
            $status          = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

            // ── Transação atômica — abre ANTES de qualquer escrita ────────────
            $conn->beginTransaction();

            // 1️⃣ Insere a venda via DBAL para obter o ID sem flush intermediário
            //    (flush intermediário dentro de transação causa autocommit implícito no MySQL)
            $conn->executeStatement(
                "INSERT INTO venda
                    (estabelecimento_id, cliente, pet_id, parcelas, origem,
                     metodo_pagamento, status, data, observacao, total)
                 VALUES
                    (:estab, :cliente, :pet, :parcelas, :origem,
                     :metodo, :status, NOW(), :obs, 0)",
                [
                    'estab'    => $baseId,
                    'cliente'  => $pet['dono_nome'] ?? 'Consumidor Final',
                    'pet'      => $petIdFromRequest,
                    'parcelas' => $request->get('parcelas', 1),
                    'origem'   => $origem,
                    'metodo'   => $metodoPagamento,
                    'status'   => $status,
                    'obs'      => $request->get('observacao'),
                ]
            );
            $vendaId = (int)$conn->lastInsertId();

            // 2️⃣ Processa itens via DBAL
            $descricoes  = (array)$request->get('descricao', []);
            $descontos   = (array)$request->get('desconto', []);
            $quantidades = (array)$request->get('quantidade_diarias', []);
            $valorTotal  = 0.0;

            foreach ($descricoes as $i => $itemId) {
                $tipo   = 'servico';
                $realId = $itemId;

                if (str_starts_with((string)$itemId, 'S-')) {
                    $tipo   = 'servico';
                    $realId = substr($itemId, 2);
                } elseif (str_starts_with((string)$itemId, 'P-')) {
                    $tipo   = 'produto';
                    $realId = substr($itemId, 2);
                }

                $nome          = 'Item';
                $valorUnitario = 0.0;

                if ($tipo === 'servico') {
                    $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($baseId, $realId);
                    if (!$servico) continue;
                    $nome          = $servico['nome'] ?? 'Serviço';
                    $valorUnitario = (float)$servico['valor'];
                } else {
                    $produto = $this->getRepositorio(\App\Entity\Produto::class)->find($realId);
                    if (!$produto) continue;
                    $nome          = $produto->getNome();
                    $valorUnitario = (float)$produto->getPrecoVenda();
                }

                $quantidade = (int)($quantidades[$i] ?? 1);
                $desconto   = (float)($descontos[$i] ?? 0);
                $valorItem  = ($valorUnitario * $quantidade) - $desconto;

                $conn->executeStatement(
                    "INSERT INTO venda_item
                        (venda_id, tipo, produto_id, produto, quantidade, preco_unitario, subtotal)
                     VALUES
                        (:venda, :tipo, :pid, :pnome, :qtd, :unit, :sub)",
                    [
                        'venda' => $vendaId,
                        'tipo'  => $tipo,
                        'pid'   => (int)$realId,
                        'pnome' => $nome,
                        'qtd'   => $quantidade,
                        'unit'  => $valorUnitario,
                        'sub'   => $valorItem,
                    ]
                );

                $valorTotal += $valorItem;
            }

            // 3️⃣ Atualiza total da venda
            $conn->executeStatement(
                "UPDATE venda SET total = :total WHERE id = :id",
                ['total' => $valorTotal, 'id' => $vendaId]
            );

            // 4️⃣ Registra no financeiro — dentro da mesma transação DBAL
            $descricaoFinanceiro = sprintf(
                'Venda Clínica - Pet: %s (#%d)',
                $pet['nome'] ?? 'Pet',
                $petIdFromRequest
            );

            if ($metodoPagamento === 'pendente') {
                // FinanceiroPendente via DBAL (entidade não possui campo "tipo")
                $conn->executeStatement(
                    "INSERT INTO financeiropendente
                        (descricao, valor, data, metodo_pagamento, origem, status,
                         estabelecimento_id, inativar)
                     VALUES
                        (:desc, :valor, NOW(), 'pendente', 'clinica', 'Pendente',
                         :estab, 0)",
                    [
                        'desc'  => $descricaoFinanceiro,
                        'valor' => $valorTotal,
                        'estab' => $baseId,
                    ]
                );

                // ── Commit ANTES do ORM para evitar transação aninhada ────────
                $conn->commit();
            } else {
                // ── Commit da transação DBAL ANTES do flush do ORM ────────────
                // O flush() do Doctrine abre sua própria transação; chamar dentro
                // de uma transação DBAL aberta causa autocommit implícito no MySQL
                // e pode gerar erro de transação aninhada.
                $conn->commit();

                // Financeiro (pago) via ORM — fora da transação DBAL
                $financeiro = new Financeiro();
                $financeiro->setDescricao($descricaoFinanceiro);
                $financeiro->setValor($valorTotal);
                $financeiro->setData(new \DateTime());
                $financeiro->setMetodoPagamento($metodoPagamento);
                $financeiro->setOrigem('clinica');
                $financeiro->setStatus('Pago');
                $financeiro->setTipo('ENTRADA');
                $financeiro->setEstabelecimentoId($baseId);
                $this->entityManager->persist($financeiro);
                $this->entityManager->flush();
            }

            $mensagem = ($metodoPagamento === 'pendente')
                ? 'Venda registrada como pendente no financeiro!'
                : 'Venda adicionada ao carrinho! Finalize no PDV.';

            return $this->json([
                'status'   => 'success',
                'mensagem' => $mensagem,
                'venda_id' => $vendaId,
            ]);

        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->logger->error('Erro ao concluir venda', [
                'erro'  => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->json([
                'status'   => 'error',
                'mensagem' => 'Erro ao registrar venda: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @Route("/pet/{petId}/venda/{id}/inativar", name="clinica_inativar_venda", methods={"POST"})
     */
    public function inativarVenda(Request $request, int $petId, int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        try {
            $vendaRepo = $this->getRepositorio(Venda::class);
            $venda = $vendaRepo->findOneBy(['id' => $id, 'estabelecimentoId' => $baseId]);

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
    public function editarVenda(Request $request, int $petId, int $id, FinanceiroRepository $financeiroRepository): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();

            $financeiro = $financeiroRepository->findFinanceiro($baseId, $id);
            if (!$financeiro) {
                return new JsonResponse(['status' => 'error', 'mensagem' => 'Venda não encontrada.'], 404);
            }

            $financeiro->setDescricao($request->get('descricao'));
            $financeiro->setValor((float)$request->get('valor'));

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
