<?php

namespace App\Controller;

use App\DTO\FinalizarCarrinhoDTO;
use App\DTO\RegistrarSaidaDTO;
use App\DTO\RegistrarVendaDTO;
use App\Entity\Cliente;
use App\Entity\Pet;
use App\Entity\Produto;
use App\Entity\Servico;
use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Repository\ClienteRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Repository\VendaRepository;
use App\Service\CaixaService;
use App\Service\PdvService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller do PDV - Apenas coordena requests, toda lógica está nos Services
 * 
 * @Route("dashboard/clinica/pdv")
 */
class PdvController extends DefaultController
{

    /**
     * Tela principal do PDV
     * 
     * @Route("", name="clinica_pdv_index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        // Busca produtos e serviços
        $produtos = $em->getRepository(Produto::class)
            ->findBy(['estabelecimentoId' => $estabelecimentoId]);
        
        $servicos = $em->getRepository(Servico::class)
            ->findBy(['estabelecimentoId' => $estabelecimentoId]);

        // Normaliza itens para o frontend
        $itensNormalizados = $this->normalizarItens($produtos, $servicos);

        // Busca e normaliza clientes
        $clientesEntities = $em->getRepository(Cliente::class)
            ->findBy(['estabelecimentoId' => $estabelecimentoId]);
        
        $clientesNormalizados = array_map(function($cliente) {
            return [
                'id' => $cliente->getId(),
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone()
            ];
        }, $clientesEntities);

        // Busca vendas em carrinho
        $vendasCarrinho = $em->getRepository(Venda::class)
            ->findCarrinho($estabelecimentoId);

        // Lê (e consome) o payload da venda pré-carregada gravado por carregarVendaNopdv()
        $session = $request->getSession();
        $vendaPreCarregada = $session->get('pdv_venda_pre_carregada');
        if ($vendaPreCarregada !== null) {
            $session->remove('pdv_venda_pre_carregada');
        }

        return $this->render("clinica/pdv.html.twig", [
            'produtos'            => $itensNormalizados,
            'clientes'            => $clientesNormalizados,
            'vendas_carrinho'     => $vendasCarrinho ?? [],
            'vendaPreCarregada'   => $vendaPreCarregada,   // null quando não há pré-carregamento
        ]);
    }

    /**
     * Carrega uma venda da clínica no PDV (Lançar no Caixa)
     *
     * @Route("/carregar/{id}", name="pdv_carregar", methods={"GET"})
     */
    public function carregarVendaNopdv(
        int $id,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $session = $request->getSession();

        // 1. Busca a venda garantindo que pertence ao estabelecimento
        $venda = $em->getRepository(Venda::class)->findOneBy([
            'id'                => $id,
            'estabelecimentoId' => $estabelecimentoId,
        ]);

        if (!$venda) {
            $this->addFlash('danger', 'Venda não encontrada.');
            return $this->redirectToRoute('clinica_pdv_listar');
        }

        // 2. Valida status — só carrega vendas abertas / em carrinho
        $statusPermitidos = ['Aberta', 'Carrinho', 'carrinho'];
        if (!in_array($venda->getStatus(), $statusPermitidos, true)) {
            $this->addFlash('warning', sprintf(
                'A venda #%d não pode ser lançada no caixa (status: %s).',
                $venda->getId(),
                $venda->getStatus()
            ));
            return $this->redirectToRoute('clinica_pdv_listar');
        }

        // 3. Busca os itens da venda
        $itensEntities = $em->getRepository(VendaItem::class)->findBy(['vendaId' => $venda->getId()]);

        // 4. Monta payload de itens para a sessão
        $itensNormalizados = [];
        foreach ($itensEntities as $item) {
            $tipo      = $item->getTipo(); // já normalizado para lowercase
            $produtoId = $this->resolverProdutoId($item); // trata legado com produtoId null

            $idFrontend = ($tipo === 'produto')
                ? 'prod_' . $produtoId
                : 'serv_' . $produtoId;

            $itensNormalizados[] = [
                'id'             => $idFrontend,
                'nome'           => $this->resolverNomeItem($em, $item),
                'tipo'           => ucfirst($tipo),
                'quantidade'     => $item->getQuantidade(),
                'valor_unitario' => $item->getValorUnitario(),
                'subtotal'       => $item->getSubtotal(),
            ];
        }

        // 5. Resolve nome do cliente e pet
        $nomeCliente = $venda->getCliente() ?? 'Consumidor Final';
        $petId       = $venda->getPetId();
        $nomePet     = null;

        if ($petId) {
            $pet = $em->getRepository(Pet::class)->find($petId);
            if ($pet) {
                $nomePet = $pet->getNome();
            }
        }

        // 6. Grava tudo na sessão — o PDV lê via Twig/JS
        $session->set('pdv_venda_pre_carregada', [
            'venda_id'     => $venda->getId(),
            'cliente_nome' => $nomeCliente,
            'pet_id'       => $petId,
            'pet_nome'     => $nomePet,
            'itens'        => $itensNormalizados,
            'total'        => $venda->getTotal(),
            'observacao'   => $venda->getObservacao(),
        ]);

        $this->addFlash('success', sprintf(
            'Venda #%d carregada no PDV. Revise os itens e finalize.',
            $venda->getId()
        ));

        // 7. Redireciona para a tela principal do PDV
        return $this->redirectToRoute('clinica_pdv_index',[ 'vendaId' => $venda->getId() ]);
    }


