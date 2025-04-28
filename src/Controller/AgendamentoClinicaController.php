<?php

namespace App\Controller;

use App\Entity\AgendamentoClinica;
use App\Repository\AgendamentoClinicaRepository;
use App\Service\DatabaseBkp;
use App\Service\TempDirManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica")
 */
class AgendamentoClinicaController extends DefaultController
{
    private $repo;

    public function __construct(
        Security $security,
        ManagerRegistry $managerRegistry,
        RequestStack $requestStack,
        TempDirManager $tempDirManager,
        DatabaseBkp $databaseBkp,
        AgendamentoClinicaRepository $repo
    ) {
        parent::__construct($security, $managerRegistry, $requestStack, $tempDirManager, $databaseBkp);
        $this->repo = $repo;
    }

    /**
     * @Route("/", name="clinica_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();

        if ($request->isMethod('POST')) {
            $form = $request->request->all();

            $agendamento = new AgendamentoClinica();
            $agendamento->setData(new \DateTime($form['data']));
            $agendamento->setHora(new \DateTime($form['hora']));
            $agendamento->setProcedimento($form['procedimento']);
            $agendamento->setPetId((int)$form['pet_id']);
            $agendamento->setDonoId((int)$form['dono_id']);
            $agendamento->setStatus('aguardando');

            $this->repo->save($baseId, $agendamento);
            return $this->redirectToRoute('clinica_index');
        }

        return $this->render('clinica/index.html.twig', [
            'agendamentos' => $this->repo->findByDate($baseId, $data),
            'clientes'     => $this->repo->findAllClientes($baseId),
            'pets'         => $this->repo->findAllPets($baseId),
            'atendimentos_hoje'         => '',
            'internacoes_ativas'         => '',
            'data'         => $data,
        ]);
    }

    /**
     * @Route("/novo", name="clinica_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $agendamento = new AgendamentoClinica();
            $agendamento->setData(new \DateTime($data['data']));
            $agendamento->setHora(new \DateTime($data['hora']));
            $agendamento->setProcedimento($data['procedimento']);
            $agendamento->setPetId((int)$data['pet_id']);
            $agendamento->setDonoId((int)$data['dono_id']);
            $agendamento->setStatus('aguardando');

            $this->repo->save($baseId, $agendamento);
            return $this->redirectToRoute('clinica_index');
        }

        return $this->render('clinica/novo.html.twig', [
            'clientes' => $this->repo->findAllClientes($baseId),
            'pets'     => $this->repo->findAllPets($baseId),
        ]);
    }
}
