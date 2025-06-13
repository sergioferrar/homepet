<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;


class MenuController extends DefaultController
{
    /**
     * @Route("menu", name="menu_index")
     */
    public function index(Request $request): Response
    {
        $repo = $this->getRepositorio(\App\Entity\Menu::class)->findAll();
        $data = [];
        $data['menus'] = $repo;
        return $this->render('menu/index.html.twig', $data);
    }

    /**
     * @Route("menu/novo", name="menu_new")
     */
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $menu = new \App\Entity\Menu();
            $menu->setTitulo($request->request->get('titulo'));
            $menu->setParent($request->request->get('parent') ?: null);
            $menu->setDescricao($request->request->get('descricao'));
            $menu->setRota($request->request->get('rota'));
            $menu->setStatus($request->request->get('status'));
            $menu->setIcone($request->request->get('icone'));

            $repositorio = $this->getRepositorio(\App\Entity\Menu::class)->add($menu, true);    

            return $this->redirectToRoute('menu_index');
        }

        $listaMenu = $this->getRepositorio(\App\Entity\Menu::class)->findBy(['parent'=>null]);
        $data = [];
        $data['menus'] = $listaMenu;
        $data['rota'] = $this->generateUrl('menu_new');
        $data['planos'] = $this->getRepositorio(\App\Entity\Plano::class)->findAll();
        $data['modulos'] = $this->getRepositorio(\App\Entity\Modulo::class)->findAll();

        return $this->render('menu/form.html.twig', $data);
    }

    /**
     * @Route("menu/editar/{id}", name="menu_edit")
     */
    public function edit(Request $request): Response
    {

        $repositorio = $this->getRepositorio(\App\Entity\Menu::class);
        $menu = $repositorio->find($request->get('id'));

        if (!$menu) {
            throw $this->createNotFoundException('Menu não encontrado');
        }

        if ($request->isMethod('POST')) {

            $dados['id'] = $request->get('id');
            $dados['titulo'] = $request->get('titulo');
            $dados['parent'] = $request->get('parent');
            $dados['descricao'] = $request->get('descricao');
            $dados['rota'] = $request->get('rota');
            $dados['status'] = $request->get('status');
            $dados['icone'] = $request->get('icone');

            $this->getRepositorio(\App\Entity\Menu::class)->update($dados);

            return $this->redirectToRoute('menu_index');
        }

        $listaMenu = $this->getRepositorio(\App\Entity\Menu::class)->findBy(['parent'=>null]);
        $data = [];
        $data['menu'] = $menu;
        $data['menus'] = $listaMenu;
        $data['rota'] = $this->generateUrl('menu_edit',['id' => $request->get('id')]);
        return $this->render('menu/form.html.twig', $data);
    }
}