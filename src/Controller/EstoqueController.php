<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica/estoque")
 */
class EstoqueController extends DefaultController
{
    /**
     * @Route("/", name="estoque_index")
     */
    public function index(): Response
    {
        $this->switchDB();
        $itens = $this->getRepositorio('App\Entity\Estoque')->findAll();

        return $this->render('clinica/estoque/index.html.twig', [
            'itens' => $itens,
        ]);
    }
}
