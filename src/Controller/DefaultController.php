<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Service\DatabaseBkp;
use App\Service\TempDirManager;
use App\Service\CaixaService;
use App\Service\PdvService;
use App\Service\TenantContext;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
    protected $requestStack;
    protected $request;
    protected $session;
    public $tempDirManager;
    public $databaseBkp;
    public $estabelecimentoId;
    protected $modulosSistema = [
        'agendamentosDePets'   => 'Agendamentos de Pets',
        'cadastroDeClientes'   => 'Cadastro de Clientes',
        'cadastroDePets'       => 'Cadastro de Pets',
        'serviçosDoPetshop'    => 'Serviços do Petshop',
        'áreaDeFinanceiro'     => 'Área de Financeiro',
        'gestãoDeUsuários'     => 'Gestão de Usuários',
        'banhoETosa'           => 'Banho e Tosa',
        'hospedagemDeCães'     => 'Hospedagem de Cães',
        'clínicaVeterinária'   => 'Clínica Veterinária',
    ];

    public EntityManagerInterface $entityManager;
    public LoggerInterface $logger;

    protected PdvService $pdvService;
    protected CaixaService $caixaService;
    protected TenantContext $tenantContext;
    protected EntityManagerInterface $em;

    /**
     * DynamicConnectionManager injetado via DI — instância única por request,
     * compartilhada entre switchDB() e restauraLoginDB().
     */
    protected DynamicConnectionManager $connectionManager;

    public function __construct(
        ?Security               $security,
        ManagerRegistry         $managerRegistry,
        RequestStack            $request,
        TempDirManager          $tempDirManager,
        DatabaseBkp             $databaseBkp,
        EntityManagerInterface  $entityManager,
        LoggerInterface         $logger,
        PdvService              $pdvService,
        CaixaService            $caixaService,
        EntityManagerInterface  $em,
        TenantContext           $tenantContext,
        DynamicConnectionManager $connectionManager   // ← injetado pelo container
    ) {
        date_default_timezone_set('America/Sao_Paulo');
        $this->security            = $security;
        $this->managerRegistry     = $managerRegistry;
        $this->requestStack        = $request;
        $this->request             = $request->getCurrentRequest();
        $this->session             = $this->request->getSession();
        $this->tempDirManager      = $tempDirManager;
        $this->databaseBkp         = $databaseBkp;
        $this->estabelecimentoId   = null;  // lazy — só resolve ao chamar getIdBase()
        $this->entityManager       = $entityManager;
        $this->logger              = $logger;
        $this->pdvService          = $pdvService;
        $this->caixaService        = $caixaService;
        $this->tenantContext        = $tenantContext;
        $this->em                  = $em;
        $this->connectionManager   = $connectionManager;
    }

    // =========================================================================
    //  GERENCIAMENTO DE CONEXÃO / TENANT
    // =========================================================================

    /**
     * Ativa o contexto do tenant conforme a estratégia configurada em TENANT_STRATEGY.
     *
     * ── Modo MULTI_DATABASE ──────────────────────────────────────────────────
     *   Troca fisicamente a conexão DBAL para o banco do tenant.
     *   O banco é resolvido na seguinte ordem de prioridade:
     *     1. 'homepet_' + estabelecimento_id da sessão  (usuário normal / impersonation)
     *     2. $_ENV['DBNAMETENANT']                      (fallback de ambiente)
     *
     * ── Modo SINGLE_DATABASE (padrão atual) ──────────────────────────────────
     *   Não troca conexão. Apenas armazena o estabelecimento_id no manager
     *   para que repositórios o usem como filtro WHERE automático.
     *   Super Admins sem estabelecimento ativo ficam sem filtro (acesso total).
     */
    public function switchDB(): void
    {
        $estabelecimentoId = $this->getIdBase(); // lê sessão com fallback snake/camel

        if ($this->connectionManager->isMultiDatabaseMode()) {
            // ── MULTI_DATABASE ────────────────────────────────────────────────
            // Monta o nome do banco: 'homepet_5', 'homepet_12', etc.
            // Usa o ENV como fallback quando não há sessão (ex.: CLI, warm-up).
            $dbName = $estabelecimentoId > 0
                ? 'homepet_' . $estabelecimentoId
                : ($_ENV['DBNAMETENANT'] ?? 'homepet_1');

            $this->connectionManager->setTenant(
                dbNameOrTenant: $dbName,
                estabelecimentoId: $estabelecimentoId ?: null,
                username: $dbName   // mantém a convenção anterior user == dbname
            );

            $this->logger->debug('[DefaultController] switchDB → multi_database', [
                'db'                 => $dbName,
                'estabelecimento_id' => $estabelecimentoId,
            ]);

        } else {
            // ── SINGLE_DATABASE ───────────────────────────────────────────────
            // Apenas registra o estabelecimento_id como filtro de tenant.
            // Super Admin sem estabelecimento selecionado → null (sem filtro).
            $this->connectionManager->setTenant(
                dbNameOrTenant: 'homepet_1',
                estabelecimentoId: $estabelecimentoId > 0 ? $estabelecimentoId : null
            );

            $this->logger->debug('[DefaultController] switchDB → single_database', [
                'estabelecimento_id' => $estabelecimentoId ?: 'sem filtro (super admin)',
            ]);
        }
    }

    /**
     * Restaura o contexto padrão (banco original + remove filtro de tenant).
     *
     * No modo multi_database: reconecta ao banco definido em DATABASE_URL.
     * No modo single_database: limpa o estabelecimento_id do manager.
     */
    public function restauraLoginDB(): void
    {
        $this->connectionManager->restoreTenant();

        $this->logger->debug('[DefaultController] restauraLoginDB', [
            'strategy' => $this->connectionManager->getStrategy(),
        ]);
    }

    /**
     * Expõe o manager para controllers filhos que precisem verificar
     * o estabelecimento_id ativo (modo single_database) ou o banco atual.
     *
     * Exemplo em um controller filho:
     *   if ($this->connectionManager()->hasTenantFilter()) {
     *       $id = $this->connectionManager()->getCurrentEstabelecimentoId();
     *   }
     */
    public function connectionManager(): DynamicConnectionManager
    {
        return $this->connectionManager;
    }

    // =========================================================================
    //  REPOSITÓRIOS
    // =========================================================================

    /**
     * @param class-string $class
     */
    public function getRepositorio($class): ObjectRepository
    {
        return $this->managerRegistry->getRepository($class);
    }

    // =========================================================================
    //  SESSÃO / DADOS DO USUÁRIO
    // =========================================================================

    private function extractSession($request): void
    {
        if ($request->getSession()->get('login')) {
            foreach ($request->getSession()->all() as $key => $value) {
                $this->data[$key] = $value;
            }
        }
    }

    protected function dataSession($request): void
    {
        if ($request->getSession()->has('login')) {
            $this->data['user'] = $request->getSession()->all();
        }
    }

    /**
     * Retorna o ID do estabelecimento da sessão.
     *
     * Lê as duas variantes de chave que coexistem no sistema:
     *   - 'estabelecimento_id' (snake_case) → gravada pelo CustomAuthenticator e TenantContext
     *   - 'estabelecimentoId'  (camelCase)  → gravada pelo LoginController e SuperAdminController
     *
     * No modo impersonation do Super Admin, ambas são populadas
     * pelo acessarEstabelecimento() com o ID do estabelecimento alvo.
     *
     * Retorna 0 quando nenhuma chave está presente (Super Admin sem contexto ativo).
     */
    public function getIdBase(): int
    {
        if ($this->estabelecimentoId === null) {
            $id = $this->session->get('estabelecimento_id')   // snake_case (CustomAuthenticator)
               ?? $this->session->get('estabelecimentoId');   // camelCase  (LoginController)

            $this->estabelecimentoId = (int) ($id ?? 0);
        }

        return $this->estabelecimentoId;
    }

    // =========================================================================
    //  HELPERS GERAIS
    // =========================================================================

    protected function buildMenuTree($menus, $parentId = null): array
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

    protected function menuPermission($idGrupo, $routeName): bool
    {
        $grupo = [];
        foreach ($this->getRepositorio(Menu::class)->menuPermission($routeName) as $values) {
            $grupo[] = $values['idGrupo'];
        }

        return in_array($idGrupo, $grupo);
    }

    protected function filterSecurity($numbers, $aumentaConta = 1): string
    {
        $nNumber    = '';
        $totalChars = strlen($numbers);
        $conta      = intval(ceil(($totalChars / 2) / 2));

        for ($i = 0; $i <= $totalChars - 1; $i++) {
            if ($i >= $conta && $i <= ($totalChars - ($conta + $aumentaConta))) {
                $numbers[$i] = '*';
            }
            $nNumber .= $numbers[$i];
        }

        if (is_int($numbers) && strlen($numbers) == 14) {
            return \App\Service\Utils::mask($nNumber, $this->cnpj);
        }

        if (is_int($numbers) && strlen($numbers) == 11) {
            return \App\Service\Utils::mask($nNumber, $this->cpf);
        }

        return $nNumber;
    }

    public function runtime(): float
    {
        $sec = explode(' ', microtime());
        return $sec[1] + $sec[0];
    }

    public function getRule(): string
    {
        return $this->rule;
    }

    public function verificarPlanoPorPeriodo($dataInicio, $dataFim): string|false
    {
        if ($dataInicio && $dataFim) {
            $hoje = new \DateTime();
            if ($hoje > $dataFim) {
                return "Seu plano expirou em " . $dataFim->format('d/m/Y') . ". Por favor, renove seu plano.";
            }
        }

        return false;
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
            $insert     = $op['insert']     ?? '';
            $attributes = $op['attributes'] ?? [];

            if (isset($attributes['bold']))      { $insert = "<strong>{$insert}</strong>"; }
            if (isset($attributes['italic']))     { $insert = "<em>{$insert}</em>"; }
            if (isset($attributes['underline']))  { $insert = "<u>{$insert}</u>"; }

            if (isset($attributes['header'])) {
                $level  = $attributes['header'];
                $insert = "<h{$level}>{$insert}</h{$level}>";
            }

            if (isset($attributes['list'])) {
                $listType = $attributes['list'] === 'ordered' ? 'ol' : 'ul';
                $html    .= "<{$listType}><li>{$insert}</li></{$listType}>";
                continue;
            }

            $html .= nl2br($insert);
        }

        return $html;
    }

    protected function utils(): \App\Service\Utils
    {
        return new \App\Service\Utils();
    }

    // =========================================================================
    //  MENSAGENS DE FEEDBACK
    // =========================================================================

    protected function infor($message): void
    {
        $this->message = $message;
        $this->status  = 'info';
        $this->error   = false;
    }

    protected function alert($message): void
    {
        $this->message = $message;
        $this->status  = 'warning';
        $this->error   = false;
    }

    protected function sucesso($message): void
    {
        $this->message = $message;
        $this->status  = 'success';
        $this->error   = false;
    }

    protected function erro($message): void
    {
        $this->message = $message;
        $this->status  = 'danger';
        $this->error   = true;
    }

    protected function message(string $dialog, string $status): array
    {
        $this->$status($dialog);

        return [
            'message' => $this->message,
            'status'  => $this->status,
            'error'   => $this->error,
        ];
    }
}