<?php

namespace App\Controller;

use App\Entity\Servico;
use App\Repository\ServicoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/servico")
 */
class ServicoController extends DefaultController
{
    /**
     * @Route("/", name="servico_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $servicos = $this->getRepositorio(Servico::class)->findAllService($this->session->get('userId'));
        return $this->render('servico/index.html.twig', ['servicos' => $servicos]);
    }

    /**
     * @Route("/novo", name="servico_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $servico = new Servico();
            $servico->setNome($request->request->get('nome'));
            $servico->setDescricao($request->request->get('descricao'));
            $servico->setValor((float)$request->request->get('valor'));

            $this->getRepositorio(Servico::class)->save($this->session->get('userId'), $servico);
            return $this->redirectToRoute('servico_index');
        }

        return $this->render('servico/novo.html.twig');
    }

    /**
     * @Route("/editar/{id}", name="servico_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $servico = $this->getRepositorio(Servico::class)->findService($this->session->get('userId'), $id);

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $servico->setNome($request->request->get('nome'));
            $servico->setDescricao($request->request->get('descricao'));
            $servico->setValor((float)$request->request->get('valor'));

            $this->getRepositorio(Servico::class)->update($this->session->get('userId'), $servico);
            return $this->redirectToRoute('servico_index');
        }

        return $this->render('servico/editar.html.twig', [
            'servico' => $servico
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="servico_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $servico = $this->getRepositorio(Servico::class)->findService($this->session->get('userId'), $id);

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        $this->getRepositorio(Servico::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('servico_index');
    }


}
