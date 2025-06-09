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
            $menu = new Menu();
            $menu->setTitulo($request->request->get('titulo'));
            $menu->setParent($request->request->get('parent') ?: null);
            $menu->setDescricao($request->request->get('descricao'));
            $menu->setRota($request->request->get('rota'));
            $menu->setStatus($request->request->get('status'));
            $menu->setIcone($request->request->get('icone'));

            $em->persist($menu);
            $em->flush();

            return $this->redirectToRoute('menu_index');
        }

        return $this->render('menu/form.html.twig');
    }

    /**
     * @Route("menu/editar/{id}", name="menu_edit")
     */
    public function edit(Menu $menu, Request $request): Response
    {
         $menu = $repo->find($id);

        if (!$menu) {
            throw $this->createNotFoundException('Menu não encontrado');
        }

        if ($request->isMethod('POST')) {
            $menu->setTitulo($request->request->get('titulo'));
            $menu->setParent($request->request->get('parent') ?: null);
            $menu->setDescricao($request->request->get('descricao'));
            $menu->setRota($request->request->get('rota'));
            $menu->setStatus($request->request->get('status'));
            $menu->setIcone($request->request->get('icone'));

            $em->flush();

            return $this->redirectToRoute('menu_index');
        }

        return $this->render('menu/form.html.twig',['menu' => $menu]);
    }
}