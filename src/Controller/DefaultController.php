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
    private $status;
    private $message;
    private $error;

    public $data;
    public $user;
    public $managerRegistry;
    protected $security;
    protected $rule = 'IS_AUTHENTICATED';
    protected $cnpj = '##.###.###/####-##';
    protected $cpf = '###.###.###-##';
    protected $requestStack;
    protected $request;
    protected $session;
    public $tempDirManager;
    public $databaseBkp;
    public $estabelecimentoId;
    protected $modulosSistema = [
        'agendamentosDePets' => 'Agendamentos de Pets',
        'cadastroDeClientes' => 'Cadastro de Clientes',
        'cadastroDePets' => 'Cadastro de Pets',
        'serviçosDoPetshop' => 'Serviços do Petshop',
        'áreaDeFinanceiro' => 'Área de Financeiro',
        'gestãoDeUsuários' => 'Gestão de Usuários',
        'banhoETosa' => 'Banho e Tosa',
        'hospedagemDeCães' => 'Hospedagem de Cães',
        'clínicaVeterinária' => 'Clínica Veterinária',
    ];

    /**
     * @param Security $security
     */
    public function __construct(?Security $security, ManagerRegistry $managerRegistry, RequestStack $request, TempDirManager $tempDirManager, DatabaseBkp $databaseBkp)
    {
        date_default_timezone_set('America/Sao_Paulo');  
        $this->security = $security;
        $this->managerRegistry = $managerRegistry;
        $this->requestStack = $request;
        $this->request = $request->getCurrentRequest();
        $this->session = $this->request->getSession();
        $this->tempDirManager = $tempDirManager;
        $this->databaseBkp = $databaseBkp;
        $this->estabelecimentoId = $this->session->get('estabelecimentoId');
    }

    public function switchDB(): void
    {
        $conexao = $this->managerRegistry->getConnection()->getParams();

        $estabelecimentoId = "{$_ENV['DBNAMETENANT']}";

        (new DynamicConnectionManager($this->managerRegistry))->switchDatabase($estabelecimentoId, $estabelecimentoId);
    }

    public function restauraLoginDB(): void
    {
        (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();
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

    public function verificarPlanoPorPeriodo($dataInicio, $dataFim)
    {
        if ($dataInicio && $dataFim) {

            $hoje = new \DateTime();

            // dd($dataInicio, $dataFim    );
            if ($hoje > $dataFim) {
                return "Seu plano expirou em " . $dataFim->format('d/m/Y') . ". Por favor, renove seu plano.";
            }
        }

        return false;
    }

    /**
     * Retorna o ID base da sessão (estabelecimento/usuário)
     */
    public function getIdBase(): int
    {
        return (int)$this->session->get('estabelecimento_id');
    }
    

    protected function quillDeltaToHtml(?string $deltaJson): string
    {
        if (!$deltaJson) {
            return '';
        }

        $delta = json_decode($deltaJson, true);
        if (!$delta || !isset($delta['ops'])) {
            return '';
        }

        $html = '';
        foreach ($delta['ops'] as $op) {
            $insert = $op['insert'] ?? '';
            $attributes = $op['attributes'] ?? [];

            if (isset($attributes['bold'])) {
                $insert = "<strong>{$insert}</strong>";
            }
            if (isset($attributes['italic'])) {
                $insert = "<em>{$insert}</em>";
            }
            if (isset($attributes['underline'])) {
                $insert = "<u>{$insert}</u>";
            }
            if (isset($attributes['header'])) {
                $level = $attributes['header'];
                $insert = "<h{$level}>{$insert}</h{$level}>";
            }
            if (isset($attributes['list'])) {
                $listType = $attributes['list'] === 'ordered' ? 'ol' : 'ul';
                $insert = "<li>{$insert}</li>";
                $html .= "<{$listType}>{$insert}</{$listType}>";
                continue;
            }
            $html .= nl2br($insert);
        }

        return $html;
    }

    protected function infor($message)
    {
        $this->message = $message;
        $this->status = 'info';
        $this->error = false;
    }

    protected function alert($message)
    {
        $this->message = $message;
        $this->status = 'warning';
        $this->error = false;
    }

    protected function sucesso($message)
    {
        $this->message = $message;
        $this->status = 'success';
        $this->error = false;
    }

    protected function erro($message)
    {
        $this->message = $message;
        $this->status = 'danger';
        $this->error = true;
    }

    protected function message(string $dialog, $status)
    {
        $this->$status($dialog);

        return [
            'message' => $this->message,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }

}