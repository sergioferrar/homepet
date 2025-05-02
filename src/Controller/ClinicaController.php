<?php

namespace App\Controller;

use App\Entity\Atendimento;
use App\Entity\Internacao;
use App\Entity\Pet;
use App\Entity\Procedimento;
use App\Repository\AtendimentoRepository;
use App\Repository\InternacaoRepository;
use App\Repository\PetRepository;
use App\Repository\ProcedimentoRepository;
use App\Service\AtendimentoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica")
 */
class ClinicaController extends AbstractController
{
    private $atendimentoRepository;
    private $petRepository;
    private $procedimentoRepository;
    private $internacaoRepository;
    private $atendimentoService;

    public function __construct(
        AtendimentoRepository $atendimentoRepository,
        PetRepository $petRepository,
        ProcedimentoRepository $procedimentoRepository,
        InternacaoRepository $internacaoRepository,
        AtendimentoService $atendimentoService
    ) {
        // $this->getRepositorio(\App\Entity\Atendimento::class) = $atendimentoRepository;
        $this->petRepository = $petRepository;
        $this->procedimentoRepository = $procedimentoRepository;
        $this->internacaoRepository = $internacaoRepository;
        $this->atendimentoService = $atendimentoService;
    }

    /**
     * @Route("/", name="clinica_index")
     */
    public function index(): Response
    {
        $atendimentosHoje = $this->getRepositorio(\App\Entity\Atendimento::class)->findAtendimentosHoje();
        $internacoesAtivas = $this->internacaoRepository->findInternacoesAtivas();

        return $this->render('clinica/index.html.twig', [
            'atendimentos_hoje' => $atendimentosHoje,
            'internacoes_ativas' => $internacoesAtivas,
        ]);
    }

    /**
     * @Route("/atendimentos", name="clinica_atendimentos")
     */
    public function listarAtendimentos(Request $request): Response
    {
        $filtro = $request->query->get('filtro', 'todos');
        $dataInicio = $request->query->get('data_inicio');
        $dataFim = $request->query->get('data_fim');

        $atendimentos = $this->getRepositorio(\App\Entity\Atendimento::class)->findByFiltro($filtro, $dataInicio, $dataFim);

        return $this->render('clinica/atendimentos.html.twig', [
            'atendimentos' => $atendimentos,
            'filtro' => $filtro,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ]);
    }

    /**
     * @Route("/atendimento/novo", name="clinica_atendimento_novo")
     */
    public function novoAtendimento(Request $request): Response
    {
        $pets = $this->petRepository->findAll();
        $procedimentos = $this->procedimentoRepository->findAll();

        if ($request->isMethod('POST')) {
            $petId = $request->request->get('pet');
            $procedimentoIds = $request->request->getInt('procedimentos', []);
            $observacoes = $request->request->get('observacoes');
            $dataAtendimento = $request->request->get('data_atendimento');
            $horaAtendimento = $request->request->get('hora_atendimento');
            
            $resultado = $this->atendimentoService->criarAtendimento(
                $petId, 
                $procedimentoIds, 
                $observacoes, 
                $dataAtendimento, 
                $horaAtendimento
            );
            
            if ($resultado['sucesso']) {
                $this->addFlash('success', 'Atendimento agendado com sucesso!');
                return $this->redirectToRoute('clinica_atendimentos');
            } else {
                $this->addFlash('error', $resultado['mensagem']);
            }
        }

        return $this->render('clinica/atendimento_novo.html.twig', [
            'pets' => $pets,
            'procedimentos' => $procedimentos,
        ]);
    }

    /**
     * @Route("/atendimento/{id}", name="clinica_atendimento_detalhes")
     */
    public function detalhesAtendimento(int $id): Response
    {
        $atendimento = $this->getRepositorio(\App\Entity\Atendimento::class)->find($id);
        
        if (!$atendimento) {
            throw $this->createNotFoundException('Atendimento não encontrado');
        }

        return $this->render('clinica/atendimento_detalhes.html.twig', [
            'atendimento' => $atendimento,
        ]);
    }

    /**
     * @Route("/atendimento/{id}/finalizar", name="clinica_atendimento_finalizar")
     */
    public function finalizarAtendimento(Request $request, int $id): Response
    {
        $atendimento = $this->getRepositorio(\App\Entity\Atendimento::class)->find($id);
        
        if (!$atendimento) {
            throw $this->createNotFoundException('Atendimento não encontrado');
        }

        if ($request->isMethod('POST')) {
            $diagnostico = $request->request->get('diagnostico');
            $prescricao = $request->request->get('prescricao');
            $observacoes = $request->request->get('observacoes');
            
            $resultado = $this->atendimentoService->finalizarAtendimento(
                $atendimento,
                $diagnostico,
                $prescricao,
                $observacoes
            );
            
            if ($resultado['sucesso']) {
                $this->addFlash('success', 'Atendimento finalizado com sucesso!');
                return $this->redirectToRoute('clinica_atendimentos');
            } else {
                $this->addFlash('error', $resultado['mensagem']);
            }
        }

        return $this->render('clinica/atendimento_finalizar.html.twig', [
            'atendimento' => $atendimento,
        ]);
    }

