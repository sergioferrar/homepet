<?php

namespace App\Controller;

use App\Entity\Agendamento;
use App\Entity\Financeiro;
use App\Repository\AgendamentoRepository;
use App\Repository\FinanceiroRepository;
use App\Repository\ServicoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/agendamento")
 */
class AgendamentoController extends DefaultController
{
    private $agendamentoRepository;
    private $financeiroRepository;
    private $servicoRepository;

    public function __construct(AgendamentoRepository $agendamentoRepository, FinanceiroRepository $financeiroRepository, ServicoRepository $servicoRepository)
    {
        $this->agendamentoRepository = $agendamentoRepository;
        $this->financeiroRepository = $financeiroRepository;
        $this->servicoRepository = $servicoRepository;
    }

    /**
     * @Route("/", name="agendamento_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $agendamentos = $this->agendamentoRepository->findByDate($data);
        $totalAgendamentos = $this->agendamentoRepository->contarAgendamentosPorData($data);

        return $this->render('agendamento/index.html.twig', [
            'agendamentos' => $agendamentos,
            'data' => $data,
            'totalAgendamentos' => $totalAgendamentos,
        ]);
    }

    /**
     * @Route("/novo", name="agendamento_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->get('data');
            $petId = $request->request->get('pet_id');
            $servicoId = $request->request->get('servico_id');
            $recorrencia = $request->request->get('recorrencia');

            $agendamento = new Agendamento();
            $agendamento->setData(new \DateTime($data));
            $agendamento->setPet_Id($petId);
            $agendamento->setServico_Id($servicoId);
            $agendamento->setConcluido(false);

            $this->agendamentoRepository->save($agendamento);

            if ($recorrencia === 'semanal' || $recorrencia === 'quinzenal') {
                $intervalo = $recorrencia === 'semanal' ? 'P1W' : 'P2W';
                for ($i = 1; $i < 4; $i++) {
                    $novaData = (new \DateTime($data))->add(new \DateInterval($intervalo));
                    $agendamentoRecorrente = new Agendamento();
                    $agendamentoRecorrente->setData($novaData);
                    $agendamentoRecorrente->setPet_Id($petId);
                    $agendamentoRecorrente->setServico_Id($servicoId);
                    $agendamentoRecorrente->setConcluido(false);
                    $this->agendamentoRepository->save($agendamentoRecorrente);
                    $data = $novaData->format('Y-m-d');
                }
            }

            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
        }

        return $this->render('agendamento/novo.html.twig', [
            'pets' => $this->agendamentoRepository->findAllPets(),
            'servicos' => $this->agendamentoRepository->findAllServicos(),
        ]);
    }

    /**
     * @Route("/editar/{id}", name="agendamento_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->get('data');
            $petId = $request->request->get('pet_id');
            $servicoId = $request->request->get('servico_id');
            $concluido = $request->request->get('concluido') ? true : false;

            $agendamento->setData(new \DateTime($data));
            $agendamento->setPet_Id($petId);
            $agendamento->setServico_Id($servicoId);
            $agendamento->setConcluido($concluido);

            $this->agendamentoRepository->update($agendamento);
            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
        }

        return $this->render('agendamento/editar.html.twig', [
            'agendamento' => $agendamento,
            'pets' => $this->agendamentoRepository->findAllPets(),
            'servicos' => $this->agendamentoRepository->findAllServicos(),
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="agendamento_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $this->agendamentoRepository->delete($id);
        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/concluir/{id}", name="agendamento_concluir", methods={"POST"})
     */
    public function concluir(int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $servico = $this->servicoRepository->find($agendamento->getServico_Id());

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        $financeiro = new Financeiro();
        $financeiro->setDescricao('Serviço para o pet: ' . $agendamento->getPet_Id());
        $financeiro->setValor($servico->getValor());
        $financeiro->setData(new \DateTime());
        $financeiro->setPet_Id($agendamento->getPet_Id());

        $this->financeiroRepository->save($financeiro);

        $agendamento->setConcluido(true);
        $this->agendamentoRepository->update($agendamento);

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/agendamento/pronto/{id}", name="agendamento_pronto")
     */
    public function marcarComoPronto($id, AgendamentoRepository $agendamentoRepository): Response
    {
        $agendamentoRepository->marcarComoPronto($id);
        return $this->redirectToRoute('lista_agendamentos');
    }

    /**
     * @Route("/agendamentos", name="lista_agendamentos")
     */
    public function listar(AgendamentoRepository $agendamentoRepository): Response
    {
        $agendamentos = $agendamentoRepository->findAll();
        return $this->render('agendamento/lista.html.twig', ['agendamentos' => $agendamentos]);
    }

}
