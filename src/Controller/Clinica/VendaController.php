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
 * @Route("/clinica")
 */
class VendaController extends DefaultController
{

    /**
     * @Route("/pet/{petId}/venda/concluir", name="clinica_concluir_venda", methods={"POST"})
     */
    public function concluirVenda(Request $request, int $petId, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // --- Pegando todos os campos do POST
        $servicoId = $request->get('servico_id');
        $descricao = $request->get('descricao');
        $data = $request->get('data') ? new \DateTime($request->get('data')) : new \DateTime();
        $observacao = $request->get('observacao');
        $metodoPagamento = $request->get('metodo_pagamento');

        // --- Se vier ID do serviço, busca o valor oficial no banco (anti-gambiarra)
        if ($servicoId) {
            $servico = $entityManager->getRepository(Servico::class)->find($servicoId);
            if (!$servico) {
                return $this->json(['status' => 'error', 'mensagem' => 'Serviço não encontrado!'], 404);
            }
            $descricao = $servico->getNome();
            $valor = (float)$servico->getValor();
        }


        $valorFinal = 0;
        $descontoFinal = 0;
        $descricaoFinal = '';


        $descricoes = (array)$request->get('descricao', []);
        $descontos = (array)$request->get('desconto', []);
        $valoresCalculados = (array)$request->get('valor_calculado', []); // vindo do JS (internações)
        $valoresSimples = (array)$request->get('valor', []); // outros serviços
        $quantidades = (array)$request->get('quantidade_diarias', []);

        $valorFinal = 0;
        $descontoFinal = 0;
        $descricaoFinal = '';

        foreach ($descricoes as $i => $servicoId) {
            $servico = $this->getRepositorio(\App\Entity\Servico::class)->listaServicoPorId($baseId, $servicoId);
            if (!$servico) continue;

            $descricaoServico = $servico['descricao'] ?? 'Serviço';
            $valorBase = (float)($servico['valor'] ?? 0);
            $desconto = isset($descontos[$i]) ? (float)$descontos[$i] : 0;

            // 🔍 Tenta achar uma quantidade ou valor calculado mesmo que o índice não coincida
            $quantidade = 1;
            $valorCalculado = null;

            if (stripos($descricaoServico, 'internação') !== false) {
                // Procura o primeiro valor em quantidade_diarias
                if (!empty($quantidades)) {
                    $quantidade = (int)reset($quantidades); // pega o primeiro valor do array
                }
                if (!empty($valoresCalculados)) {
                    $valorCalculado = (float)reset($valoresCalculados);
                }

                // Calcula corretamente
                if ($valorCalculado > 0) {
                    $valorBase = $valorCalculado;
                } elseif ($quantidade > 1) {
                    $valorBase *= $quantidade;
                }

                $descricaoServico .= " ({$quantidade} diárias)";
            }

            $valorFinal += $valorBase;
            $descontoFinal += $desconto;
            $descricaoFinal .= $descricaoServico . ' + ';
        }


        $valorFinal = max(0, $valorFinal - $descontoFinal);

        if ($metodoPagamento === 'pendente') {
            $financeiroPendente = new FinanceiroPendente();
            $financeiroPendente->setEstabelecimentoId($baseId);
            $financeiroPendente->setPetId($petId);
            $financeiroPendente->setValor($valorFinal);
            $financeiroPendente->setDescricao(trim($descricaoFinal, ' +'));
            $financeiroPendente->setData($data);
            $financeiroPendente->setStatus('pendente');
            $financeiroPendente->setOrigem('clinica');
            $financeiroPendente->setMetodoPagamento($metodoPagamento);

            $entityManager->persist($financeiroPendente);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Lançado como pendente.',
            ]);
        } else {
            $financeiro = new Financeiro();
            $financeiro->setEstabelecimentoId($baseId);
            $financeiro->setPetId($petId);
            $financeiro->setValor($valorFinal);
            $financeiro->setDescricao(trim($descricaoFinal, ' +'));
            $financeiro->setData($data);
            $financeiro->setOrigem('clinica');
            $financeiro->setStatus('concluido');

            $entityManager->persist($financeiro);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Pagamento registrado no financeiro!',
            ]);
        }

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
