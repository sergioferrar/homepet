<?php

namespace App\Controller;

use App\Entity\Exame;
use App\Repository\ExameRepository;
use App\Service\DatabaseBkp;
use App\Service\TempDirManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica/exame")
 */
class ExameController extends DefaultController
{
    private $repo;

    public function __construct(
        Security $security,
        ManagerRegistry $managerRegistry,
        RequestStack $requestStack,
        TempDirManager $tempDirManager,
        DatabaseBkp $databaseBkp,
        ExameRepository $repo
    ) {
        parent::__construct($security, $managerRegistry, $requestStack, $tempDirManager, $databaseBkp);
        $this->repo = $repo;
    }

    /**
     * @Route("/", name="exame_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            /** @var UploadedFile $arquivo */
            $arquivo = $request->files->get('arquivo');
            $nomeArquivo = null;

            if ($arquivo) {
                $nomeArquivo = uniqid() . '.' . $arquivo->guessExtension();
                $arquivo->move($this->getParameter('kernel.project_dir') . '/public/uploads/exames', $nomeArquivo);
            }

            $exame = new Exame();
            $exame->setPetId((int) $data['pet_id']);
            $exame->setAgendamentoId((int) $data['agendamento_id']);
            $exame->setDescricao($data['descricao']);
            $exame->setArquivo($nomeArquivo);
            $exame->setCriadoEm(new \DateTime());

            $this->repo->insert($baseId, $exame);

            return $this->redirectToRoute('exame_index');
        }

        return $this->render('exame/index.html.twig', [
            'exames' => $this->repo->findAll($baseId),
            'pets' => $this->repo->findAllPets($baseId),
            'agendamentos' => $this->repo->findAllAgendamentos($baseId),
        ]);
    }

    /**
     * @Route("/remover/{id}", name="exame_remover", methods={"GET"})
     */
    public function remover(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $this->repo->delete($baseId, $id);
        return $this->redirectToRoute('exame_index');
    }
}