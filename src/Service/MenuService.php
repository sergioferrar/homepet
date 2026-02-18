<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class MenuService
{
    private EntityManagerInterface $em;
    private Security $security;
    private ManagerRegistry $managerRegistry;
    private RequestStack $requestStack;

    public function __construct(
        Security $security,
        EntityManagerInterface $em,
        ManagerRegistry $managerRegistry,
        RequestStack $requestStack
    ) {
        $this->security        = $security;
        $this->em              = $em;
        $this->managerRegistry = $managerRegistry;
        $this->requestStack    = $requestStack;
    }

    public function getUser()
    {
        return $this->security->getUser();
    }

    /**
     * Retorna true quando o Super Admin está navegando COMO um estabelecimento.
     * Nesse caso o menu deve ser o do estabelecimento alvo, não o menu vazio do SA.
     */
    private function isImpersonating(): bool
    {
        $session = $this->requestStack->getSession();
        return (bool) $session->get('impersonating_establishment');
    }

    /**
     * ID do estabelecimento que o SA está acessando no momento.
     * Lê as duas variantes de chave para compatibilidade.
     */
    private function getImpersonatingId(): ?int
    {
        $session = $this->requestStack->getSession();
        $id = $session->get('estabelecimento_id') ?? $session->get('estabelecimentoId');
        return $id ? (int) $id : null;
    }

    public function getMenu(): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [];
        }

        $isSuperAdmin  = in_array('ROLE_SUPER_ADMIN', $user->getRoles());
        $impersonating = $this->isImpersonating();

        // SA puro (sem impersonation): sem menu dinamico - usa menu proprio da sidebar
        if ($isSuperAdmin && !$impersonating) {
            return [];
        }

        // Garante que estamos lendo da base principal (nao de um tenant)
        (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();

        // Resolve o ID do estabelecimento
        // Impersonation: ID vem da sessao (estabelecimento alvo do SA)
        // Usuario normal: ID vem do petshop_id do proprio usuario no banco
        if ($isSuperAdmin && $impersonating) {
            $petshopId = $this->getImpersonatingId();

            if (!$petshopId) {
                return [];
            }
        } else {
            $usuarioLogado = $this->em
                ->getRepository(\App\Entity\Usuario::class)
                ->find($user->getId());

            if (!$usuarioLogado) {
                return [];
            }

            $petshopId = $usuarioLogado->getPetshopId();

            if (!$petshopId) {
                return [];
            }
        }

        // Carrega estabelecimento e plano
        $estabelecimento = $this->em
            ->getRepository(\App\Entity\Estabelecimento::class)
            ->find($petshopId);

        if (!$estabelecimento) {
            return [];
        }

        $planoId = $estabelecimento->getPlanoId();

        if (!$planoId) {
            return [];
        }

        $plano = $this->em
            ->getRepository(\App\Entity\Plano::class)
            ->find($planoId);

        // Monta lista de modulos habilitados pelo plano
        $modulo = [];

        if ($plano) {
            $descricao = $plano->getDescricao();

            if ($descricao) {
                $modulosPlano = json_decode($descricao, true);

                if (is_array($modulosPlano)) {
                    foreach ($modulosPlano as $row) {
                        $moduloEntity = $this->em
                            ->getRepository(\App\Entity\Modulo::class)
                            ->findOneBy(['descricao' => $row]);

                        if ($moduloEntity) {
                            $modulo[] = $moduloEntity->getId();
                        }
                    }
                }
            }
        }

        if (empty($modulo)) {
            return [];
        }

        // Monta a arvore de menus
        $data      = [];
        $listaMenu = $this->em
            ->getRepository(\App\Entity\Menu::class)
            ->findBy(['parent' => null], ['ordem' => 'ASC']);

        foreach ($listaMenu as $menu) {
            if (!in_array($menu->getModulo(), $modulo)) {
                continue;
            }

            $dataS        = [];
            $listaSubMenu = $this->em
                ->getRepository(\App\Entity\Menu::class)
                ->findBy(['parent' => $menu->getId()], ['ordem' => 'ASC']);

            foreach ($listaSubMenu as $submenu) {
                $dataS[] = $submenu;
            }

            $data[] = [
                'menu'    => $menu,
                'submenu' => !empty($dataS) ? $dataS : false,
                'rota'    => null,
            ];
        }

        return $data;
    }
}