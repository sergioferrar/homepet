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
    public function concluirVenda(Request $request, int $petId, EntityManagerInterface $em): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();

            $petIdFromRequest = $request->get('pet_id');
            if (!$petIdFromRequest) {
                return $this->json(['status' => 'error', 'mensagem' => 'ID do pet não informado.'], 400);
            }

            $pet = $this->getRepositorio(\App\Entity\Pet::class)->findPetById($baseId, $petIdFromRequest);
            if (!$pet) {
                return $this->json(['status' => 'error', 'mensagem' => 'Pet não encontrado.'], 404);
            }

            $metodoPagamento = $request->get('metodo_pagamento', 'pendente');
            $origem = $request->get('origem', 'clinica');

            // Define status baseado no método de pagamento
            $status = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

            // 1️⃣ Cria a venda
            $venda = new Venda();
            $venda->setEstabelecimentoId($baseId);
            $venda->setCliente($pet['dono_id']);
            $venda->setPetId($petIdFromRequest);
            $venda->setParcelas($request->get('parcelas', 1));
            $venda->setOrigem($origem);
            $venda->setMetodoPagamento($metodoPagamento);
            $venda->setStatus($status);
            $venda->setData(new \DateTime());
            $venda->setObservacao($request->get('observacao'));
            $venda->setTotal(0);

            $em->persist($venda);
            $em->flush(); // flush para obter o ID

            // 2️⃣ Processa itens
            $descricoes  = (array)$request->get('descricao', []);
            $descontos   = (array)$request->get('desconto', []);
            $quantidades = (array)$request->get('quantidade_diarias', []);

            $valorTotal = 0;

            foreach ($descricoes as $i => $itemId) {
                $tipo = 'servico';
                $realId = $itemId;

                // Detecta prefixo S- ou P-
                if (str_starts_with($itemId, 'S-')) {
                    $tipo = 'servico';
                    $realId = substr($itemId, 2);
                } elseif (str_starts_with($itemId, 'P-')) {
                    $tipo = 'produto';
                    $realId = substr($itemId, 2);
                }

                $nome = 'Item';
                $valorUnitario = 0;

                if ($tipo === 'servico') {
                    $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($baseId, $realId);
                    if (!$servico) continue;
                    $nome = $servico['nome'] ?? 'Serviço';
                    $valorUnitario = (float)$servico['valor'];
                } else {
                    $produto = $this->getRepositorio(\App\Entity\Produto::class)->find($realId);
                    if (!$produto) continue;
                    $nome = $produto->getNome();
                    $valorUnitario = (float)$produto->getPrecoVenda();
                }

                $quantidade = (int)($quantidades[$i] ?? 1);
                $desconto   = (float)($descontos[$i] ?? 0);
                $valorItem  = ($valorUnitario * $quantidade) - $desconto;

                $item = new VendaItem();
                $item->setVendaId($venda->getId());
                $item->setTipo($tipo);
                $item->setProdutoId((int)$realId);
                $item->setProdutoNome($nome);
                $item->setQuantidade($quantidade);
                $item->setPrecoUnitario($valorUnitario);
                $item->setSubtotal($valorItem);

                $em->persist($item);
                $valorTotal += $valorItem;
            }

            // 3️⃣ Atualiza total
            $venda->setTotal($valorTotal);
            $em->flush();

            // 4️⃣ Registra no financeiro
            $descricaoFinanceiro = sprintf(
                'Venda Clínica - Pet: %s (#%d)',
                $pet['nome'] ?? 'Pet',
                $petIdFromRequest
            );

            if ($metodoPagamento === 'pendente') {
                // Vai para financeiro pendente
                $finPendente = new FinanceiroPendente();
                $finPendente->setDescricao($descricaoFinanceiro);
                $finPendente->setValor($valorTotal);
                $finPendente->setData(new \DateTime());
                $finPendente->setMetodoPagamento('pendente');
                $finPendente->setOrigem('clinica');
                $finPendente->setStatus('Pendente');
                $finPendente->setTipo('ENTRADA');
                $finPendente->setEstabelecimentoId($baseId);
                $em->persist($finPendente);
            } else {
                // Vai para financeiro (pago)
                $financeiro = new Financeiro();
                $financeiro->setDescricao($descricaoFinanceiro);
                $financeiro->setValor($valorTotal);
                $financeiro->setData(new \DateTime());
                $financeiro->setMetodoPagamento($metodoPagamento);
                $financeiro->setOrigem('clinica');
                $financeiro->setStatus('Pago');
                $financeiro->setTipo('ENTRADA');
                $financeiro->setEstabelecimentoId($baseId);
                $em->persist($financeiro);
            }

            $em->flush();

            $mensagem = ($metodoPagamento === 'pendente')
                ? 'Venda registrada como pendente no financeiro!'
                : 'Venda adicionada ao carrinho! Finalize no PDV.';

            return $this->json([
                'status'   => 'success',
                'mensagem' => $mensagem,
                'venda_id' => $venda->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao concluir venda', ['erro' => $e->getMessage()]);
            return $this->json(['status' => 'error', 'mensagem' => 'Erro: ' . $e->getMessage()], 500);
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
