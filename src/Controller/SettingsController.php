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
        // pegar o id do estabelecimento para validar configuração
        $estabelecimentoId = $request->getSession()->get('estabelecimentoId');
        $json = [];

        if($request->isMethod('POST')){
            $buscaConfigEID = $this->getRepositorio(\App\Entity\Config::class)->findOneBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'mailer']);
            

            if(!$buscaConfigEID){
                // Cadastra e retorna a mensagem
                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId);//
                $config->setChave('gateway_chave');
                $config->setValor($request->get('gateway_chave'));
                $config->setTipo('gateway_payment'); 
                $config->setObservacao($request->get('gateway_observacao'));
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('gateway_payment_env'); 
                $config->setValor($request->get('gateway_payment_env')); 
                $config->setTipo('gateway_payment'); 
                $config->setObservacao($request->get('gateway_payment_env_observacao')); 
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('gateway_payment_token'); 
                $config->setValor($request->get('gateway_payment_token')); 
                $config->setTipo('gateway_payment'); 
                $config->setObservacao($request->get('gateway_payment_token_observacao')); 
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('gateway_payment_email'); 
                $config->setValor($request->get('gateway_payment_email')); 
                $config->setTipo('gateway_payment'); 
                $config->setObservacao($request->get('gateway_payment_email_observacao')); 
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('gateway_payment_key_secret'); 
                $config->setValor($request->get('gateway_payment_key_secret')); 
                $config->setTipo('gateway_payment'); 
                $config->setObservacao($request->get('gateway_payment_key_secret_observacao'));
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true); 

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId);
                $config->setChave('gateway_payment_key_public');
                $config->setValor($request->get('gateway_payment_key_public'));
                $config->setTipo('gateway_payment');
                $config->setObservacao($request->get('gateway_payment_key_public_observacao'));
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
            }

            
            dd($buscaConfigEID, $request->getSession()->all());
        }

        return $this->json($this->message('Este método só pode ser acessado via POST', 'alert'));
    }



    /**
     * @Route("settings/mailer", name="setting_mailer_estabelecimento")
     */
    public function setMail(Request $request): Response
    {
        // pegar o id do estabelecimento para validar configuração
        $estabelecimentoId = $request->getSession()->get('estabelecimentoId');
        $json = [];

        if($request->isMethod('POST')){
            $buscaConfigEID = $this->getRepositorio(\App\Entity\Config::class)->findOneBy(['estabelecimento_id' => $estabelecimentoId, 'tipo' => 'mailer']);
            

            if(!$buscaConfigEID){
                // Cadastra e retorna a mensagem
                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId);//
                $config->setChave('mailer_server');
                $config->setValor($request->get('mailer_server'));
                $config->setTipo('mailer'); 
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('mailer_port'); 
                $config->setValor($request->get('mailer_port')); 
                $config->setTipo('mailer'); 
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('mailer_crypt'); 
                $config->setValor($request->get('mailer_crypt')); 
                $config->setTipo('mailer'); 
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('mailer_user'); 
                $config->setValor($request->get('mailer_user')); 
                $config->setTipo('mailer'); 
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId); 
                $config->setChave('mailer_paswd'); 
                $config->setValor($request->get('mailer_paswd')); 
                $config->setTipo('mailer'); 
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true); 

                $config = new \App\Entity\Config();
                $config->setEstabelecimentoId($estabelecimentoId);
                $config->setChave('mailer_remetente');
                $config->setValor($request->get('mailer_remetente'));
                $config->setTipo('mailer');
                $config->setObservacao('Este bloco é responsável por gravar o seu host de envio de e-mails');
                $this->getRepositorio(\App\Entity\Config::class)->add($config, true);
            }

            
            dd($buscaConfigEID, $request->getSession()->all());
        }

        return $this->json($this->message('Este método só pode ser acessado via POST', 'alert'));
    }
}
