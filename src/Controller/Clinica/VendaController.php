<?php

namespace App\Controller\Clinica;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\DocumentoModelo;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Internacao;
use App\Entity\InternacaoExecucao;
use App\Entity\InternacaoPrescricao;
use App\Entity\Medicamento;
use App\Entity\Pet;
use App\Entity\Servico;
use App\Entity\Vacina;
use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Entity\Veterinario;
use App\Repository\ConsultaRepository;
use App\Repository\DocumentoModeloRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Repository\FinanceiroRepository;
use App\Repository\InternacaoRepository;
use App\Repository\VeterinarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GeradorpdfService;
use App\Repository\PetRepository;
use App\Controller\DefaultController;


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
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(\App\Entity\Pet::class)->find($request->get('pet_id'));

        $metodoPagamento = $request->get('metodo_pagamento');
        $origem = 'clinica';

        // 1️⃣ Cria a VENDA
        $venda = new Venda();
        $venda->setEstabelecimentoId($baseId);
        $venda->setCliente($pet->getDono_Id());
        $venda->setPetId($request->get('pet_id'));
        $venda->setParcelas($request->get('parcelas'));
        $venda->setOrigem($origem);
        $venda->setMetodoPagamento($metodoPagamento);
        $venda->setStatus($metodoPagamento === 'pendente' ? 'Pendente' : 'Aberta');

        $em->persist($venda);

        // 2️⃣ Processa itens
        $descricoes = (array)$request->get('descricao', []);
        $descontos = (array)$request->get('desconto', []);
        $valores = (array)$request->get('valor', []);
        $quantidades = (array)$request->get('quantidade_diarias', []);

        $valorTotal = 0;
        $descontoTotal = 0;

        foreach ($descricoes as $i => $servicoId) {

            $servico = $this->getRepositorio(Servico::class)
                ->listaServicoPorId($baseId, $servicoId);

            if (!$servico) continue;

            $quantidade = $quantidades[$i] ?? 1;
            $valorUnitario = (float)$servico['valor'];
            $desconto = (float)($descontos[$i] ?? 0);

            $valorItem = ($valorUnitario * $quantidade) - $desconto;

            $item = new VendaItem();
            $item->setVenda($venda);
            $item->setTipo('servico');
            $item->setProduto($servicoId);
            $item->setQuantidade($quantidade);
            $item->setValorUnitario($valorUnitario);
            $item->setSubtotal($valorItem);

            $em->persist($item);

            $valorTotal += $valorItem;
            $descontoTotal += $desconto;
        }

        

        // 3️⃣ Finaliza venda
        $venda->setTotal($valorTotal + $descontoTotal);
        // $venda->setDescontoTotal($descontoTotal);
        // $venda->setValorFinal($valorTotal);

        // 4️⃣ Se não for pendente, cria financeiro
        if ($metodoPagamento !== 'pendente') {
            $financeiro = new \App\Entity\Financeiro();
            $financeiro->setVenda($venda->getTotal());
            $financeiro->setEstabelecimentoId($baseId);
            $financeiro->setValor($valorTotal);
            $financeiro->setMetodoPagamento($metodoPagamento);
            $financeiro->setStatus('concluido');

            $em->persist($financeiro);
        }

        $em->flush();

        return $this->json([
            'status' => 'success',
            'mensagem' => 'Venda registrada com sucesso!',
            'venda_id' => $venda->getId()
        ]);
    }

    /**
     * @Route("/pet/{petId}/venda/{id}/inativar", name="clinica_inativar_venda", methods={"POST"})
     */
    public function inativarVenda(Request $request, FinanceiroRepository $financeiroRepository, FinanceiroPendenteRepository $pendenteRepository, int $petId, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();


        try {
            $financeiroRepository->inativar($baseId, $id);

            $pendenteRepository->inativar($baseId, $id);

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
            // 🔧 Troca o banco para o da clínica atual
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

            $metodo = $request->get('metodo_pagamento') ?: 'pendente';
            $financeiro->setMetodoPagamento($metodo);
            $financeiro->setObservacoes($request->get('observacao'));

            $financeiroRepository->update($baseId, $financeiro);

            return new JsonResponse(['status' => 'success', 'mensagem' => 'Venda atualizada com sucesso.']);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'mensagem' => 'Erro ao editar venda: ' . $e->getMessage(),
            ], 500);
        }
    }
}
