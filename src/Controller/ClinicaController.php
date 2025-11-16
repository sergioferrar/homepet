<?php

namespace App\Controller;

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


/**
 * @Route("/clinica")
 */
class ClinicaController extends DefaultController
{


    /**
     * @Route("/pet/{petId}/fotos/nova", name="clinica_novas_fotos", methods={"GET", "POST"})
     */
    public function novasFotos(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para fotos
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Fotos']);
    }

    /**
     * @Route("/pet/{petId}/vacina/nova", name="clinica_nova_vacina", methods={"GET", "POST"})
     */
    // public function novaVacina(Request $request, int $petId): Response
    // {
    //     $this->switchDB();
    //     $baseId = $this->getIdBase();
    //     $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
    //     // Lógica para vacinas
    //     return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Vacina']);
    // }

    // --- ROTAS ORIGINAIS (MANTIDAS E FUNCIONAIS) ---


    // /**
    //  * @Route("/receita", name="clinica_receita", methods={"GET"})
    //  */
    // public function receita(): Response
    // {
    //     $this->switchDB();
    //     return $this->render('clinica/receita.html.twig');
    // }

    // /**
    //  * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
    //  */
    // public function gerarReceitaPdf(Request $request, PdfService $pdfService): Response
    // {
    //     $this->switchDB();

    //     return $pdfService->gerarPdf(
    //         'clinica/receita_pdf_backend.html.twig',
    //         [
    //             'cabecalho' => $request->get('cabecalho', ''),
    //             'conteudo'  => $request->get('conteudo', ''),
    //             'rodape'    => $request->get('rodape', '')
    //         ],
    //         'receita-medica.pdf'
    //     );
    // }


    /**
     * @Route("/api/pets/{clienteId}", name="clinica_api_pets", methods={"GET"})
     */
    public function apiPetsPorCliente(int $clienteId): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $pets = $this->getRepositorio(Pet::class)->buscarPetsPorCliente($baseId, $clienteId);

        return $this->json(array_map(fn($pet) => ['id' => $pet['id'], 'nome' => $pet['nome']], $pets));
    }

    /**
     * @Route("/notificacoes", name="clinica_notificacoes", methods={"GET"})
     */
    public function notificacoes(EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        try {
            $agora = new \DateTime();
            $notificacoes = [];

            // Buscar medicamentos pendentes nas próximas 2 horas
            $eventos = $em->getRepository(\App\Entity\InternacaoEvento::class)
                ->createQueryBuilder('e')
                ->where('e.tipo = :tipo')
                ->andWhere('e.dataHora BETWEEN :agora AND :limite')
                ->setParameter('tipo', 'prescricao')
                ->setParameter('agora', $agora)
                ->setParameter('limite', (clone $agora)->modify('+2 hours'))
                ->orderBy('e.dataHora', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($eventos as $evento) {
                // Verifica se já foi executado
                $execucao = $em->getRepository(\App\Entity\InternacaoExecucao::class)
                    ->findOneBy(['prescricaoId' => $evento->getId(), 'status' => 'confirmado']);

                if (!$execucao) {
                    $diff = $agora->diff($evento->getDataHora());
                    $minutos = ($diff->h * 60) + $diff->i;

                    if ($diff->invert) {
                        // Atrasado
                        $icon = 'bi-exclamation-triangle-fill';
                        $color = 'text-danger';
                        $tempo = 'Atrasado há ' . $minutos . ' min';
                    } else if ($minutos <= 30) {
                        // Próximo (30 min)
                        $icon = 'bi-clock-fill';
                        $color = 'text-warning';
                        $tempo = 'Em ' . $minutos . ' minutos';
                    } else {
                        // Futuro próximo
                        $icon = 'bi-clock';
                        $color = 'text-info';
                        $tempo = 'Em ' . $minutos . ' minutos';
                    }

                    $notificacoes[] = [
                        'titulo' => $evento->getTitulo(),
                        'mensagem' => $evento->getDescricao() ?? 'Medicamento pendente',
                        'tempo' => $tempo,
                        'icon' => $icon,
                        'color' => $color,
                        'data' => $evento->getDataHora()->format('Y-m-d H:i:s')
                    ];
                }
            }

            return new JsonResponse(['notificacoes' => $notificacoes]);

        } catch (\Exception $e) {
            return new JsonResponse(['notificacoes' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/medicamentos", name="clinica_medicamentos", methods={"GET","POST"})
     */
    public function medicamentos(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Cadastro de novo medicamento
        if ($request->isMethod('POST')) {
            $medicamento = new Medicamento();
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->persist($medicamento);
            $em->flush();

            $this->addFlash('success', 'Medicamento cadastrado com sucesso!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        // Listagem de medicamentos
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();

        return $this->render('clinica/medicamentos.html.twig', [
            'medicamentos' => $medicamentos,
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/editar", name="clinica_medicamento_editar", methods={"GET","POST"})
     */
    public function editarMedicamento(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if (!$medicamento) {
            throw $this->createNotFoundException('Medicamento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->flush();

            $this->addFlash('success', 'Medicamento atualizado!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        return $this->render('clinica/medicamento_editar.html.twig', [
            'medicamento' => $medicamento,
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/excluir", name="clinica_medicamento_excluir", methods={"POST"})
     */
    public function excluirMedicamento(int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if ($medicamento) {
            $em->remove($medicamento);
            $em->flush();
            $this->addFlash('success', 'Medicamento excluído.');
        }

        return $this->redirectToRoute('clinica_medicamentos');
    }
    /**
     * @Route("/calendario/geral", name="clinica_calendario_geral", methods={"GET"})
     */
    public function calendarioGeral(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $prescricoes = $em->getRepository(InternacaoPrescricao::class)->findAll();

        $eventos = [];
        foreach ($prescricoes as $p) {
            if (!$p->getDataHora()) {
                continue;
            }

            $primeira = $p->getDataHora();
            $freq = (int)$p->getFrequenciaHoras();
            $dias = (int)$p->getDuracaoDias();
            if ($freq <= 0 || $dias <= 0) {
                continue;
            }

            $numDoses = ($dias * 24) / $freq;

            for ($i = 0; $i < $numDoses; $i++) {
                $horas = $i * $freq;
                $doseTime = (clone $primeira)->modify("+{$horas} hours");

                $internacao = $em->getRepository(Internacao::class)->find($p->getInternacaoId());
                $petNome = 'Pet';
                if ($internacao) {
                    $pet = $em->getRepository(Pet::class)->find($internacao->getPetId());
                    $petNome = $pet ? $pet->getNome() : 'Pet';
                }

                $eventos[] = [
                    'title' => $p->getMedicamento()->getNome() . " - " . $p->getDose(),
                    'start' => $doseTime->format(\DateTime::ATOM),
                    'end' => (clone $doseTime)->modify('+30 minutes')->format(\DateTime::ATOM),
                    'color' => '#0d6efd',
                    'prescricao_id' => $p->getId(),
                ];

            }

        }

        return $this->render('clinica/calendario_geral.html.twig', [
            'eventos' => $eventos,
        ]);
    }

}
