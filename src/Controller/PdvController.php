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
use App\Repository\ClienteRepository;
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
    public function index(EntityManagerInterface $em): Response
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
        $vendas = $em->getRepository(Venda::class);
        $vendasCarrinho = $vendas->findCarrinho($estabelecimentoId);
        
        // Carrega itens de cada venda em carrinho
        foreach ($vendasCarrinho as &$venda) {
            $venda['itens'] = $this->buscarItensVenda($em, $venda['id']);
        }

        return $this->render("clinica/pdv.html.twig", [
            "produtos" => $itensNormalizados,
            "clientes" => $clientesNormalizados,
            "vendas_carrinho" => $vendasCarrinho,
        ]);
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
    public function finalizarCarrinho(Request $request, int $id): JsonResponse
    {
        $this->switchDB();
        
        try {
            $dto = FinalizarCarrinhoDTO::fromArray($id, $request->request->all());

            // Valida DTO
            $errors = $dto->validate();
            if (!empty($errors)) {
                return new JsonResponse([
                    'status' => 'error',
                    'mensagem' => implode(' ', $errors)
                ], 400);
            }

            // Processa finalização via service
            $resultado = $this->pdvService->finalizarCarrinho($dto);

            return new JsonResponse($resultado, $resultado['status'] === 'success' ? 200 : 400);

        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'mensagem' => 'Erro ao finalizar venda: ' . $e->getMessage()
            ], 500);
        }
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
                    ->findBy(['venda' => $venda]);

                $itensFormatados = array_map(fn($item) => [
                    'produto' => $item->getProduto(),
                    'quantidade' => $item->getQuantidade(),
                    'valor_unitario' => number_format($item->getValorUnitario(), 2, ',', '.'),
                    'subtotal' => number_format($item->getSubtotal(), 2, ',', '.'),
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
        $itens = $em->getRepository(VendaItem::class)->findBy(['venda' => $vendaId]);
        
        return array_map(fn($item) => [
            'descricao' => $item->getProduto(),
            'quantidade' => $item->getQuantidade(),
            'valor_unitario' => $item->getValorUnitario(),
            'subtotal' => $item->getSubtotal(),
        ], $itens);
    }
}