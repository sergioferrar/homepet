<?php

namespace App\Controller;

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
        $data = [];

        $dadosUsuarioLogado = $this->getRepositorio(\App\Entity\Usuario::class)->findOneBy(['id' => $this->session->get('userId')]);
        $dadosEstabelecimentoLogado = $this->getRepositorio(\App\Entity\Estabelecimento::class)->findOneBy(['id' => $dadosUsuarioLogado->getPetshopId()]);
        
        $dadosPlanoLogado = $this->getRepositorio(\App\Entity\Plano::class)->findOneBy(['id' => $dadosEstabelecimentoLogado->getPlanoId()]);
        $dadosPlanos = $this->getRepositorio(\App\Entity\Plano::class)->findAll();
        $data['estabelecimento'] = $dadosEstabelecimentoLogado;
        $data['planoLogado'] = $dadosEstabelecimentoLogado;
        $data['planos'] = $dadosPlanos;
        $data['gateway'] = $this->getRepositorio(\App\Entity\Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'gateway_payment']);
        $data['mailer'] = $this->getRepositorio(\App\Entity\Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'mailer']);
        // dd($data);

        return $this->render('settings/index.html.twig', $data);
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
