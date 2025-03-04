<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EstabelecimentoController extends DefaultController
{
    /**
     * @Route("/estabelecimento", name="app_estabelecimento")
     */
    public function index(): Response
    {
        return $this->render('estabelecimento/index.html.twig', [
            'controller_name' => 'EstabelecimentoController',
        ]);
    }

    /**
     * @Route("/estabelecimento/cadastrar", name="estabelecimento_cadastrar", methods="POST")
     */
    public function cadastrar(Request $request): Response
    {
        $estabelecimento = new Estabelecimento();

        $estabelecimento->setRazaoSocial($request->get('razaoSocial'));
        $estabelecimento->setCnpj($request->get('cnpj'));
        $estabelecimento->setRua($request->get('rua'));
        $estabelecimento->setNumero($request->get('numero'));
        $estabelecimento->setComplemento($request->get('complemento'));
        $estabelecimento->setBairro($request->get('bairro'));
        $estabelecimento->setCidade($request->get('cidade'));
        $estabelecimento->setPais($request->get('pais'));
        $estabelecimento->setCep($request->get('cep'));
        $estabelecimento->setStatus('Ativo');
        $estabelecimento->setDataCadastro((new \DateTime(   'now')));
        $this->getRepositorio(Estabelecimento::class)->add($estabelecimento, true);
//        dd($estabelecimento);

        // Criar database apartir do estabelecimento criado
        $database = $this->getRepositorio(Estabelecimento::class)->verificaDatabase($estabelecimento->getId());
        if (!$database) {
            ## Inicia a criação do diretório para "download" do dump
            $this->tempDirManager->init();

            $arquivoSQL = "backup_bd_modelo.sql";
            $diretorio = $this->tempDirManager->obterCaminho($arquivoSQL);

            ## Quebra da string do banco para puchar suas informações
            $hosts = explode(':', explode('mysql://', $_SERVER['DATABASE_URL'])[1]);
            $base = explode('@', $hosts[1]);

            // Realiza o backup do banco modelo

            $bck_bd_modelo = "mysqldump -u root -p -h " . end($base) . " --routines --set-gtid-purged=OFF --events --triggers homepet_000 | sed 's/homepet_000/homepet_{$estabelecimento->getId()}/g' > " . $diretorio;
            shell_exec($bck_bd_modelo);

            // Cria o novo banco de dados
            $criar_bd = "mysql -u root -p -h " . end($base) . " -e \"CREATE DATABASE homepet_{$estabelecimento->getId()}\"";
            shell_exec($criar_bd);

            //restaura o backup no novo banco
            $restaura_bd = "mysql -u root -p -h " . end($base) . " -c homepet_{$estabelecimento->getId()} < " . $diretorio;
            shell_exec($restaura_bd);
            dd($database);

            $this->tempDirManager->deletarDiretorio();
        }

        return $this->redirectToRoute('petshop_usuario_cadastrar', ['estabelecimento' => $estabelecimento->getId()]);

    }

    /**
     * @Route("/usuario/cadastrar", name="petshop_usuario_cadastrar")
     */
    public function cadastrarUsuario(Request $request): Response
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
            return $this->redirectToRoute('app_login');
        }
//        $usuario = new User();
//        $usuario->setEstabelecimento($estabelecimento); // Relaciona o usuário ao estabelecimento
//        $form = $this->createForm(UserType::class, $usuario);
//
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $entityManager->persist($usuario);
//            $entityManager->flush();
//
//            $this->addFlash('success', 'Usuário cadastrado com sucesso! Faça login para acessar o sistema.');
//
//        }

        return $this->render('usuario/cadastrar.html.twig', [
            'estabelecimento' => $request->get('estabelecimento'),
        ]);
    }
}
