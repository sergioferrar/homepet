<?php

namespace App\Controller;

use App\Entity\Config;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/")
 */
class SettingsController extends DefaultController
{
    /**
     * ID de estabelecimento reservado para configurações globais do sistema
     * (políticas, termos, tracking). Gravadas no banco homepet_login.
     */
    private const GLOBAL_ESTAB_ID = 0;

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
        $data['gateway'] = $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'gateway_payment']);
        $data['mailer']  = $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'mailer']);

        // ── Configurações LGPD e Tracking (banco login, estab=0) ─────────────
        $configRepo = $this->getRepositorio(Config::class);
        $data['lgpd']    = $this->indexarConfig($configRepo->findBy(['estabelecimento_id' => $this->session->get('userId'), 'tipo' => 'lgpd']));
        $data['tracking'] = $this->indexarConfig($configRepo->findBy(['estabelecimento_id' => $this->session->get('userId'), 'tipo' => 'tracking']));
        
        return $this->render('settings/index.html.twig', $data);
    }

    /**
     * Salva as páginas de LGPD (Política de Privacidade e Termos de Uso)
     * no banco homepet_login com estabelecimento_id = 0 (global do sistema).
     *
     * @Route("settings/lgpd", name="settings_lgpd_salvar", methods={"POST"})
     */
    public function salvarLgpd(Request $request): JsonResponse
    {
        $configRepo = $this->getRepositorio(Config::class);

        $campos = [
            'politica_privacidade' => 'Texto da Política de Privacidade — LGPD',
            'termos_uso'           => 'Texto dos Termos de Uso',
            'dpo_nome'             => 'Nome do Encarregado de Dados (DPO)',
            'dpo_email'            => 'E-mail do DPO para solicitações de titulares',
            'cookie_banner_ativo'  => 'Exibe banner de consentimento de cookies nas páginas públicas',
        ];

        foreach ($campos as $chave => $observacao) {
            $valor = $request->get($chave);
            if ($valor === null) {
                continue;
            }

            $config = $configRepo->findOneBy([
                'estabelecimento_id' => $this->session->get('userId'),
                'tipo'               => 'lgpd',
                'chave'              => $chave,
            ]);

            if (!$config) {
                $config = new Config();
                $config->setEstabelecimentoId($this->session->get('userId'));
                $config->setTipo('lgpd');
                $config->setChave($chave);
                $config->setObservacao($observacao);
                $this->em->persist($config);
            }

            $config->setValor($valor);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Configurações LGPD salvas com sucesso.']);
    }

    /**
     * Salva os pixels e tags de rastreamento (Google Analytics, Facebook Pixel,
     * Google Tag Manager) no banco homepet_login com estabelecimento_id = 0.
     *
     * @Route("settings/tracking", name="settings_tracking_salvar", methods={"POST"})
     */
    public function salvarTracking(Request $request): JsonResponse
    {
        $configRepo = $this->getRepositorio(Config::class);

        $campos = [
            'google_analytics_id'  => 'ID de medição do Google Analytics 4 (ex: G-XXXXXXXXXX)',
            'google_tag_manager_id'=> 'ID do Google Tag Manager (ex: GTM-XXXXXXX)',
            'facebook_pixel_id'    => 'ID do Pixel do Facebook/Meta (ex: 1234567890)',
            'google_ads_id'        => 'ID de conversão do Google Ads (ex: AW-XXXXXXXXX)',
        ];

        foreach ($campos as $chave => $observacao) {
            $valor = $request->get($chave);
            if ($valor === null) {
                continue;
            }

            $config = $configRepo->findOneBy([
                'estabelecimento_id' => $this->session->get('userId'),
                'tipo'               => 'tracking',
                'chave'              => $chave,
            ]);

            if (!$config) {
                $config = new Config();
                $config->setEstabelecimentoId($this->session->get('userId'));
                $config->setTipo('tracking');
                $config->setChave($chave);
                $config->setObservacao($observacao);
                $this->em->persist($config);
            }

            $config->setValor($valor);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Configurações de rastreamento salvas com sucesso.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Converte array de Config[] em mapa chave => valor para uso no template.
     *
     * @param Config[] $configs
     */
    private function indexarConfig(array $configs): array
    {
        $mapa = [];
        foreach ($configs as $c) {
            $mapa[$c->getChave()] = $c->getValor();
        }
        return $mapa;
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