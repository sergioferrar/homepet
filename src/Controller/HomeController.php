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
     * @Route("/landing/getstarted", name="landing_home")
     */
    public function landing(Request $request): Response
    {
        $data = [];

        $planos = $this->getRepositorio(\App\Entity\Plano::class)->listaPlanosHome();
        $data['planos'] = $planos;
        $data['modulos'] = $this->modulosSistema;

        return $this->render('home/landing.html.twig', $data);
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $request): Response
    {
        
        $this->switchDB();
        $agendamento = $this->getRepositorio(Financeiro::class)->totalAgendamento($this->estabelecimentoId);
        $agendamentoDia = $this->getRepositorio(Financeiro::class)->totalAgendamentoDia($this->estabelecimentoId);
        $animais = $this->getRepositorio(Financeiro::class)->totalAnimais($this->estabelecimentoId);
        $lucrototal = $this->getRepositorio(Financeiro::class)->totalLucroPorMes($this->estabelecimentoId);
        $valores = $this->getRepositorio(Financeiro::class)->lucroDiario($this->estabelecimentoId);
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
