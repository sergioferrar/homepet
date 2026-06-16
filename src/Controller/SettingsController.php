<?php

namespace App\Controller;

use App\Entity\Config;
use Symfony\Component\HttpFoundation\Request;
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
        // Usa o usuário autenticado pelo Symfony — não depende de userId na sessão.
        $usuarioLogado = $this->security->getUser();

        if (!$usuarioLogado || !$usuarioLogado->getPetshopId()) {
            // Superadmin sem estabelecimento impersonado não tem acesso a esta tela.
            $this->addFlash('warning',
                'As configurações de estabelecimento não estão disponíveis no painel Super Admin. '
                . 'Acesse um estabelecimento primeiro ou use Configurações Globais.'
            );
            return $this->redirectToRoute('superadmin_dashboard');
        }

        $petshopId = $usuarioLogado->getPetshopId();

        $dadosEstabelecimentoLogado = $this->getRepositorio(\App\Entity\Estabelecimento::class)
            ->findOneBy(['id' => $petshopId]);
        $dadosPlanos = $this->getRepositorio(\App\Entity\Plano::class)->findAll();

        return $this->render('settings/index.html.twig', [
            'estabelecimento' => $dadosEstabelecimentoLogado,
            'planoLogado'     => $dadosEstabelecimentoLogado,
            'planos'          => $dadosPlanos,
            'gateway'         => $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $petshopId, 'tipo' => 'gateway_payment']),
            'mailer'          => $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $petshopId, 'tipo' => 'mailer']),
        ]);
    }

    /**
     * @Route("settings/payment", name="setting_payment_estabelecimento")
     */
    public function setPayments(Request $request): Response
    {
        $usuarioLogado = $this->security->getUser();

        if (!$usuarioLogado || !$usuarioLogado->getPetshopId()) {
            return $this->json($this->message('Usuário não autenticado ou sem estabelecimento vinculado.', 'error'));
        }

        $estabelecimentoId = $usuarioLogado->getPetshopId();

        if ($request->isMethod('POST')) {
            $buscaConfigEID = $this->getRepositorio(\App\Entity\Config::class)
                ->findOneBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'gateway_payment']);

            if (!$buscaConfigEID) {
                $campos = [
                    'gateway_chave'           => $request->get('gateway_chave'),
                    'gateway_payment_env'     => $request->get('gateway_payment_env'),
                    'gateway_payment_token'   => $request->get('gateway_payment_token'),
                    'gateway_payment_email'   => $request->get('gateway_payment_email'),
                    'gateway_payment_key_secret' => $request->get('gateway_payment_key_secret'),
                    'gateway_payment_key_public' => $request->get('gateway_payment_key_public'),
                ];

                foreach ($campos as $chave => $valor) {
                    $config = new \App\Entity\Config();
                    $config->setEstabelecimentoId($estabelecimentoId);
                    $config->setChave($chave);
                    $config->setValor($valor ?? '');
                    $config->setTipo('gateway_payment');
                    $config->setObservacao('');
                    $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
                }

                return $this->json($this->message('Configurações de pagamento salvas com sucesso!', 'success'));
            }

            // Atualiza registros existentes
            $todos = $this->getRepositorio(\App\Entity\Config::class)
                ->findBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'gateway_payment']);

            foreach ($todos as $config) {
                $valor = $request->get($config->getChave());
                if ($valor !== null) {
                    $config->setValor($valor);
                    $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
                }
            }

            return $this->json($this->message('Configurações de pagamento atualizadas com sucesso!', 'success'));
        }

        return $this->json($this->message('Este método só pode ser acessado via POST', 'alert'));
    }

    /**
     * @Route("settings/mailer", name="setting_mailer_estabelecimento")
     */
    public function setMail(Request $request): Response
    {
        $usuarioLogado = $this->security->getUser();

        if (!$usuarioLogado || !$usuarioLogado->getPetshopId()) {
            return $this->json($this->message('Usuário não autenticado ou sem estabelecimento vinculado.', 'error'));
        }

        $estabelecimentoId = $usuarioLogado->getPetshopId();

        if ($request->isMethod('POST')) {
            $buscaConfigEID = $this->getRepositorio(\App\Entity\Config::class)
                ->findOneBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'mailer']);

            if (!$buscaConfigEID) {
                $campos = [
                    'mailer_server'    => $request->get('mailer_server'),
                    'mailer_port'      => $request->get('mailer_port'),
                    'mailer_crypt'     => $request->get('mailer_crypt'),
                    'mailer_user'      => $request->get('mailer_user'),
                    'mailer_paswd'     => $request->get('mailer_paswd'),
                    'mailer_remetente' => $request->get('mailer_remetente'),
                ];

                foreach ($campos as $chave => $valor) {
                    $config = new \App\Entity\Config();
                    $config->setEstabelecimentoId($estabelecimentoId);
                    $config->setChave($chave);
                    $config->setValor($valor ?? '');
                    $config->setTipo('mailer');
                    $config->setObservacao('Configuração de envio de e-mails');
                    $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
                }

                return $this->json($this->message('Configurações de e-mail salvas com sucesso!', 'success'));
            }

            // Atualiza registros existentes
            $todos = $this->getRepositorio(\App\Entity\Config::class)
                ->findBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'mailer']);

            foreach ($todos as $config) {
                $valor = $request->get($config->getChave());
                if ($valor !== null) {
                    $config->setValor($valor);
                    $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
                }
            }

            return $this->json($this->message('Configurações de e-mail atualizadas com sucesso!', 'success'));
        }

        return $this->json($this->message('Este método só pode ser acessado via POST', 'alert'));
    }
}
