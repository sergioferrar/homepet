<?php

namespace App\Service;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;

class MenuService{


	private EntityManagerInterface $em;
	private Security $security;
	private ManagerRegistry $managerRegistry;
	
	public function __construct(Security $security, EntityManagerInterface $em, ManagerRegistry $managerRegistry) {
		$this->security = $security; // ← CORRIGIDO: minúsculo
		$this->em = $em;
		$this->managerRegistry = $managerRegistry;
	}

	public function getUser()
    {
        return $this->security->getUser();
    }

	public function getMenu()
    {
        $user = $this->security->getUser();

        // Se não há usuário, retornar vazio
        if (!$user) {
            return [];
        }

        $data = [];

        // Verificar se é Super Admin
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            // Super Admin: retornar vazio (menu é carregado no template próprio)
            return [];
        }
        
        (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();
        
        // Usuários normais: buscar usuário completo
        $usuarioLogado = $this->em->getRepository(\App\Entity\Usuario::class)->find($user->getId());
        
        if (!$usuarioLogado) {
            return [];
        }
        
        $modulo = [];
        
        // Pegar o estabelecimento a qual pertence o usuario logado
        $petshopId = $usuarioLogado->getPetshopId();
        
        if (!$petshopId) {
            return [];
        }
        
        $estabelecimento = $this->em->getRepository(\App\Entity\Estabelecimento::class)->find($petshopId);
        
        if (!$estabelecimento) {
            return [];
        }
        
        // Pegar o plano que o estabelecimento do usuario logado pertence
        $planoId = $estabelecimento->getPlanoId();
        
        if (!$planoId) {
            return [];
        }
        
        $getPlanoLogado = $this->em->getRepository(\App\Entity\Plano::class)->find($planoId);

        if ($getPlanoLogado) {
            $descricao = $getPlanoLogado->getDescricao();
            
            if ($descricao) {
                $plano = json_decode($descricao, true);

                if (is_array($plano)) {
                    foreach ($plano as $row) {
                        $moduloEntity = $this->em->getRepository(\App\Entity\Modulo::class)->findOneBy(['descricao' => $row]);
                        if ($moduloEntity) {
                            $modulo[] = $moduloEntity->getId();
                        }
                    }
                }
            }
        }

        // Se não tem módulos, retornar vazio
        if (empty($modulo)) {
            return [];
        }

        $listaMenu = $this->em->getRepository(\App\Entity\Menu::class)->findBy(['parent' => null], ['ordem' => 'ASC']);
        
        foreach ($listaMenu as $menu) {
            $dataS = [];

            if (in_array($menu->getModulo(), $modulo)) {
                $listaSubMenu = $this->em->getRepository(\App\Entity\Menu::class)->findBy(['parent' => $menu->getId()], ['ordem' => 'ASC']);
                if ($listaSubMenu) {
                    foreach ($listaSubMenu as $submenu) {
                        $dataS[] = $submenu;
                    }
                }

                $data[] = [
                    'menu' => $menu,
                    'submenu' => (!empty($dataS) ? $dataS : false),
                    'rota' => null
                ];
            }
        }

        return $data;
    }
}