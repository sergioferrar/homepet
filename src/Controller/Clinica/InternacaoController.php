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
 * @Route("dashboard/clinica")
 */
class InternacaoController extends DefaultController
{


    /**
     * @Route("/pet/{petId}/internacao/nova", name="clinica_nova_internacao", methods={"GET", "POST"})
     */
    public function novaInternacao(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $internacaoRepo = $this->getRepositorio(Internacao::class);
        $veterinarioRepo = $this->getRepositorio(Veterinario::class);

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // lista para o formulário
        $veterinarios = $veterinarioRepo->findBy(['estabelecimentoId' => $baseId]);
        $boxes = [
            ['id' => 1, 'nome' => 'Box 1'],
            ['id' => 2, 'nome' => 'Box 2'],
            ['id' => 3, 'nome' => 'Box 3'],
        ];

        // POST: salva e redireciona para a ficha criada
        if ($request->isMethod('POST')) {
            $internacao = new Internacao();

            $donoId = $pet['dono_id'] ?? null;

            $internacao->setPetId($petId);
            $internacao->setDonoId($donoId);
            $internacao->setEstabelecimentoId($baseId);
            $internacao->setDataInicio(new \DateTime());
            $internacao->setStatus('ativa');
            $internacao->setMotivo((string)$request->get('queixa'));

            // Campos adicionais
            $internacao->setSituacao((string)$request->get('situacao'));
            $internacao->setRisco((string)$request->get('risco'));
            $internacao->setVeterinarioId((int)$request->get('veterinario_id'));
            $internacao->setBox((string)$request->get('box'));

            // Alta prevista (opcional)
            $altaPrevistaStr = trim((string)$request->get('alta_prevista'));
            if ($altaPrevistaStr !== '') {
                try {
                    $internacao->setAltaPrevista(new \DateTime($altaPrevistaStr));
                } catch (\Exception $e) {
                    $internacao->setAltaPrevista(null);
                }
            }

            $internacao->setDiagnostico((string)$request->get('diagnostico'));
            $internacao->setPrognostico((string)$request->get('prognostico'));
            $internacao->setAnotacoes((string)$request->get('alergias_marcacoes'));

            // Salva e obtém o ID gerado
            $novoId = $internacaoRepo->inserirInternacao($baseId, $internacao);

            // Cria um evento inicial na timeline da internação
            $internacaoRepo->inserirEvento(
                $baseId,
                $novoId,
                $petId,
                'Internação',
                'Internação iniciada',
                sprintf(
                    'Motivo: %s | Situação: %s | Risco: %s | Box: %s',
                    (string)$internacao->getMotivo(),
                    (string)$internacao->getSituacao(),
                    (string)$internacao->getRisco(),
                    (string)$internacao->getBox()
                ),
                new \DateTime()
            );

            $this->addFlash('success', 'Internação registrada com sucesso!');
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $novoId]);
        }

        // GET: exibe o formulário
        return $this->render('clinica/nova_internacao.html.twig', [
            'pet' => $pet,
            'veterinarios' => $veterinarios,
            'boxes' => $boxes,
        ]);
    }
    



    /**
     * @Route("/internacao/{id}/ficha", name="clinica_ficha_internacao", methods={"GET"})
     */
    public function fichaInternacao(
        int                    $id,
        InternacaoRepository   $internacaoRepo,
        VeterinarioRepository  $veterinarioRepo,
        EntityManagerInterface $em
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $internacao = $internacaoRepo->findInternacaoCompleta($baseId, $id);
        if (!$internacao) {
            throw $this->createNotFoundException('A ficha de internação não foi encontrada.');
        }

        // --- TIMELINE ---
        $rows = $internacaoRepo->listarEventosPorInternacao($baseId, $id);
        $timeline = array_map(function (array $r) {
            try {
                $r['data_hora'] = !empty($r['data_hora']) ? new \DateTime($r['data_hora']) : new \DateTime();
            } catch (\Exception $e) {
                $r['data_hora'] = new \DateTime();
            }
            $r['titulo'] = $r['titulo'] ?? '—';
            $r['descricao'] = $r['descricao'] ?? '';
            $r['tipo'] = (string)($r['tipo'] ?? 'internacao');
            return $r;
        }, $rows);
        usort($timeline, fn($a, $b) => $b['data_hora'] <=> $a['data_hora']);

        // --- PRESCRIÇÕES ---
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();
        $prescricoes = $em->getRepository(InternacaoPrescricao::class)->findBy(['internacaoId' => $id]);

        // --- CALENDÁRIO ---
        // Busca TODOS os eventos de prescrição desta internação de uma vez
        $todosEventos = $em->getRepository(\App\Entity\InternacaoEvento::class)
            ->createQueryBuilder('e')
            ->where('e.internacaoId = :internacaoId')
            ->andWhere('e.tipo = :tipo')
            ->setParameter('internacaoId', $id)
            ->setParameter('tipo', 'prescricao')
            ->orderBy('e.dataHora', 'ASC')
            ->getQuery()
            ->getResult();

        // Busca todas as execuções de uma vez
        $eventoIds = array_map(fn($e) => $e->getId(), $todosEventos);
        $execucoes = [];
        if (!empty($eventoIds)) {
            $execucoesResult = $em->getRepository(\App\Entity\InternacaoExecucao::class)
                ->createQueryBuilder('ex')
                ->where('ex.prescricaoId IN (:eventoIds)')
                ->setParameter('eventoIds', $eventoIds)
                ->getQuery()
                ->getResult();

            // Indexa por prescricaoId
            foreach ($execucoesResult as $exec) {
                $execucoes[$exec->getPrescricaoId()] = $exec;
            }
        }

        // Processa todos os eventos
        $events = [];
        foreach ($todosEventos as $evento) {
            $execucao = $execucoes[$evento->getId()] ?? null;

            $cor = '#0d6efd';
            $habilitaModal = true;
            if ($execucao && $execucao->getStatus() == 'confirmado') {
                $habilitaModal = false;
                $cor = 'green';
            }

            $events[] = [
                'title' => $evento->getTitulo(),
                'start' => $evento->getDataHora()->format(\DateTime::ATOM),
                'end' => (clone $evento->getDataHora())->modify('+30 minutes')->format(\DateTime::ATOM),
                'color' => $cor,
                'prescricao_id' => $evento->getId(),
                'habilita_modal' => $habilitaModal,
            ];
        }

        // --- VETERINÁRIOS ---
        $veterinarios = $veterinarioRepo->findBy(['estabelecimentoId' => $baseId]);

        $data = [];
        $data['internacao'] = $internacao;
        $data['timeline'] = $timeline;
        $data['medicamentos'] = $medicamentos;
        $data['prescricoes'] = $prescricoes;
        $data['calendario_prescricoes'] = $events;
        $data['veterinarios'] = $veterinarios;
        // dd($data);
        return $this->render('clinica/ficha_internacao.html.twig', $data);
    }

    /**
     * @Route("/internacao/{id}/acao/{acao}", name="clinica_internacao_acao", methods={"POST"})
     */
    public function acaoInternacao(int $id, string $acao, Request $request, InternacaoRepository $internacaoRepo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Validar CSRF token (um único nome genérico)
        if (!$this->isCsrfTokenValid('internacao_acao_' . $id, $request->get('_token'))) {
            return $this->json(['ok' => false, 'msg' => 'Token inválido.'], 400);
        }

        // Busca internação
        $internacao = $internacaoRepo->buscarPorId($baseId, $id);
        if (!$internacao) {
            return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
        }

        // Normaliza a ação
        $acoesValidas = [
            'alta' => ['status' => 'finalizada', 'titulo' => 'Alta concedida', 'descricao' => 'Internação finalizada pelo sistema'],
            'obito' => ['status' => 'obito', 'titulo' => 'Óbito registrado', 'descricao' => 'Internação encerrada por óbito'],
            'cancelar' => ['status' => 'cancelada', 'titulo' => 'Internação cancelada', 'descricao' => 'Internação cancelada pelo sistema'],
            'box' => ['status' => 'ativa', 'titulo' => 'Box alterado', 'descricao' => 'Box de internação atualizado'],
            'editar' => ['status' => 'ativa', 'titulo' => 'Internação editada', 'descricao' => 'Dados da internação foram atualizados'],
        ];

        if (!isset($acoesValidas[$acao])) {
            return $this->json(['ok' => false, 'msg' => 'Ação inválida.'], 400);
        }

        if (in_array($internacao['status'], ['alta', 'obito'])) {
            return $this->json([
                'ok' => false,
                'msg' => 'Esta internação já foi encerrada por ' . $internacao['status'] . '.'
            ]);
        }

        $acaoInfo = $acoesValidas[$acao];

        $internacaoRepo->atualizarStatus($baseId, $id, $acaoInfo['status']);

        // Cria evento na timeline
        $internacaoRepo->inserirEvento(
            $baseId,
            $id,
            (int)$internacao['pet_id'],
            ucfirst($acao),
            $acaoInfo['titulo'],
            $acaoInfo['descricao'],
            new \DateTime()
        );

        return $this->json(['ok' => true]);
    }


    /**
     * @Route("/internacao/{id}/prescricao/nova", name="clinica_internacao_prescricao_nova", methods={"GET","POST"})
     */
    public function novaPrescricao(
        int                    $id,
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $internacaoRepo = $this->getRepositorio(\App\Entity\Internacao::class);

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $id]);
        }

        try {
            $internacao = $em->getRepository(Internacao::class)->find($id);
            if (!$internacao) {
                return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
            }

            $medicamentoId = (int)$request->get('medicamentoId');
            $dose = trim((string)$request->get('dose'));
            $frequenciaHoras = (int)$request->get('frequencia_horas');
            $duracaoDias = (int)$request->get('duracao_dias');
            $dataHoraPrimeiraDose = $request->get('data_hora_primeira_dose');

            if (!$medicamentoId || empty($dose) || $frequenciaHoras <= 0 || $duracaoDias <= 0 || empty($dataHoraPrimeiraDose)) {
                return $this->json(['ok' => false, 'msg' => 'Campos obrigatórios faltando.'], 400);
            }

            $medicamento = $em->getRepository(Medicamento::class)->find($medicamentoId);
            if (!$medicamento) {
                return $this->json(['ok' => false, 'msg' => 'Medicamento não encontrado.'], 404);
            }

            // --- Cria a prescrição ---
            $prescricao = new InternacaoPrescricao();
            $prescricao->setInternacaoId($internacao->getId());
            $prescricao->setMedicamento($medicamento);
            $prescricao->setDescricao($medicamento->getNome());
            $prescricao->setDose($dose);

            // Salva tanto a string "bonita" quanto os valores numéricos
            $prescricao->setFrequencia(sprintf('a cada %d horas por %d dias', $frequenciaHoras, $duracaoDias));
            $prescricao->setFrequenciaHoras($frequenciaHoras);
            $prescricao->setDuracaoDias($duracaoDias);

            $prescricao->setDataHora(new \DateTime($dataHoraPrimeiraDose));
            $prescricao->setCriadoEm(new \DateTime());


            $em->persist($prescricao);
            $em->flush();

            // --- Cria eventos da timeline ---
            $petId = method_exists($internacao, 'getPetId')
                ? $internacao->getPetId()
                : ($internacao->getPet() ? $internacao->getPet()->getId() : null);

            if ($petId) {
                $numDoses = ($duracaoDias * 24) / $frequenciaHoras;

                for ($i = 0; $i < $numDoses; $i++) {
                    $dataDose = (new \DateTime($dataHoraPrimeiraDose))->modify('+' . ($i * $frequenciaHoras) . ' hours');

                    $descricaoEvento = sprintf(
                        "Medicamento: %s | Dose: %s | Frequência: %s",
                        $medicamento->getNome(),
                        $dose,
                        $prescricao->getFrequencia()
                    );

                    $internacaoRepo->inserirEvento(
                        $baseId,
                        $id,
                        $petId,
                        'prescricao',
                        $medicamento->getNome(),
                        $descricaoEvento,
                        $dataDose
                    );
                }
            }

            return $this->json(['ok' => true, 'msg' => 'Prescrição salva com sucesso!']);

        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'msg' => 'Erro interno: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @Route("/internacao/{id}/prescricao/{eventoId}/executar", name="clinica_internacao_prescricao_executar", methods={"POST"})
     */
    public function executarPrescricao(
        int                    $id,
        int                    $eventoId,
        Request                $request,
        EntityManagerInterface $em,
        InternacaoRepository   $internacaoRepo
    ): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $horaAplicacao = $request->get('hora_aplicacao');
        $veterinarioId = (int)$request->get('veterinario_id');
        $anotacoes = $request->get('anotacoes');

        if (!$horaAplicacao || !$veterinarioId) {
            return $this->json(['ok' => false, 'msg' => 'Preencha hora e veterinário.'], 400);
        }

        try {

            // Busca o veterinário
            $veterinario = $em->getRepository(\App\Entity\Veterinario::class)->find($veterinarioId);
            if (!$veterinario) {
                return $this->json(['ok' => false, 'msg' => 'Veterinário não encontrado.'], 404);
            }

            // Cria entidade de execução
            $execucao = new InternacaoExecucao();
            $execucao->setInternacaoId($id);
            $execucao->setPrescricaoId($eventoId);
            $execucao->setVeterinario($veterinario);
            $execucao->setDataExecucao(new \DateTime($horaAplicacao));
            $execucao->setStatus('confirmado'); // marca como confirmado
            $execucao->setAnotacoes($anotacoes);

            $em->persist($execucao);
            $em->flush();

            return $this->json(['ok' => true, 'msg' => 'Medicação confirmada com sucesso!']);

        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/internacao/{id}/evento/{eventoId}/executar", name="clinica_internacao_evento_executar", methods={"POST"})
     */
    public function executarEvento(
        int                    $id,
        int                    $eventoId,
        Request                $request,
        EntityManagerInterface $em,
        InternacaoRepository   $internacaoRepo
    ): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $horaAplicacao = $request->get('hora_aplicacao');
        $veterinarioId = (int)$request->get('veterinario_id', 1);
        $anotacoes = $request->get('anotacoes', 'Confirmado via sistema');

        try {
            // Busca o veterinário
            $veterinario = $em->getRepository(\App\Entity\Veterinario::class)->find($veterinarioId);
            if (!$veterinario) {
                // Se não encontrar, usa o primeiro veterinário disponível
                $veterinario = $em->getRepository(\App\Entity\Veterinario::class)->findOneBy([]);
            }

            // Cria entidade de execução
            $execucao = new InternacaoExecucao();
            $execucao->setInternacaoId($id);
            $execucao->setPrescricaoId($eventoId);
            if ($veterinario) {
                $execucao->setVeterinario($veterinario);
            }
            $execucao->setDataExecucao(new \DateTime($horaAplicacao ?: 'now'));
            $execucao->setStatus('confirmado');
            $execucao->setAnotacoes($anotacoes);

            $em->persist($execucao);
            $em->flush();

            return $this->json(['ok' => true, 'msg' => 'Medicação confirmada com sucesso!']);

        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/internacao/medicamento/novo", name="clinica_internacao_medicamento_novo", methods={"POST"})
     */
    public function novoMedicamentoViaInternacao(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $nome = trim((string)$request->get('nome'));
        $via = trim((string)$request->get('via'));
        $concentracao = trim((string)$request->get('concentracao'));

        if (empty($nome)) {
            return $this->json(['ok' => false, 'msg' => 'O nome do medicamento é obrigatório.']);
        }

        try {
            $medicamento = new Medicamento();
            $medicamento->setNome($nome);
            $medicamento->setVia($via);
            $medicamento->setConcentracao($concentracao);

            $em->persist($medicamento);
            $em->flush();
            return $this->json(['ok' => true, 'msg' => 'Medicamento cadastrado com sucesso!']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro ao salvar o medicamento: ' . $e->getMessage()]);
        }

    }
}
