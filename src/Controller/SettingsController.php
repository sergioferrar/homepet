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

        // Usa o usuário autenticado pelo Symfony — não depende de userId na sessão.
        $usuarioLogado = $this->security->getUser();

        if (!$usuarioLogado || !$usuarioLogado->getPetshopId()) {
            $this->addFlash('warning',
                'As configurações de estabelecimento não estão disponíveis no painel Super Admin. '
                . 'Acesse um estabelecimento primeiro para gerenciar suas configurações.'
            );
            return $this->redirectToRoute('superadmin_dashboard');
        }

        $dadosUsuarioLogado = $this->getRepositorio(\App\Entity\Usuario::class)
            ->findOneBy(['id' => $usuarioLogado->getId()]);

        $dadosEstabelecimentoLogado = $this->getRepositorio(\App\Entity\Estabelecimento::class)
            ->findOneBy(['id' => $dadosUsuarioLogado->getPetshopId()]);
        
        $dadosPlanoLogado = $this->getRepositorio(\App\Entity\Plano::class)->findOneBy(['id' => $dadosEstabelecimentoLogado->getPlanoId()]);
        $dadosPlanos = $this->getRepositorio(\App\Entity\Plano::class)->findAll();
        $data['estabelecimento'] = $dadosEstabelecimentoLogado;
        $data['planoLogado'] = $dadosEstabelecimentoLogado;
        $data['planos'] = $dadosPlanos;
        $data['gateway'] = $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'gateway_payment']);
        $data['mailer']  = $this->getRepositorio(Config::class)->findBy(['estabelecimento_id' => $dadosUsuarioLogado->getPetshopId(), 'tipo' => 'mailer']);

        // ── Configurações LGPD e Tracking — lidas direto via DBAL do banco login ──
        // getRepositorio() pode estar apontando para o tenant após switchDB em outra parte
        // da requisição, por isso usamos o DBAL direto aqui para garantir o banco correto.
        $data['lgpd']     = $this->lerConfigsDbal('lgpd');
        $data['tracking'] = $this->lerConfigsDbal('tracking');

        return $this->render('settings/index.html.twig', $data);
    }

    /**
     * Salva as páginas de LGPD (Política de Privacidade e Termos de Uso)
     * no banco homepet_login com estabelecimento_id = 0 (global do sistema).
     *
     * Usa DBAL direto (INSERT … ON DUPLICATE KEY UPDATE) para garantir que
     * a gravação ocorra sempre no banco homepet_login, independente de o
     * tenant ter sido trocado antes nesta requisição.
     *
     * @Route("settings/lgpd", name="settings_lgpd_salvar", methods={"POST"})
     */
    public function salvarLgpd(Request $request): JsonResponse
    {
        // Garante conexão com o banco homepet_login antes de qualquer leitura/escrita
        $this->restauraLoginDB();

        $campos = [
            'politica_privacidade' => 'Texto da Política de Privacidade — LGPD',
            'termos_uso'           => 'Texto dos Termos de Uso',
            'dpo_nome'             => 'Nome do Encarregado de Dados (DPO)',
            'dpo_email'            => 'E-mail do DPO para solicitações de titulares',
            'cookie_banner_ativo'  => 'Exibe banner de consentimento de cookies nas páginas públicas',
        ];

        try {
            $this->upsertConfigs('lgpd', $campos, $request);
            return new JsonResponse(['success' => true, 'message' => 'Configurações LGPD salvas com sucesso.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Salva os pixels e tags de rastreamento (Google Analytics, Facebook Pixel,
     * Google Tag Manager) no banco homepet_login com estabelecimento_id = 0.
     *
     * @Route("settings/tracking", name="settings_tracking_salvar", methods={"POST"})
     */
    public function salvarTracking(Request $request): JsonResponse
    {
        $this->restauraLoginDB();

        $campos = [
            'google_analytics_id'   => 'ID de medição do Google Analytics 4 (ex: G-XXXXXXXXXX)',
            'google_tag_manager_id' => 'ID do Google Tag Manager (ex: GTM-XXXXXXX)',
            'facebook_pixel_id'     => 'ID do Pixel do Facebook/Meta (ex: 1234567890)',
            'google_ads_id'         => 'ID de conversão do Google Ads (ex: AW-XXXXXXXXX)',
        ];

        try {
            $this->upsertConfigs('tracking', $campos, $request);
            return new JsonResponse(['success' => true, 'message' => 'Configurações de rastreamento salvas com sucesso.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Upsert genérico via DBAL — INSERT … ON DUPLICATE KEY UPDATE.
     * Garante atomicidade e funciona corretamente com o banco homepet_login.
     *
     * @param array<string,string> $campos  chave => observação
     */
    private function upsertConfigs(string $tipo, array $campos, Request $request): void
    {
        $conn = $this->managerRegistry->getConnection();

        foreach ($campos as $chave => $observacao) {
            $valor = $request->get($chave);
            if ($valor === null) {
                continue;
            }

            // Cria a tabela config caso não exista (idempotente — seguro rodar sempre)
            $conn->executeStatement("
                CREATE TABLE IF NOT EXISTS `config` (
                    `id`                INT          NOT NULL AUTO_INCREMENT,
                    `estabelecimento_id` INT         NOT NULL DEFAULT 0,
                    `chave`             VARCHAR(255) NOT NULL,
                    `valor`             LONGTEXT         NULL,
                    `tipo`              VARCHAR(255) NOT NULL,
                    `observacao`        TEXT             NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_config_estab_tipo_chave` (`estabelecimento_id`, `tipo`(100), `chave`(100))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $conn->executeStatement("
                INSERT INTO `config` (`estabelecimento_id`, `tipo`, `chave`, `valor`, `observacao`)
                VALUES (:estab, :tipo, :chave, :valor, :obs)
                ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)
            ", [
                'estab' => self::GLOBAL_ESTAB_ID,
                'tipo'  => $tipo,
                'chave' => $chave,
                'valor' => $valor,
                'obs'   => $observacao,
            ]);
        }
    }

    /**
     * Lê configs de um tipo via DBAL direto, retornando mapa chave => valor.
     * Usa a conexão padrão (homepet_login) independente de switchDB anterior.
     */
    private function lerConfigsDbal(string $tipo): array
    {
        try {
            $conn = $this->managerRegistry->getConnection();
            $rows = $conn->fetchAllAssociative(
                "SELECT chave, valor FROM config WHERE estabelecimento_id = :estab AND tipo = :tipo",
                ['estab' => self::GLOBAL_ESTAB_ID, 'tipo' => $tipo]
            );
            $mapa = [];
            foreach ($rows as $row) {
                $mapa[$row['chave']] = $row['valor'];
            }
            return $mapa;
        } catch (\Throwable) {
            // Tabela ainda não existe — retorna mapa vazio (primeira vez)
            return [];
        }
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
