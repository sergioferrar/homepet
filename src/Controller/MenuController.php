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
        $this->restauraLoginDB();
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
        $this->restauraLoginDB();
        if ($request->isMethod('POST')) {
            $menu = new \App\Entity\Menu();
            $menu->setTitulo($request->request->get('titulo'));
            $menu->setParent($request->request->get('parent') ?: null);
            $menu->setDescricao($request->request->get('descricao'));
            $menu->setRota($request->request->get('rota'));
            $menu->setStatus($request->request->get('status'));
            $menu->setIcone($request->request->get('icone'));
            $menu->setModulo($request->request->get('modulo'));

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

        $this->restauraLoginDB();
        $repositorio = $this->getRepositorio(\App\Entity\Menu::class);
        $menu = $repositorio->find($request->get('id'));

        if (!$menu) {
            throw $this->createNotFoundException('Menu não encontrado');
        }

        if ($request->isMethod('POST')) {

            $dados['id'] = $request->get('id');
            $dados['titulo'] = $request->get('titulo');
            $dados['parent'] = $request->get('parent') ?? '';
            $dados['descricao'] = $request->get('descricao');
            $dados['rota'] = $request->get('rota');
            $dados['status'] = $request->get('status');
            $dados['icone'] = $request->get('icone');
            $dados['modulo'] = $request->get('modulo');

            $this->getRepositorio(\App\Entity\Menu::class)->update($dados);

            return $this->redirectToRoute('menu_index');
        }

        $listaMenu = $this->getRepositorio(\App\Entity\Menu::class)->findBy(['parent'=>null]);
        $data = [];
        $data['menu'] = $menu;
        $data['menus'] = $listaMenu;
        $data['rota'] = $this->generateUrl('menu_edit',['id' => $request->get('id')]);
        $data['modulos'] = $this->getRepositorio(\App\Entity\Modulo::class)->findAll();
        
        return $this->render('menu/form.html.twig', $data);
    }

    /**
     * @Route("/listamenu", name="leftMenu")
    */
    public function getMenu(Request $request): Response
    {

        $this->restauraLoginDB();

        $data = [];

        // Usuario logado
        $usuarioLogado = $this->getRepositorio(\App\Entity\Usuario::class)->find($request->getSession()->get('userId'));
        // dd($usuarioLogado, $request->getSession()->get('userId'));
        // Pegar o estabelecimento a qual pertence o usuario loado
        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($usuarioLogado->getPetshopId());
        // Pegar o plano que o estabelecimento do usuario logado pertence
        $getPlanoLogado = $this->getRepositorio(\App\Entity\Plano::class)->find($estabelecimento->getPlanoId());

        $plano = json_decode($getPlanoLogado->getDescricao(), true);

        $modulo = [];
        foreach ($plano as $row) {
            $modulo[] = $this->getRepositorio(\App\Entity\Modulo::class)->findOneBy(['descricao' => $row])->getId();
        }

        $listaMenu = $this->getRepositorio(\App\Entity\Menu::class)->findBy(['parent'=>null]);
        foreach($listaMenu as $menu){
            $dataS = [];
            
            if(in_array($menu->getModulo(), $modulo)){
                $listaSubMenu = $this->getRepositorio(\App\Entity\Menu::class)->findBy(['parent'=>$menu->getId()]);
                if($listaSubMenu){                    
                    foreach($listaSubMenu as $submenu){
                        $dataS[] = $submenu;
                    }
                }

                $data[] = [
                    'menu' => $menu,
                    'submenu' => (!empty($dataS) ? $dataS : false)
                ];
            
            }
        }
        return $this->render('left-menu.html.twig', ['menuLateral' => $data]);
    }
}