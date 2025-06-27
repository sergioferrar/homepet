<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UsuarioController extends DefaultController
{
    /**
     * @Route("/usuario/lista", name="app_usuario")
     */
    public function index(Request $request): Response
    {
        $data = [];
        $repositorio = $this->getRepositorio(Usuario::class);
        switch ($this->security->getUser()->getAccessLevel()) {
            case 'Super Admin':
                $usuarios = $repositorio->listaTodos();
                $pethop = $this->getRepositorio(Estabelecimento::class)->find($this->estabelecimentoId);

                $data['estabelecimento'] = $pethop;
                break;
            case 'Admin':
                $usuarios = $repositorio->listaTodosPrivado($this->estabelecimentoId);
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
        return $this->render('usuario/create.html.twig', [
            'controller_name' => 'UsuarioController',
        ]);
    }

    /**
     * @Route("/usuario/edit/{id}", name="usuario_edit")
     */
    public function edit(Request $request): Response
    {
        $usuarios = $this->getRepositorio(Usuario::class)->findOneBy(['id' => $request->get('id')]);
        $data = [];
        $data['usuario'] = $usuarios;
        return $this->render('usuario/edit.html.twig', $data);
    }

    /**
     * @Route("/usuario/create/salvar", name="usuario_create_save")
     */
    public function store(Request $request): Response
    {
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
        $usuario->setPetshopId($this->security->getUser()->getPetshopId());

        $this->getRepositorio(Usuario::class)->add($usuario, true);

        return $this->redirectToRoute('app_usuario');
    }

    /**
     * @Route("/usuario/edit/salvar", name="usuario_edit_save")
     */
    public function update(Request $request): Response
    {
        $usuario = new Usuario();
        $usuario->setNomeUsuario($request->get('nome_usuario'));
        $usuario->setSenha(password_hash($request->get('senha'), PASSWORD_DEFAULT, ["cost" => 10]));
        $usuario->setEmail($request->get('email'));
        $usuario->setAccessLevel($request->get('accessLevel'));
        $this->getRepositorio(Usuario::class)->update($usuario);

        return $this->redirectToRoute('app_usuario');
    }
}
