<?php

namespace App\Controller;

use App\Entity\Vacina;
use App\Repository\VacinaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Route("/vacina")
 */
class VacinaController extends AbstractController
{
    private $repo;
    private $session;

    public function __construct(VacinaRepository $repo, RequestStack $requestStack)
    {
        $this->repo = $repo;
        $this->session = $requestStack->getSession();
    }

    /**
     * @Route("/", name="vacina_index", methods={"GET"})
     */
    public function index(): Response
    {
        $baseId = $this->session->get('userId');
        $vacinas = $this->repo->findAll($baseId);

        return $this->render('vacina/index.html.twig', [
            'vacinas' => $vacinas
        ]);
    }

    /**
     * @Route("/nova", name="vacina_nova", methods={"GET", "POST"})
     */
    public function nova(Request $request): Response
    {
        $baseId = $this->session->get('userId');
        $sugestoes = $this->repo->getVacinasSugeridas();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $vacina = new Vacina();
            $vacina->setPetId((int)$data['pet_id']);
            $vacina->setTipo($data['tipo']);
            $vacina->setDataAplicacao(new \DateTime($data['data_aplicacao']));
            $vacina->setDataValidade(new \DateTime($data['data_validade']));
            $vacina->setLote($data['lote']);

            $this->repo->insert($baseId, $vacina);
            return $this->redirectToRoute('vacina_index');
        }

        return $this->render('vacina/nova.html.twig', [
            'pets' => $this->repo->findAllPets($baseId),
            'vacinasCao' => $sugestoes['Cães'],
            'vacinasGato' => $sugestoes['Gatos']
        ]);
    }

    /**
     * @Route("/editar/{id}", name="vacina_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $vacina = new Vacina();
            $vacina->setPetId((int)$data['pet_id']);
            $vacina->setTipo($data['tipo']);
            $vacina->setDataAplicacao(new \DateTime($data['data_aplicacao']));
            $vacina->setDataValidade(new \DateTime($data['data_validade']));
            $vacina->setLote($data['lote']);

            $this->repo->update($baseId, $id, $vacina);
            return $this->redirectToRoute('vacina_index');
        }

        $vacina = $this->repo->find($baseId, $id);
        $sugestoes = $this->repo->getVacinasSugeridas();

        return $this->render('vacina/editar.html.twig', [
            'vacina' => $vacina,
            'pets' => $this->repo->findAllPets($baseId),
            'vacinasCao' => $sugestoes['Cães'],
            'vacinasGato' => $sugestoes['Gatos']
        ]);
    }

    /**
     * @Route("/remover/{id}", name="vacina_remover", methods={"GET"})
     */
    public function remover(int $id): RedirectResponse
    {
        $baseId = $this->session->get('userId');
        $this->repo->delete($baseId, $id);
        return $this->redirectToRoute('vacina_index');
    }
}
