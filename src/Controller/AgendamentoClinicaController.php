<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgendamentoClinicaController extends AbstractController
{
    /**
     * @Route("/agendamento/clinica", name="app_agendamento_clinica")
     */
    public function index(): Response
    {
        return $this->render('agendamento_clinica/index.html.twig', [
            'controller_name' => 'AgendamentoClinicaController',
        ]);
    }
}
