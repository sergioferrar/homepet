<?php

namespace App\Controller;

use App\Entity\Financeiro;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends DefaultController
{

    /**
     * @Route("/", name="home")
     */
    public function index(): Response
    {
        $agendamento = $this->getRepositorio(Financeiro::class)->totalAgendamento();
        $agendamentoDia = $this->getRepositorio(Financeiro::class)->totalAgendamentoDia();
        $animais = $this->getRepositorio(Financeiro::class)->totalAnimais();
        $lucrototal = $this->getRepositorio(Financeiro::class)->totalLucro();
//        dd($agendamento);
        $data = [];
        $data['agendamento'] = $agendamento['totalAgendamento'];
        $data['agendamentoHoje'] = $agendamentoDia['totalAgendamento'];
        $data['lucrototal'] = number_format($lucrototal['lucroTotal'], 2, ',', '.');//;
        $data['animais'] = $animais['totalAnimal'];
        return $this->render('home/index.html.twig',$data);
    }
}
