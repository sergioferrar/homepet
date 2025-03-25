<?php

namespace App\Controller;

use App\Entity\HospedagemCaes;
use App\Repository\HospedagemCaesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/hospedagem")
 */
class HospedagemController extends AbstractController
{
    private $repo;

    public function __construct(HospedagemCaesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @Route("/agendar", name="hospedagem_agendar", methods={"GET", "POST"})
     */
    public function agendar(Request $req): Response
    {
        $baseId = $req->getSession()->get('userId');

        if ($req->isMethod('POST')) {
        $hospedagem = new HospedagemCaes();
        $hospedagem->setClienteId($req->request->get('cliente_id'));
        $hospedagem->setPetId($req->request->get('pet_id'));
        $hospedagem->setDataEntrada(new \DateTime($req->request->get('dataEntrada')));
        $hospedagem->setDataSaida(new \DateTime($req->request->get('dataSaida')));

        $valorInformado = (float) $req->request->get('valor'); // valor por dia
        $dias = $hospedagem->getDataSaida()->diff($hospedagem->getDataEntrada())->days + 1;
        $valorTotal = $dias * $valorInformado;

        $hospedagem->setValor($valorTotal);
        $hospedagem->setObservacoes($req->request->get('observacoes'));

        $this->repo->insert($baseId, $hospedagem);
        $this->repo->registrarFinanceiro($baseId, $hospedagem);


            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/agendar.html.twig', [
            'clientes' => $this->repo->getClientes($baseId),
            'pets' => $this->repo->getPets($baseId),
        ]);
    }

    /**
     * @Route("/listar", name="hospedagem_listar", methods={"GET"})
     */
    public function listar(Request $req): Response
    {
        $baseId = $req->getSession()->get('userId');
        return $this->render('hospedagem/listar.html.twig', [
            'dados' => $this->repo->findAll($baseId)
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="hospedagem_deletar", methods={"POST"})
     */
    public function deletar(Request $req, int $id): Response
    {
        $baseId = $req->getSession()->get('userId');

        if (!$this->repo->findById($baseId, $id)) {
            throw $this->createNotFoundException('Hospedagem não encontrada');
        }

        $this->repo->delete($baseId, $id);
        return $this->redirectToRoute('hospedagem_listar');
    }
}
