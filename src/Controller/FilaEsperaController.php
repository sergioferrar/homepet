<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica/fila-espera")
 */
class FilaEsperaController extends DefaultController
{
    /**
     * @Route("/", name="fila_espera")
     */
    public function index(): Response
    {
        $this->switchDB();
        $fila = $this->getRepositorio('App\Entity\FilaEspera')->findAll();

        return $this->render('clinica/fila_espera/index.html.twig', [
            'fila' => $fila,
        ]);
    }
}