    /**
     * @Route("/internacoes", name="clinica_internacoes")
     */
    public function listarInternacoes(): Response
    {
        $internacoes = $this->internacaoRepository->findAll();

        return $this->render('clinica/internacoes.html.twig', [
            'internacoes' => $internacoes,
        ]);
    }

    /**
     * @Route("/internacao/nova", name="clinica_internacao_nova")
     */
    public function novaInternacao(Request $request): Response
    {
        $pets = $this->petRepository->findAll();

        if ($request->isMethod('POST')) {
            $petId = $request->request->get('pet');
            $motivo = $request->request->get('motivo');
            $observacoes = $request->request->get('observacoes');
            $dataInicio = $request->request->get('data_inicio');
            
            $pet = $this->petRepository->find($petId);
            
            if (!$pet) {
                $this->addFlash('error', 'Pet não encontrado');
            } else {
                $internacao = new Internacao();
                $internacao->setPet($pet);
                $internacao->setMotivo($motivo);
                $internacao->setObservacoes($observacoes);
                $internacao->setDataInicio(new \DateTime($dataInicio));
                $internacao->setStatus('ativa');
                
                $this->internacaoRepository->save($internacao, true);
                
                $this->addFlash('success', 'Internação iniciada com sucesso!');
                return $this->redirectToRoute('clinica_internacoes');
            }
        }

        return $this->render('clinica/internacao_nova.html.twig', [
            'pets' => $pets,
        ]);
    }

    /**
     * @Route("/internacao/{id}", name="clinica_internacao_detalhes")
     */
    public function detalhesInternacao(int $id): Response
    {
        $internacao = $this->internacaoRepository->find($id);
        
        if (!$internacao) {
            throw $this->createNotFoundException('Internação não encontrada');
        }

        return $this->render('clinica/internacao_detalhes.html.twig', [
            'internacao' => $internacao,
        ]);
    }

    /**
     * @Route("/internacao/{id}/finalizar", name="clinica_internacao_finalizar")
     */
    public function finalizarInternacao(Request $request, int $id): Response
    {
        $internacao = $this->internacaoRepository->find($id);
        
        if (!$internacao) {
            throw $this->createNotFoundException('Internação não encontrada');
        }

        if ($request->isMethod('POST')) {
            $observacoesSaida = $request->request->get('observacoes_saida');
            $dataSaida = $request->request->get('data_saida');
            
            $internacao->setObservacoesSaida($observacoesSaida);
            $internacao->setDataSaida(new \DateTime($dataSaida));
            $internacao->setStatus('finalizada');
            
            $this->internacaoRepository->save($internacao, true);
            
            $this->addFlash('success', 'Internação finalizada com sucesso!');
            return $this->redirectToRoute('clinica_internacoes');
        }

        return $this->render('clinica/internacao_finalizar.html.twig', [
            'internacao' => $internacao,
        ]);
    }

    /**
     * @Route("/procedimentos", name="clinica_procedimentos")
     */
    public function listarProcedimentos(): Response
    {
        $procedimentos = $this->procedimentoRepository->findAll();

        return $this->render('clinica/procedimentos.html.twig', [
            'procedimentos' => $procedimentos,
        ]);
    }

    /**
     * @Route("/procedimento/novo", name="clinica_procedimento_novo")
     */
    public function novoProcedimento(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $nome = $request->request->get('nome');
            $descricao = $request->request->get('descricao');
            $valor = $request->request->get('valor');
            $duracao = $request->request->get('duracao');
            
            $procedimento = new Procedimento();
            $procedimento->setNome($nome);
            $procedimento->setDescricao($descricao);
            $procedimento->setValor($valor);
            $procedimento->setDuracao($duracao);
            
            $this->procedimentoRepository->save($procedimento, true);
            
            $this->addFlash('success', 'Procedimento cadastrado com sucesso!');
            return $this->redirectToRoute('clinica_procedimentos');
        }

        return $this->render('clinica/procedimento_novo.html.twig');
    }

    /**
     * @Route("/relatorios", name="clinica_relatorios")
     */
    public function relatorios(Request $request): Response
    {
        $tipoRelatorio = $request->query->get('tipo', 'atendimentos');
        $dataInicio = $request->query->get('data_inicio');
        $dataFim = $request->query->get('data_fim');
        
        $dados = [];
        
        if ($tipoRelatorio === 'atendimentos') {
            $dados = $this->getRepositorio(\App\Entity\Atendimento::class)->gerarRelatorioAtendimentos($dataInicio, $dataFim);
        } elseif ($tipoRelatorio === 'internacoes') {
            $dados = $this->internacaoRepository->gerarRelatorioInternacoes($dataInicio, $dataFim);
        } elseif ($tipoRelatorio === 'financeiro') {
            $dados = $this->getRepositorio(\App\Entity\Atendimento::class)->gerarRelatorioFinanceiro($dataInicio, $dataFim);
        }

        return $this->render('clinica/relatorios.html.twig', [
            'tipo_relatorio' => $tipoRelatorio,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'dados' => $dados,
        ]);
    }
}
