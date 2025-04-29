<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Service\DatabaseBkp;
use App\Service\TempDirManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\DynamicConnectionManager;

class DefaultController extends AbstractController
{
    public $data;
    public $user;
    public $managerRegistry;
    protected $security;
    protected $rule = 'IS_AUTHENTICATED';
    protected $cnpj = '##.###.###/####-##';
    protected $cpf = '###.###.###-##';
    protected $request;
    protected $session;
    public $tempDirManager;
    public $databaseBkp;

    /**
     * @param Security $security
     */
    public function __construct(?Security $security, ManagerRegistry $managerRegistry, RequestStack $request, TempDirManager $tempDirManager, DatabaseBkp $databaseBkp)
    {
        $this->security = $security;
        $this->managerRegistry = $managerRegistry;
        $this->request = $request->getCurrentRequest();
        $this->session = $this->request->getSession();
        $this->tempDirManager = $tempDirManager;
        $this->databaseBkp = $databaseBkp;
    }

    public function switchDB():void
    {   
        $conexao = $this->managerRegistry->getConnection()->getParams();
        
        if($conexao['host'] == '127.0.0.1'){
            $estabelecimentoId = "homepet_{$this->session->get('userId')}";
        } else {
            $estabelecimentoId = "u199209817_{$this->session->get('userId')}";
        }

        (new DynamicConnectionManager($this->managerRegistry))->switchDatabase($estabelecimentoId, $estabelecimentoId);
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