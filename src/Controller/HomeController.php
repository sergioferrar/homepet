<?php

namespace App\Controller;

use App\Entity\Financeiro;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends DefaultController
{

    /**
     * @Route("/dashboard", name="home")
     */
    public function index(Request $request): Response
    {
        // Redireciona usuários não autenticados diretamente para a tela de login
        // Isso evita que a aplicação lance uma exceção de autenticação em produção
        // quando alguém acessa apenas o domínio raiz.
        if (!$this->security->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Redireciona Super Admin para dashboard específico
        if ($this->isGranted('ROLE_SUPER_ADMIN') && $request->getSession()->get('estabelecimentoId') == null) {
            return $this->redirectToRoute('superadmin_dashboard');
        }

        $this->switchDB();
        // dd($this);
        $relatorioHome = $this->getRepositorio(Financeiro::class)->relatorioHome($this->getIdBase());
        $valores = $this->getRepositorio(Financeiro::class)->lucroDiario($this->getIdBase());



        // Verificar se a assinatura está próxima de expirar
        $this->restauraLoginDB();
        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($this->getIdBase());

        $this->switchDB();
        $diasParaExpirar = null;
        $assinaturaExpirada = false;
        $avisoExpiracao = false;

        if ($estabelecimento) {
            $hoje = new \DateTime();
            $dataFim = $estabelecimento->getDataPlanoFim();
            $diff = $hoje->diff($dataFim);
            $diasParaExpirar = $diff->days;

            // Se já expirou
            if ($dataFim < $hoje) {
                $assinaturaExpirada = true;
            }
            // Se faltam 15 dias ou menos
            elseif ($diasParaExpirar <= 15) {
                $avisoExpiracao = true;
            }
        }

        $data = [];
        $data['agendamento'] = $relatorioHome['totalAgendamento'];
        $data['agendamentoHoje'] = $relatorioHome['totalAgendamento'];
        $data['lucrototal'] = number_format($relatorioHome['lucroTotal'], 2, ',', '.'); //;
        $data['animais'] = $relatorioHome['totalAnimal'];
        $data['dias_para_expirar'] = $diasParaExpirar;
        $data['assinatura_expirada'] = $assinaturaExpirada;
        $data['aviso_expiracao'] = $avisoExpiracao;

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
