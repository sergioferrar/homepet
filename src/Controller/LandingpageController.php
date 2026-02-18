<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use App\Service\DatabaseBkp;
use App\Service\EmailService;
use App\Service\Payment\MercadoPagoService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
        $data['modulos'] = $this->modulosSistema;

        return $this->render('home/landing.html.twig', $data);
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
        $estabelecimento = new Estabelecimento();

        $plano = $request->get('planoId');

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
        try {
            $backupFile = dirname(__DIR__, 2) . '/instalation.sql';
            
            // Verifica se o arquivo de instalação existe
            if (!file_exists($backupFile)) {
                throw new \Exception("Arquivo de instalação não encontrado: {$backupFile}");
            }
            
            // Cria o banco de dados e importa a estrutura
            $this->databaseBkp->setDbName("homepet_{$estabelecimento->getId()}")
                ->createDatabase()
                ->importDatabase($backupFile);
                
        } catch (\Exception $e) {
            // Log do erro mas não bloqueia o cadastro
            error_log("Erro ao criar banco de dados para estabelecimento {$estabelecimento->getId()}: " . $e->getMessage());
            // Você pode adicionar uma flash message aqui se quiser notificar o usuário
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
                'price' => (float)$plano->getValor(),
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
}
