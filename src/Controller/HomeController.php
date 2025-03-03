<?php

namespace App\Controller;

use App\Entity\Financeiro;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends DefaultController
{

    /**
     * @Route("/landing", name="landing_home")
     */
    public function landing(Request $request): Response
    {
        $data = [];

        return $this->render('home/landing.html.twig', $data);
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $request): Response
    {
        $agendamento = $this->getRepositorio(Financeiro::class)->totalAgendamento($request->getSession()->get('userId'));
        $agendamentoDia = $this->getRepositorio(Financeiro::class)->totalAgendamentoDia($request->getSession()->get('userId'));
        $animais = $this->getRepositorio(Financeiro::class)->totalAnimais($request->getSession()->get('userId'));
        $lucrototal = $this->getRepositorio(Financeiro::class)->totalLucro($request->getSession()->get('userId'));
        $valores = $this->getRepositorio(Financeiro::class)->lucroDiario($request->getSession()->get('userId'));
//        dd($agendamento);
        $data = [];
        $data['agendamento'] = $agendamento['totalAgendamento'];
        $data['agendamentoHoje'] = $agendamentoDia['totalAgendamento'];
        $data['lucrototal'] = number_format($lucrototal['lucroTotal'], 2, ',', '.');//;
        $data['animais'] = $animais['totalAnimal'];

        $values = [];
        $dates = [];
        foreach ($valores as $valor) {
            $values[] = $valor['valor'];
            $dates[] = $valor['data'];
        }

        $data['valores'] = json_encode($values);
        $data['datas'] = json_encode($dates);

        return $this->render('home/index.html.twig', $data);
    }
}
