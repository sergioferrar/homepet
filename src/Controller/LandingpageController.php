<?php
namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use App\Installer\TenantDatabaseInstaller;
use Doctrine\DBAL\DriverManager;
use App\Service\DatabaseBkp;
use App\Service\EmailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LandingpageController extends DefaultController
{

    /**
     * @Route("/", name="landing_home")
     */
    public function landing(Request $request): Response
    {
        $data = [];

        $planos = $this->getRepositorio(\App\Entity\Plano::class)->listaPlanosHome();
        $data['planos'] = $planos;
        $data['cookie_banner_ativo'] = true;
        $data['tracking'] = '';
        $data['modulos'] = $this->modulosSistema;

        return $this->render('home/landing.html.twig', $data);
    }

    /**
     * @Route("/planos", name="landing_planos")
     */
    public function planos(Request $request): Response
    {
        $planos = $this->getRepositorio(\App\Entity\Plano::class)->listaPlanosHome();

        return $this->render('home/planos.html.twig', [
            'planos'              => $planos,
            'cookie_banner_ativo' => true,
            'tracking'            => '',
            'modulos'             => $this->modulosSistema,
        ]);
    }

    /**
     * @Route("/landing/cadastro", name="landing_cadastro")
     */
    public function cadastro(Request $request): Response
    {
        $data = [];

        $planos = $this->getRepositorio(\App\Entity\Plano::class)->listaPlanosHome($this->session->get('userId'));
        $data['planoId'] = $request->get('planoId');

        return $this->render('home/cadastro-estabelecimento.html.twig', $data);
    }

    /**
     * @Route("/landing/cadastrar", name="estabelecimento_cadastrar", methods="POST")
     */
    public function cadastrar(Request $request): Response
    {

        try {
            $plano = $request->get('planoId');

            $estabelecimento = new Estabelecimento();
            $estabelecimento->setRazaoSocial($request->get('razaoSocial'));
            $estabelecimento->setCnpj(preg_replace('/\D/', '', $request->get('cnpj')));
            $estabelecimento->setRua($request->get('rua'));
            $estabelecimento->setNumero($request->get('numero'));
            $estabelecimento->setComplemento($request->get('complemento'));
            $estabelecimento->setBairro($request->get('bairro'));
            $estabelecimento->setCidade($request->get('cidade'));
            $estabelecimento->setPais($request->get('pais'));
            $estabelecimento->setCep($request->get('cep'));
            $estabelecimento->setStatus('Inativo');
            $estabelecimento->setDataCadastro((new \DateTime('now')));
            $estabelecimento->setDataPlanoInicio((new \DateTime('now')));
            $estabelecimento->setDataPlanoFim((new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month')))));
            $estabelecimento->setPlanoId($request->get('planoId'));
            $this->getRepositorio(Estabelecimento::class)->add($estabelecimento, true);
            //dd($estabelecimento);

            // Criar database apartir do estabelecimento criado usando DatabaseBkp

            // --- Dentro do seu Controller / Service ---

            // 1. Cria o banco de dados do novo tenant
            $dbName = "homepet_{$estabelecimento->getId()}";
            $this->databaseBkp
                ->setDbName($dbName)
                ->createDatabase();

            // 2. Monta a Connection Doctrine apontando para o banco recém-criado
            $tenantConnectiold = $this->pegarConexaoAtiva();
            $this->prepareTenantidConnection($estabelecimento->getId());

            $tenantConnection = $this->entityManager->getConnection();;
            // dd($tenantConnectiold, $tenantConnection->execute("SHOW databases")->fetchAssociative());

            // 3. Executa o instalador (cria todas as 33 tabelas em ordem)
            $installer = new TenantDatabaseInstaller($this->logger);
            $result = $installer->install($tenantConnection);

            // 4. Avalia o resultado
            if (!$result['success']) {
                throw new \RuntimeException(
                    sprintf(
                        'Falha ao criar tabela "%s" no banco "%s": %s',
                        $result['failed_table'],
                        $dbName,
                        $result['message'],
                    )
                );
            }

            $this->logger->info('Schema do tenant instalado com sucesso.', [
                'db' => $dbName,
                'created_tables' => $result['created_tables'],
            ]);

            // 5. Flash message de sucesso (opcional)
            // $this->addFlash('success', "Banco de dados do estabelecimento criado com sucesso ({$dbName}).");

        } catch (\RuntimeException $e) {
            // Erro controlado do instalador — tabela específica falhou
            $this->logger->error('Erro na instalação do schema do tenant.', [
                'estabelecimento' => $estabelecimento->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            // $this->addFlash('warning', 'O banco foi criado, mas houve falha ao instalar as tabelas. Contate o suporte.');

        } catch (\Exception $e) {
            // Erro inesperado (conexão, permissão, etc.)
            $this->logger->critical('Erro inesperado ao criar banco do tenant.', [
                'estabelecimento' => $estabelecimento->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            // $this->addFlash('danger', 'Não foi possível criar o banco de dados. Contate o suporte.');
        }

        return $this->redirectToRoute('petshop_usuario_cadastrar', ['estabelecimento' => $estabelecimento->getId(), 'planoId' => $plano]);
    }

    /**
     * @Route("/landing/usuario/cadastrar", name="petshop_usuario_cadastrar")
     */
    public function cadastrarUsuario(EmailService $emailService, Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('usuario/cadastrar.html.twig', [
                'estabelecimento' => $request->get('estabelecimento'),
            ]);
        }

        /**
         * 1. Validação básica
         */
        $senha = $request->get('senha');
        $confirmacao = $request->get('senha_confirmacao');

        if ($senha !== $confirmacao) {
            $this->addFlash('error', 'As senhas não conferem. Tente novamente.');
            return $this->redirectToRoute('petshop_usuario_cadastro');
        }

        /**
         * 2. Criação do usuário
         */
        $usuario = new Usuario();
        $usuario->setNomeUsuario($request->get('nome_usuario'));
        $usuario->setEmail($request->get('email'));
        $usuario->setAccessLevel($request->get('access_level'));
        $usuario->setPetshopId($request->get('estabelecimento'));

        $usuario->setSenha(
            password_hash($senha, PASSWORD_DEFAULT, ['cost' => 10])
        );

        /**
         * 3. Definição de roles
         */
        switch ($request->get('access_level')) {
            case 'Super Admin':
            case 'Admin':
                $roles = ['ROLE_ADMIN'];
                break;
            case 'Atendente':
            case 'Tosador':
            case 'Balconista':
                $roles = ['ROLE_ADMIN_USER'];
                break;
            default:
                $roles = ['ROLE_USER'];
                break;
        }

        $usuario->setRoles($roles);

        /**
         * 4. Persistência
         */
        $this->getRepositorio(Usuario::class)->add($usuario, true);

        /**
         * 5. Geração do link de confirmação
         */
        $confirmationUrl = $this->generateUrl(
            'confirma_cadastro',
            ['estabelecimento' => $request->get('estabelecimento')],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->render('estabelecimento/email.html.twig', [
            'confirmation_link' => $confirmationUrl,
        ])->getContent();

        /**
         * 6. Envio de e-mail
         */
        $emailService->sendEmail(
            $request->get('email'),
            'Confirmação de cadastro no sistema System Home Pet',
            $html
        );

        /**
         * 7. Redirecionamento com feedback
         */
        $mensagem = base64_encode(
            "Foi enviado um e-mail para <b>{$request->get('email')}</b>.
             Verifique sua caixa de entrada ou SPAM e clique no link para concluir seu cadastro!"
        );

        return $this->redirectToRoute('app_login', ['confirmation' => $mensagem]);
    }

    /**
     * @Route("/assinatura/pagamento/efetuar/{estabelecimento}", name="confirma_cadastro")
     */
    public function confirmacaoCadastro(
        Request $request,
        \App\Service\Payment\PaymentGatewayFactory $paymentGatewayFactory,
        \App\Service\InvoiceService $invoiceService
    ): Response {
        try {
            $eid = $request->get('estabelecimento');
            $uid = $this->session->get('userId');

            // Pegar dados do estabelecimiento
            $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($eid);
            // Buscar usuario com o estabelecimento acessado
            $usario = $this->getRepositorio(\App\Entity\Usuario::class)->findOneBy(['petshop_id' => $eid]);
            // Buscar o plano que o estabelecimento está assinando
            $plano = $this->getRepositorio(\App\Entity\Plano::class)->find($estabelecimento->getPlanoId());

            // Criar invoice para esta assinatura
            $invoice = $invoiceService->createInvoice($estabelecimento, [
                'tipo' => 'assinatura',
                'valor_total' => $plano->getValor(),
                'plano_id' => $plano->getId(),
                'data_vencimento' => $estabelecimento->getDataPlanoFim(),
            ]);

            // Obter gateway de pagamento
            $gateway = $paymentGatewayFactory->getDefaultGateway();

            // pra salvar o cadastro original
            $endpoint = "https://viacep.com.br/ws/{$estabelecimento->getCep()}/json";
            $endereco = json_decode(file_get_contents($endpoint), true);

            $comprador = [
                'name' => $usario->getNomeUsuario(),
                'email' => $usario->getEmail(),
                'rua' => $endereco['logradouro'] ?? '',
                'numero' => $estabelecimento->getNumero(),
                'bairro' => $endereco['bairro'] ?? '',
                'cidade' => $endereco['localidade'] ?? '',
                'estado' => $endereco['uf'] ?? '',
                'idUsuario' => $usario->getId(),
                'cnpj' => $estabelecimento->getCNPJ(),
                'cep' => $endereco['cep'] ?? '',
            ];

            $dataPagamento = [
                'comprador' => $comprador,
                'planoId' => $plano->getId(),
                'title' => $plano->getTitulo(),
                'price' => (float) $plano->getValor(),
                'email' => $usario->getEmail(),
                'external_reference' => $invoice->getId(),
            ];

            // Salva na sessão para usar após pagamento
            $request->getSession()->set('finaliza', [
                'uid' => $usario->getId(),
                'eid' => $eid,
                'invoice_id' => $invoice->getId(),
            ]);

            // Criar assinatura recorrente
            $result = $gateway->createSubscription($dataPagamento);

            if ($result['success']) {
                // Atualizar invoice com subscription_id
                $invoice->setSubscriptionId($result['subscription_id']);
                $invoice->setPaymentGateway($gateway->getGatewayName());
                $this->getRepositorio(\App\Entity\Fatura::class)->add($invoice, true);

                // Redirecionar para página de pagamento do gateway
                return $this->redirect($result['init_point']);
            } else {
                throw new \Exception($result['error'] ?? 'Erro ao criar assinatura');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro ao processar pagamento: ' . $e->getMessage());
            return $this->redirectToRoute('landing_home');
        }
    }

    /**
     * Página pública — Política de Privacidade.
     * Lê o conteúdo da tabela config (banco homepet_login, estab=0, tipo=lgpd).
     *
     * @Route("/politicas-de-privacidade", name="landing_politica_privacidade", methods={"GET"})
     */
    public function politicaPrivacidade(): Response
    {
        $config = $this->getRepositorio(\App\Entity\Config::class);

        $politica = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'politica_privacidade']);
        $dpoNome = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'dpo_nome']);
        $dpoEmail = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'dpo_email']);
        $banner = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'cookie_banner_ativo']);
        $tracking = $this->carregarTracking($config);

        return $this->render('home/politica_privacidade.html.twig', [
            'conteudo' => $politica?->getValor() ?? '',
            'dpo_nome' => $dpoNome?->getValor() ?? '',
            'dpo_email' => $dpoEmail?->getValor() ?? '',
            'cookie_banner_ativo' => ($banner?->getValor() ?? '0') === '1',
            'tracking' => $tracking,
        ]);
    }

    /**
     * Página pública — Termos de Uso.
     *
     * @Route("/termos-de-uso", name="landing_termos_uso", methods={"GET"})
     */
    public function termosUso(): Response
    {
        $config = $this->getRepositorio(\App\Entity\Config::class);

        $termos = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'termos_uso']);
        $dpoNome = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'dpo_nome']);
        $dpoEmail = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'dpo_email']);
        $banner = $config->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'lgpd', 'chave' => 'cookie_banner_ativo']);
        $tracking = $this->carregarTracking($config);

        return $this->render('home/termos_uso.html.twig', [
            'conteudo' => $termos?->getValor() ?? '',
            'dpo_nome' => $dpoNome?->getValor() ?? '',
            'dpo_email' => $dpoEmail?->getValor() ?? '',
            'cookie_banner_ativo' => ($banner?->getValor() ?? '0') === '1',
            'tracking' => $tracking,
        ]);
    }

    /**
     * Endpoint AJAX — registra o consentimento de cookies do visitante.
     * Recebe a escolha (aceitar/recusar) e retorna JSON.
     *
     * @Route("/lgpd/consentimento", name="landing_lgpd_consentimento")
     */
    public function registrarConsentimento(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $escolha = $request->get('escolha'); // 'aceito' ou 'recusado'
        $allowed = ['aceito', 'recusado'];

        if (!in_array($escolha, $allowed, true)) {
            return $this->json(['success' => false, 'message' => 'Escolha inválida.'], 400);
        }

        // Grava cookie de consentimento por 365 dias (não precisa de banco para esta etapa)
        $response = $this->json(['success' => true, 'consentimento' => $escolha]);
        $response->headers->setCookie(
            new \Symfony\Component\HttpFoundation\Cookie(
                'hp_lgpd_consent',
                $escolha,
                new \DateTime('+365 days'),
                '/',
                null,
                false,
                false,
                false,
                'Lax'
            )
        );

        return $response;
    }

    /**
     * Carrega os IDs de tracking (GA4, GTM, Pixel, Ads) do banco login.
     */
    private function carregarTracking($configRepo): array
    {
        $chaves = ['google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id', 'google_ads_id'];
        $tracking = [];
        foreach ($chaves as $chave) {
            $c = $configRepo->findOneBy(['estabelecimento_id' => 0, 'tipo' => 'tracking', 'chave' => $chave]);
            $tracking[$chave] = $c?->getValor() ?? '';
        }
        return $tracking;
    }
}