    /**
     * Registra nova venda
     * 
     * @Route("/registrar", name="clinica_pdv_registrar", methods={"POST"})
     */
    public function registrar(Request $request): JsonResponse
    {
        $this->switchDB();
        
        try {
            $dados = json_decode($request->getContent(), true);
            $dto = RegistrarVendaDTO::fromArray($dados);

            // Valida DTO
            $errors = $dto->validate();
            if (!empty($errors)) {
                return new JsonResponse([
                    'ok' => false,
                    'msg' => implode(' ', $errors)
                ], 400);
            }

            // Processa venda via service
            $resultado = $this->pdvService->registrarVenda($dto);

            return new JsonResponse($resultado, $resultado['ok'] ? 200 : 400);

        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'msg' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra saída de caixa
     * 
     * @Route("/saida", name="clinica_pdv_saida", methods={"POST"})
     */
    public function registrarSaida(Request $request): JsonResponse
    {
        $this->switchDB();
        
        try {
            $dados = json_decode($request->getContent(), true);
            $dto = RegistrarSaidaDTO::fromArray($dados);

            // Valida DTO
            $errors = $dto->validate();
            if (!empty($errors)) {
                return new JsonResponse([
                    'ok' => false,
                    'msg' => implode(' ', $errors)
                ], 400);
            }

            // Processa saída via service
            $resultado = $this->caixaService->registrarSaida($dto);

            return new JsonResponse($resultado, $resultado['ok'] ? 200 : 400);

        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'msg' => 'Erro ao registrar saída: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tela de controle de caixa
     * 
     * @Route("/caixa", name="clinica_pdv_caixa", methods={"GET"})
     */
    public function caixa(VendaRepository $vendaRepo): Response
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        $hoje = new \DateTime();

        // Busca resumo do caixa
        $resumoCaixa = $this->caixaService->getResumoDoDia();

        // Busca totais de vendas
        $totais = $vendaRepo->totalPorFormaPagamento($estabelecimentoId, $hoje);
        $totalGeral = $vendaRepo->totalGeralDoDia($estabelecimentoId, $hoje);

        return $this->render('clinica/pdv_caixa.html.twig', [
            'data' => $hoje,
            'registros' => $resumoCaixa['registros'],
            'entradas' => $resumoCaixa['entradas'],
            'saidas' => $resumoCaixa['saidas'],
            'saldo' => $resumoCaixa['saldo'],
            'totais' => $totais,
            'totalGeral' => $totalGeral,
        ]);
    }

    /**
     * Listagem de vendas
     * 
     * @Route("/listar", name="clinica_pdv_listar", methods={"GET"})
     */
    public function listar(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $vendas = $em->getRepository(Venda::class)
            ->findBy(
                ['estabelecimentoId' => $estabelecimentoId],
                ['data' => 'DESC']
            );

        return $this->render('clinica/pdv_listar.html.twig', [
            'vendas' => $vendas,
        ]);
    }

    /**
     * Lista clientes para Select2
     * 
     * @Route("/clientes/listar", name="clinica_pdv_clientes_listar", methods={"GET"})
     */
    public function listarClientes(ClienteRepository $clienteRepo): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $clientes = $clienteRepo->findBy(['estabelecimentoId' => $estabelecimentoId]);

        $resultado = array_map(fn($c) => [
            'id' => $c->getId(),
            'nome' => $c->getNome(),
            'email' => $c->getEmail(),
            'telefone' => $c->getTelefone()
        ], $clientes);

        return new JsonResponse($resultado);
    }

    /**
     * Autocomplete de tutores
     * 
     * @Route("/autocomplete/tutor", name="clinica_autocomplete_tutor", methods={"GET"})
     */
    public function autocompleteTutor(Request $request, ClienteRepository $clienteRepo): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        $query = $request->query->get('query');

        if (empty($query) || strlen($query) < 3) {
            return new JsonResponse([]);
        }

        $clientes = $clienteRepo->findByNomeLike($estabelecimentoId, $query);

        $resultado = array_map(fn($c) => [
            'id' => $c['id'],
            'nome' => $c['nome']
        ], $clientes);

        return new JsonResponse($resultado);
    }

    /**
     * Busca pets de um tutor
     * 
     * @Route("/pets-by-tutor/{id}", name="clinica_pets_by_tutor", methods={"GET"})
     */
    public function getPetsByTutor(int $id, ClienteRepository $clienteRepo): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $pets = $clienteRepo->listarPetsDoCliente($estabelecimentoId, $id);

        $resultado = array_map(fn($p) => [
            'id' => $p['id'],
            'nome' => $p['nome']
        ], $pets);

        return new JsonResponse($resultado);
    }

