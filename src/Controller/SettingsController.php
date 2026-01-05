<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/")
 */
class SettingsController extends DefaultController
{
    /**
     * @Route("settings", name="app_settings")
     */
    public function index(): Response
    {
        $data = [];

        $dadosUsuarioLogado = $this->getRepositorio(\App\Entity\Usuario::class)->findOneBy(['id' => $this->session->get('userId')]);
        $dadosEstabelecimentoLogado = $this->getRepositorio(\App\Entity\Estabelecimento::class)->findOneBy(['id' => $dadosUsuarioLogado->getId()]);
        $dadosPlanoLogado = $this->getRepositorio(\App\Entity\Plano::class)->findOneBy(['id' => $dadosEstabelecimentoLogado->getPlanoId()]);
        $dadosPlanos = $this->getRepositorio(\App\Entity\Plano::class)->findAll();
        $data['estabelecimento'] = $dadosEstabelecimentoLogado;
        $data['planoLogado'] = $dadosEstabelecimentoLogado;
        $data['planos'] = $dadosPlanos;
        // dd($data);

        return $this->render('settings/index.html.twig', $data);
    }
}
