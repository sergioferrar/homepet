<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use App\Entity\Veterinario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @Route("dashboard")
 */
class UsuarioController extends DefaultController
{
    /**
     * @Route("/usuario/lista", name="app_usuario")
     */
    public function index(Request $request): Response
    {
        $data = [];
        // dd($this->tenantContext);
        $repositorio = $this->getRepositorio(Usuario::class);
        switch ($this->security->getUser()->getAccessLevel()) {
            case 'Super Admin':
                $usuarios = $repositorio->listaTodos();
                $pethop = $this->getRepositorio(Estabelecimento::class)->find($this->tenantContext->getEstabelecimentoId());

                $data['estabelecimento'] = $pethop;
                break;
            case 'Admin':
                $usuarios = $repositorio->listaTodosPrivado($this->tenantContext->getEstabelecimentoId());
                break;
        }

        $data['usuarios'] = $usuarios;

        return $this->render('usuario/index.html.twig', $data);
    }

    /**
     * @Route("/usuario/novo", name="app_usuario_create")
     */
    public function create(Request $request): Response
    {
        // Lista os veterinários do estabelecimento (tabela fica no banco do tenant)
        // para o select que aparece quando o nível de acesso é "Veterinário".
        $veterinarios = [];
        try {
            $this->switchDB();
            $veterinarios = $this->getRepositorio(Veterinario::class)->findBy(
                ['estabelecimentoId' => $this->tenantContext->getEstabelecimentoId(), 'status' => 'ativo'],
                ['nome' => 'ASC']
            );
        } catch (\Throwable $e) {
            $veterinarios = [];
        } finally {
            $this->restauraLoginDB();
        }

        return $this->render('usuario/create.html.twig', [
            'controller_name' => 'UsuarioController',
            'veterinarios' => $veterinarios,
        ]);
    }

    /**
     * @Route("/usuario/edit/{id}", name="usuario_edit")
     */
    public function edit(Request $request): Response
    {
        $usuario = $this->getRepositorio(Usuario::class)->findOneBy(['id' => $request->get('id')]);

        // Veterinários do estabelecimento para o select (quando nível = Veterinário)
        $veterinarios = [];
        try {
            $this->switchDB();
            $veterinarios = $this->getRepositorio(Veterinario::class)->findBy(
                ['estabelecimentoId' => $this->tenantContext->getEstabelecimentoId(), 'status' => 'ativo'],
                ['nome' => 'ASC']
            );
        } catch (\Throwable $e) {
            $veterinarios = [];
        } finally {
            $this->restauraLoginDB();
        }

        return $this->render('usuario/edit.html.twig', [
            'usuario' => $usuario,
            'veterinarios' => $veterinarios,
        ]);
    }

    /**
     * @Route("/usuario/create/salvar", name="usuario_create_save")
     */
    public function store(Request $request, MailerInterface $mailer): Response
    {
        $accessLevel = $request->get('access_level');

        $usuario = new Usuario();
        $usuario->setNomeUsuario($request->get('nome_usuario'));
        $usuario->setEmail($request->get('email'));
        $usuario->setAccessLevel($accessLevel);

        // Vínculo com veterinário quando o nível for "Veterinário"
        if ($accessLevel === 'Veterinário' && $request->get('veterinario_id')) {
            $usuario->setVeterinarioId((int) $request->get('veterinario_id'));
        }

        switch ($accessLevel) {
            case 'Super Admin':
            case 'Admin':
                $roles = ['ROLE_ADMIN'];
                break;
            case 'Veterinário':
                $roles = ['ROLE_VETERINARIO', 'ROLE_ADMIN_USER'];
                break;
            case 'Financeiro':
                $roles = ['ROLE_FINANCEIRO', 'ROLE_ADMIN_USER'];
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
        $usuario->setPetshopId($this->security->getUser()->getPetshopId());

        // Todo usuário é criado por convite: recebe um link para definir a senha.
        $token = bin2hex(random_bytes(32));
        $usuario->setSenha(password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT, ['cost' => 10]));
        $usuario->setAtivo(false);
        $usuario->setTokenSenha($token);
        $usuario->setTokenExpira(new \DateTime('+48 hours'));

        $linkDefinicao = $this->generateUrl(
            'usuario_definir_senha',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->getRepositorio(Usuario::class)->add($usuario, true);

        // Envia o convite por e-mail (best-effort: não bloqueia o cadastro)
        $nomeEstab = '';
        try {
            $estab = $this->getRepositorio(Estabelecimento::class)->find($this->security->getUser()->getPetshopId());
            $nomeEstab = $estab ? $estab->getRazaoSocial() : '';
        } catch (\Throwable $e) {
            $nomeEstab = '';
        }

        $enviado = false;
        try {
            $email = (new Email())
                ->from('suporte@systemhomepet.com')
                ->to($usuario->getEmail())
                ->subject('Convite de acesso' . ($nomeEstab ? " - {$nomeEstab}" : ''))
                ->html($this->renderView('emails/definir_senha.html.twig', [
                    'nome' => $usuario->getNomeUsuario(),
                    'estabelecimento' => $nomeEstab,
                    'link' => $linkDefinicao,
                ]));
            $mailer->send($email);
            $enviado = true;
        } catch (\Throwable $e) {
            $enviado = false;
        }

        if ($enviado) {
            $this->addFlash('success', 'Usuário convidado! Um e-mail foi enviado para ' . $usuario->getEmail() . ' com o link para criar a senha.');
        } else {
            $this->addFlash('warning', 'Usuário criado, mas o e-mail não pôde ser enviado. Envie este link para o usuário criar a senha: ' . $linkDefinicao);
        }

        return $this->redirectToRoute('app_usuario');
    }

    /**
     * @Route("/usuario/edit/salvar", name="usuario_edit_save")
     */
    public function update(Request $request): Response
    {
        $usuario = $this->getRepositorio(Usuario::class)->findOneBy(['id' => $request->get('id')]);

        if (!$usuario) {
            $this->addFlash('danger', 'Usuário não encontrado.');
            return $this->redirectToRoute('app_usuario');
        }

        $accessLevel = $request->get('access_level');

        $usuario->setNomeUsuario($request->get('nome_usuario'));
        $usuario->setEmail($request->get('email'));
        $usuario->setAccessLevel($accessLevel);

        // Vínculo com veterinário (limpa se não for mais veterinário)
        if ($accessLevel === 'Veterinário') {
            $usuario->setVeterinarioId($request->get('veterinario_id') ? (int) $request->get('veterinario_id') : null);
        } else {
            $usuario->setVeterinarioId(null);
        }

        // Recalcula os papéis conforme o nível de acesso
        switch ($accessLevel) {
            case 'Super Admin':
            case 'Admin':
                $roles = ['ROLE_ADMIN'];
                break;
            case 'Veterinário':
                $roles = ['ROLE_VETERINARIO', 'ROLE_ADMIN_USER'];
                break;
            case 'Financeiro':
                $roles = ['ROLE_FINANCEIRO', 'ROLE_ADMIN_USER'];
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

        // A senha NÃO é alterada aqui — é responsabilidade do próprio usuário
        // (convite por e-mail ou tela "Meu Perfil").
        $this->getRepositorio(Usuario::class)->update($usuario);

        $this->addFlash('success', 'Usuário atualizado com sucesso!');
        return $this->redirectToRoute('app_usuario');
    }
}