    /**
     * Finaliza venda em carrinho
     * 
     * @Route("/carrinho/finalizar/{id}", name="pdv_finalizar_carrinho", methods={"POST"})
     */
    public function finalizarCarrinho(
        Request $request,
        int $id,
        VendaRepository $vendaRepo,
        FinanceiroPendenteRepository $fpRepo
    ): JsonResponse {
        $this->switchDB(); // <- garante que estamos no banco do tenant

        try {
            // Aceita JSON ou form-data
            $contentType = $request->headers->get('Content-Type', '');
            $dados = str_contains($contentType, 'application/json')
                ? (json_decode($request->getContent(), true) ?? [])
                : $request->request->all();

            $metodo   = $dados['metodo_pagamento'] ?? '';
            $bandeira = $dados['bandeira_cartao']  ?? null;
            $parcelas = !empty($dados['parcelas'])  ? (int)$dados['parcelas'] : null;

            if (empty($metodo)) {
                return new JsonResponse([
                    'status'   => 'error',
                    'mensagem' => 'Método de pagamento não informado.',
                ], 400);
            }

            $estabelecimentoId = $this->getIdBase();

            // Busca a venda via SQL nativo (banco do tenant correto)
            $venda = $vendaRepo->findVendaCarrinho($estabelecimentoId, $id);

            if (!$venda) {
                $this->logger->error('Venda não encontrada', [
                    'venda_id' => $id,
                    'estabelecimento_id' => $estabelecimentoId,
                ]);
                return new JsonResponse([
                    'status'   => 'error',
                    'mensagem' => 'Venda não encontrada ou já finalizada.',
                ], 404);
            }

            // Determina status final e registra no financeiro
            if ($metodo === 'pendente') {
                $statusFinal = 'Pendente';
                $fpRepo->inserirPdv(
                    $estabelecimentoId,
                    $venda['cliente'] ?? 'Consumidor Final',
                    (float)$venda['total'],
                    $venda['pet_id'] ?? null
                );
            } else {
                $statusFinal = 'Paga';
                $vendaRepo->inserirFinanceiro(
                    $estabelecimentoId,
                    $metodo,
                    (float)$venda['total'],
                    $venda['cliente'] ?? 'Consumidor Final',
                    $venda['pet_id'] ?? null,
                    $id
                );
            }

            // Atualiza a venda com SQL nativo — banco correto garantido
            $ok = $vendaRepo->finalizarVenda(
                $estabelecimentoId,
                $id,
                $metodo,
                $statusFinal,
                $bandeira,
                $parcelas
            );

            if (!$ok) {
                return new JsonResponse([
                    'status'   => 'error',
                    'mensagem' => 'Não foi possível atualizar a venda. Tente novamente.',
                ], 500);
            }

            return new JsonResponse([
                'status'   => 'success',
                'mensagem' => 'Venda #' . $id . ' finalizada com sucesso!',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'status'   => 'error',
                'mensagem' => 'Erro ao finalizar venda: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Retorna os dados de uma venda em JSON para pré-carregar o PDV via popup (sem usar sessão).
     *
     * @Route("/dados-venda/{id}", name="pdv_dados_venda", methods={"GET"})
     */
    public function dadosVenda(int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->getIdBase();

        $venda = $em->getRepository(Venda::class)->findOneBy([
            'id'                => $id,
            'estabelecimentoId' => $estabelecimentoId,
        ]);

        if (!$venda) {
            return new JsonResponse(['ok' => false, 'msg' => 'Venda não encontrada.'], 404);
        }

        $nomeCliente = $venda->getCliente() ?? 'Consumidor Final';
        $petId       = $venda->getPetId();
        $nomePet     = null;

        if ($petId) {
            $pet = $em->getRepository(Pet::class)->find($petId);
            if ($pet) {
                $nomePet = $pet->getNome();
            }
        }

        $itensEntities     = $em->getRepository(VendaItem::class)->findBy(['vendaId' => $venda->getId()]);
        $itensNormalizados = [];

        foreach ($itensEntities as $item) {
            $tipo      = $item->getTipo(); // já lowercase pela entity
            $produtoId = $this->resolverProdutoId($item); // trata legado com produtoId null

            $idFrontend = ($tipo === 'produto')
                ? 'prod_' . $produtoId
                : 'serv_' . $produtoId;

            $itensNormalizados[] = [
                'id'             => $idFrontend,
                'nome'           => $this->resolverNomeItem($em, $item),
                'tipo'           => ucfirst($tipo),
                'quantidade'     => $item->getQuantidade(),
                'valor_unitario' => $item->getValorUnitario(),
                'subtotal'       => $item->getSubtotal(),
            ];
        }

        return new JsonResponse([
            'ok'           => true,
            'venda_id'     => $venda->getId(),
            'status'       => $venda->getStatus(),
            'cliente_nome' => $nomeCliente,
            'pet_id'       => $petId,
            'pet_nome'     => $nomePet,
            'itens'        => $itensNormalizados,
            'total'        => $venda->getTotal(),
            'observacao'   => $venda->getObservacao(),
        ]);
    }

    /**
     * Busca vendas de um pet
     * 
     * @Route("/pet/{petId}/vendas", name="clinica_pdv_pet_vendas", methods={"GET"})
     */
    public function vendasPorPet(int $petId, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            // Busca pet
            $pet = $em->getRepository(Pet::class)
                ->findOneBy(['id' => $petId, 'estabelecimentoId' => $estabelecimentoId]);

            if (!$pet) {
                return new JsonResponse(['ok' => false, 'msg' => 'Pet não encontrado'], 404);
            }

            // Busca vendas do pet
            $vendas = $em->getRepository(Venda::class)
                ->findByPet($estabelecimentoId, $petId);

            $vendasFormatadas = [];
            $totalGasto = 0;

            foreach ($vendas as $venda) {
                $totalGasto += $venda->getTotal();

                $itens = $em->getRepository(VendaItem::class)
                    ->findBy(['vendaId' => $venda->getId()]);

                $itensFormatados = array_map(fn($item) => [
                    'produto'        => $item->getProdutoNome(),
                    'quantidade'     => $item->getQuantidade(),
                    'valor_unitario' => number_format($item->getValorUnitario(), 2, ',', '.'),
                    'subtotal'       => number_format($item->getSubtotal(), 2, ',', '.'),
                ], $itens);

                $vendasFormatadas[] = [
                    'id' => $venda->getId(),
                    'data' => $venda->getData()->format('d/m/Y H:i'),
                    'total' => number_format($venda->getTotal(), 2, ',', '.'),
                    'metodo_pagamento' => $venda->getMetodoPagamento(),
                    'observacao' => $venda->getObservacao(),
                    'itens' => $itensFormatados,
                ];
            }

            return new JsonResponse([
                'ok' => true,
                'pet' => [
                    'id' => $pet->getId(),
                    'nome' => $pet->getNome(),
                    'especie' => $pet->getEspecie(),
                    'raca' => $pet->getRaca(),
                ],
                'vendas' => $vendasFormatadas,
                'resumo' => [
                    'total_vendas' => count($vendas),
                    'total_gasto' => number_format($totalGasto, 2, ',', '.'),
                    'ticket_medio' => count($vendas) > 0 
                        ? number_format($totalGasto / count($vendas), 2, ',', '.') 
                        : '0,00',
                ],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'msg' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resumo de vendas do dia
     * 
     * @Route("/resumo-dia", name="clinica_pdv_resumo_dia", methods={"GET"})
     */
    public function resumoDia(Request $request, VendaRepository $vendaRepo): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $dataParam = $request->query->get('data');
            $data = $dataParam ? new \DateTime($dataParam) : new \DateTime();

            $resumo = $vendaRepo->getResumoDoDia($estabelecimentoId, $data);
            $porMetodo = $vendaRepo->totalPorFormaPagamento($estabelecimentoId, $data);

            return new JsonResponse([
                'ok' => true,
                'data' => $data->format('d/m/Y'),
                'resumo' => [
                    'quantidade_vendas' => $resumo['quantidade_vendas'],
                    'total_vendas' => number_format($resumo['total_vendas'], 2, ',', '.'),
                    'ticket_medio' => number_format($resumo['ticket_medio'], 2, ',', '.'),
                    'por_metodo' => $porMetodo,
                ],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'msg' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helpers privados
     */

    /**
     * Resolve o nome de exibição de um VendaItem.
     *
     * Itens gravados antes da refatoração tinham o ID numérico no campo
     * produtoNome (snapshot). Este método detecta esse caso e busca o nome
     * real no cadastro de Produtos ou Serviços, com fallback graceful.
     */
    /**
     * Resolve o ID numérico de um VendaItem garantindo que nunca seja null/zero.
     *
     * Itens legados podem ter produtoId=null e o ID numérico guardado
     * no snapshot do nome (campo produto). Este método normaliza os dois casos.
     */
    private function resolverProdutoId(VendaItem $item): int
    {
        if ($item->getProdutoId()) {
            return $item->getProdutoId();
        }

        // Fallback: snapshot numérico (dado legado)
        $snapshot = $item->getProduto();
        if ($snapshot !== null && is_numeric(trim($snapshot))) {
            return (int)trim($snapshot);
        }

        // Sem ID válido — retorna 0 (tratado no EstoqueService)
        return 0;
    }

    private function resolverNomeItem(EntityManagerInterface $em, VendaItem $item): string
    {
        $snapshot = $item->getProdutoNome();
        $produtoId = $item->getProdutoId();

        // Se o snapshot não é numérico, é um nome válido — retorna direto
        if ($snapshot !== null && !is_numeric(trim($snapshot))) {
            return $snapshot;
        }

        // Snapshot é numérico (dado legado) ou nulo — busca no cadastro pelo produtoId
        $idBusca = $produtoId ?? (int)trim((string)$snapshot);

        if (!$idBusca) {
            return $snapshot ?? 'Item sem descrição';
        }

        if ($item->getTipo() === 'produto') {
            $entidade = $em->getRepository(Produto::class)->find($idBusca);
        } else {
            $entidade = $em->getRepository(Servico::class)->find($idBusca);
        }

        return $entidade ? $entidade->getNome() : ($snapshot ?? "Item #{$idBusca}");
    }

    private function normalizarItens(array $produtos, array $servicos): array
    {
        $itens = [];

        foreach ($produtos as $p) {
            $itens[] = [
                "id" => "prod_" . $p->getId(),
                "nome" => $p->getNome(),
                "valor" => $p->getPrecoVenda() ?? 0.0,
                "estoque" => $p->getEstoqueAtual() ?? 0,
                "tipo" => "Produto",
                "descricao" => $p->getNome() ?? "",
            ];
        }

        foreach ($servicos as $s) {
            $itens[] = [
                "id" => "serv_" . $s->getId(),
                "nome" => $s->getNome(),
                "valor" => $s->getValor() ?? 0.0,
                "estoque" => null,
                "tipo" => "Serviço",
                "descricao" => $s->getDescricao() ?? "",
            ];
        }

        usort($itens, fn($a, $b) => strcmp($a["nome"], $b["nome"]));

        return $itens;
    }

    private function buscarItensVenda(EntityManagerInterface $em, int $vendaId): array
    {
        $itens = $em->getRepository(VendaItem::class)->findBy(['vendaId' => $vendaId]);

        return array_map(fn($item) => [
            'descricao'      => $this->resolverNomeItem($em, $item),
            'quantidade'     => $item->getQuantidade(),
            'valor_unitario' => $item->getValorUnitario(),
            'subtotal'       => $item->getSubtotal(),
        ], $itens);
    }
}