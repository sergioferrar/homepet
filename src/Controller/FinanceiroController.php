<?php
namespace App\Controller;

use App\Entity\Financeiro;
use App\Repository\FinanceiroRepository;
use App\Repository\PetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/financeiro")
 */
class FinanceiroController extends AbstractController
{
    private $financeiroRepository;
    private $petRepository;

    public function __construct(FinanceiroRepository $financeiroRepository, PetRepository $petRepository)
    {
        $this->financeiroRepository = $financeiroRepository;
        $this->petRepository = $petRepository;
    }

    /**
     * @Route("/", name="financeiro_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $financeiros = $this->financeiroRepository->findByDate($data);

        return $this->render('financeiro/index.html.twig', [
            'financeiros' => $financeiros,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/novo", name="financeiro_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $financeiro = new Financeiro();
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float)$request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int)$request->request->get('pet_id') : null);

            $this->financeiroRepository->save($financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/novo.html.twig', [
            'pets' => $this->petRepository->findAll()
        ]);
    }

    /**
     * @Route("/editar/{id}", name="financeiro_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $financeiro = $this->financeiroRepository->find($id);

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float)$request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPet_Id($request->request->get('pet_id') !== '' ? (int)$request->request->get('pet_id') : null);

            $this->financeiroRepository->update($financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/editar.html.twig', [
            'financeiro' => $financeiro,
            'pets' => $this->petRepository->findAll()
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="financeiro_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $financeiro = $this->financeiroRepository->find($id);

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        $this->financeiroRepository->delete($id);
        return $this->redirectToRoute('financeiro_index');
    }

    /**
     * @Route("/relatorio", name="financeiro_relatorio", methods={"GET"})
     */
    public function relatorio(): Response
    {
        $relatorio = $this->financeiroRepository->getRelatorio();
        return $this->render('financeiro/relatorio.html.twig', ['relatorio' => $relatorio]);
    }
}
