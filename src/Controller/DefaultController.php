<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Usuario;
use App\Service\FirewallSwitcher;
use App\Service\TempDirManager;
use App\Service\Utils;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Doctrine\Persistence\ManagerRegistry;

class DefaultController extends AbstractController
{
    const NAO_AUTORIZADO = 403;
    const NAO_AUTORIZADO_MENSAGEM = 'Você não tem permissão de acesso a esta página.';

    const NAO_LOCALIZADO = 404;
    const NAO_LOCALIZADO_MENSAGEM = 'A página que você procura não existe, favor listar sua página apartir dos links no menu lateral.';

    const BAD_AUTHENTICATE = 419;
    const BAD_AUTHENTICATE_MENSAGEM = 'Página expirada, favor faça a autenticação e tente novamente.';

    const BAD_REQUESTS = 429;
    const BAD_REQUESTS_MENSAGEM = 'Houve muitas requisições, o sistema entendeu que foi necessário fazer uma parada brusca para não ocorrer algo crítico.';

    const BAD_SERVIDOR = 500;
    const BAD_SERVIDOR_MENSAGEM = 'Houve um problema interno no servidor, tente novamente mais tarde!';

    const UNAVAILABLE_SERVICE = 503;
    const UNAVAILABLE_SERVICE_MENSAGEM = 'Serviço indisponível.';

    public $data;
    public $user;
    public $listaMenus;
    public $titlePage;
    public $directoryMananger;
    public $managerRegistry;
    protected $security;
    protected $securityMessage = 'Você não tem permissão de acesso a esta página.';
    protected $rule = 'IS_AUTHENTICATED';
    protected $firewallSwitcher = 'IS_AUTHENTICATED';
    protected $cnpj = '##.###.###/####-##';
    protected $cpf = '###.###.###-##';

    /**
     * @param SessionInterface $session
     * @param Security $security
     * @param TempDirManager $tempDirManager
     */
    public function __construct(
        ?Security       $security,
        ManagerRegistry $managerRegistry
    )
    {
        $this->security = $security;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param $class
     * @return ObjectRepository
     */
    public function getRepositorio($class): ObjectRepository
    {
        return $this->managerRegistry->getRepository($class);
    }

    private function extractSession($request)
    {
        if ($request->getSession()->get('login')) {
            foreach ($request->getSession()->all() as $key => $value) {
                $this->data[$key] = $value;
            }
        }
    }

    protected function buildMenuTree($menus, $parentId = null)
    {
        $listaMenu = [];

        foreach ($menus as $menu) {
            if ($menu['father'] == $parentId) {
                $children = $this->buildMenuTree($menus, $menu['idMenu']);
                if ($children) {
                    $menu['children'] = $children;
                }
                $listaMenu[] = $menu;
            }
        }

        return $listaMenu;
    }

    protected function menuPermission($idGrupo, $routeName)
    {
        $grupo = [];
        foreach ($this->getRepositorio(Menu::class)->menuPermission($routeName) as $values) {
            $grupo[] = $values['idGrupo'];
        }

        if (in_array($idGrupo, $grupo)) {
            return true;
        }

        return false;
    }

    protected function filterSecurity($numbers, $aumentaConta = 1)
    {
        // $numbers = \App\Service\Utils::str_to_integer($numbers);
        $nNumber = '';
        $totalChars = strlen($numbers);
        $conta = intval(ceil(($totalChars / 2) / 2));

        for ($i = 0; $i <= $totalChars - 1; $i++) {
            if ($i >= $conta && $i <= ($totalChars - ($conta + $aumentaConta))) {
                $numbers[$i] = '*';
            }
            $nNumber .= $numbers[$i];
        }

        if (is_int($numbers) && strlen($numbers) == 14) {
            return (\App\Service\Utils::mask($nNumber, $this->cnpj));
        }

        if (is_int($numbers) && strlen($numbers) == 11) {
            return (\App\Service\Utils::mask($nNumber, $this->cpf));
        }
        return $nNumber;
        // return (\App\Service\Utils::mask($nNumber, $this->{$tipo}));
    }

    /**
     * @return mixed|string
     */
    public function runtime()
    {
        $sec = explode(" ", microtime());
        return $sec[1] + $sec[0];
    }

    /**
     * @param $request
     * @return void
     */
    protected function dataSession($request)
    {
        if ($request->getSession()->has('login')) {
            $this->data['user'] = $request->getSession()->all();
        }
    }

    public function getRule()
    {
        return $this->rule;
    }
}
