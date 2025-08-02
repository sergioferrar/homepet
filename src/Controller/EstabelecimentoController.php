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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EstabelecimentoController extends DefaultController
{
    /**
     * @Route("/estabelecimento", name="app_estabelecimento")
     */
    public function index(): Response
    {
        if($this->security->getUser()->getAccessLevel() == 'Admin'){
            return $this->redirectToRoute('petshop_edit',['eid'=>$this->security->getUser()->getId()]);
        }
        
        $estabelecimentos = $this->getRepositorio(\App\Entity\Estabelecimento::class)->listaEstabelecimentos($this->session->get('userId'));

        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)
        ->findById($this->security->getUser()->getPetshopId())[0];

        $validaPlano = $this->verificarPlanoPorPeriodo($estabelecimento->getDataPlanoInicio(), $estabelecimento->getDataPlanoFim());

        $data['estabelecimentos'] = $estabelecimentos;
        $data['validaPlano'] = $validaPlano;

        return $this->render('estabelecimento/index.html.twig', $data);
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
     * @Route("/estabelecimento/cadastrar", name="estabelecimento_cadastrar", methods="POST")
     */
    public function cadastrar(Request $request): Response
    {
        $estabelecimento = new Estabelecimento();

        $plano = $request->get('planoId');

        $estabelecimento->setRazaoSocial($request->get('razaoSocial'));
        $estabelecimento->setCnpj($request->get('cnpj'));
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

        // Criar database apartir do estabelecimento criado
        //$database = $this->getRepositorio(Estabelecimento::class)->verificaDatabase($estabelecimento->getId());
        // if (!$database) {
        //     ## Inicia a criação do diretório para "download" do dump
        //     //$this->tempDirManager->init();
        //     //$arquivoSQL = "backup_bd_modelo.sql";
        //     //$diretorio = $this->tempDirManager->obterCaminho($arquivoSQL);
        /*
            $backupFile = dirname(__DIR__, 2) . '/instalation.sql';
            $this->databaseBkp->setDbName("homepet_{$estabelecimento->getId()}")
                ->createDatabase()
                ->importDatabase($backupFile);
        */
        //            ## Quebra da string do banco para puchar suas informações
        //            $hosts = explode(':', explode('mysql://', $_SERVER['DATABASE_URL'])[1]);
        //            $base = explode('@', $hosts[1]);
        //            // Realiza o backup do banco modelo
        //            $bck_bd_modelo = "mysqldump -u root -p -h " . end($base) . " --routines --set-gtid-purged=OFF --events --triggers homepet_000 | sed 's/homepet_000/homepet_{$estabelecimento->getId()}/g' > " . $diretorio;
        //            shell_exec($bck_bd_modelo);
        //            // Cria o novo banco de dados
        //            $criar_bd = "mysql -u root -p -h " . end($base) . " -e \"CREATE DATABASE homepet_{$estabelecimento->getId()}\"";
        //            shell_exec($criar_bd);
        //            //restaura o backup no novo banco
        //            $restaura_bd = "mysql -u root -p -h " . end($base) . " -c homepet_{$estabelecimento->getId()} < " . $diretorio;
        //            shell_exec($restaura_bd);
        //            $this->tempDirManager->deletarDiretorio();
        //  }

        return $this->redirectToRoute('petshop_usuario_cadastrar', ['estabelecimento' => $estabelecimento->getId(), 'planoId' => $plano]);
    }

    /**
     * @Route("/landing/usuario/cadastrar", name="petshop_usuario_cadastrar")
     */
    public function cadastrarUsuario(EmailService $emailService, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $usuario = new Usuario();
            $usuario->setNomeUsuario($request->get('nome_usuario'));
            $usuario->setSenha(password_hash($request->get('senha'), PASSWORD_DEFAULT, ["cost" => 10]));
            $usuario->setEmail($request->get('email'));
            $usuario->setAccessLevel($request->get('access_level'));

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
            $usuario->setPetshopId($request->get('estabelecimento'));

            $this->getRepositorio(Usuario::class)->add($usuario, true);

            $confirmationUrl = $this->generateUrl('confirma_cadastro', ['estabelecimento'=>$request->get('estabelecimento')], UrlGeneratorInterface::ABSOLUTE_URL);
            $html = $this->render('estabelecimento/email.html.twig', [
                'confirmation_link' => $confirmationUrl,
            ]);

            $emailService->sendEmail(
                'adiliogobira@gmail.com',
                'Confirmação de cadastro',
                $html
            );
            return $this->redirectToRoute('app_login');
        }

        return $this->render('usuario/cadastrar.html.twig', [
            'estabelecimento' => $request->get('estabelecimento'),
        ]);
    }

    /**
     * @Route("/assinatura/confirmacao/cadastro/{estabelecimento}", name="confirma_cadastro")
     */
    public function confirmacaoCadastro(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        try {
            $eid = $request->get('estabelecimento');
            $uid = $this->session->get('userId');

            // Pegar dados do estabelecimiento
            $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($eid);
            // Buscar usuario com o estabelecimento acessado
            $usario = $this->getRepositorio(\App\Entity\Usuario::class)->findOneBy(['petshop_id' => $eid]);
            // Buscar o plano que o estabelecimento está assinando
            $plano = $this->getRepositorio(\App\Entity\Plano::class)->find($estabelecimento->getPlanoId());

            // pra salvar o cadastro original
            $endpoint = "https://viacep.com.br/ws/{$estabelecimento->getCep()}/json";
            $endereco = json_decode(file_get_contents($endpoint), true);

            $comprador = [
                'nome' => $usario->getNomeUsuario(),
                'email' => $usario->getEmail(),
                'rua' => $endereco['logradouro'],
                'numero' => $estabelecimento->getNumero(),
                'bairro' => $endereco['bairro'],
                'cep' => $estabelecimento->getCep(),
                'cidade' => $endereco['localidade'],
                'estado' => $endereco['uf'],
            ];

            $produto = [
                'id' => $plano->getId(),
                'titulo' => $plano->getTitulo(),
                'qtd' => 1,
                'valor' => $plano->getValor(),
            ];

            $dataPagamento = [
                'title'=>$plano->getTitulo(),
                'price'=>$plano->getValor(),
                'email'=>$usario->getEmail(),
            ];

            $code = $mercadoPagoService->createPayment($dataPagamento);
            return $this->redirect($code['init_point']);
        // } catch(\Exception $e){
        //     dd($e);
        // }
        // return $this->render('estabelecimento/confirmacao.html.twig', [
        //     'estabelecimento' => $request->get('estabelecimento'),
        // ]);
        } catch (\Exception $e) {
            // Trate o erro conforme necessário
            throw $e;
        }
    }



    /**
     * @Route("/estabelecimento/renovacao/{eid}", name="petshop_renovacao")
     */
    public function renovaAssinatura(Request $request):Response
    {
        $dataAtual = new \DateTime();

        // Adiciona 30 dias
        $dataAtual->modify('+30 days');

        // Converte para string no formato do banco de dados (ex: MySQL)
        $dataFinal = $dataAtual->format('Y-m-d H:i:s'); // ou 'Y-m-d H:i:s' se precisar da hora

        $this->getRepositorio(\App\Entity\Estabelecimento::class)
        ->renovacao($this->session->get('userId'), $request->get('eid'), (new \DateTime())->format('Y-m-d H:i:s'), $dataFinal);

        return $this->redirectToRoute('app_estabelecimento');
    }

    /**
     * @Route("/estabelecimento/editar/{eid}", name="petshop_edit")
     */
    public function editar(Request $request): Response
    {
        $loja = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($request->get('eid'));
        $data = [];
        $data['loja']=$loja;
        return $this->render('estabelecimento/edit.html.twig',$data);
    }
}
