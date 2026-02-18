<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Fatura;
use App\Repository\InvoiceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/superadmin")
 */
class SuperAdminController extends DefaultController
{
    /**
     * @Route("/dashboard", name="superadmin_dashboard")
     */
    public function index(Request $request): Response
    {
        // Verifica se o usuário é super admin
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $hoje = new \DateTime();
        $inicioMes = new \DateTime('first day of this month');
        $fimMes = new \DateTime('last day of this month');

        // Buscar todos estabelecimentos usando método otimizado do repository
        $estabelecimentoRepo = $this->getRepositorio(Estabelecimento::class);
        
        // Usa método customizado se existir, senão usa QueryBuilder
        if (method_exists($estabelecimentoRepo, 'listaEstabelecimentosGestao')) {
            $estabelecimentos = $estabelecimentoRepo->listaEstabelecimentosGestao();
        } else {
            $estabelecimentos = $estabelecimentoRepo
                ->createQueryBuilder('e')
                ->select('e.id, e.nome, e.dataCadastro, e.dataPlanoFim, e.status')
                ->getQuery()
                ->getArrayResult();
        }

        // Separar estabelecimentos por status
        $novos = [];
        $ativos = [];
        $expirados = [];
        $proximos_vencer = [];

        foreach ($estabelecimentos as $est) {
            // Agora $est é um array, não objeto
            $diasCadastro = $hoje->diff(new \DateTime($est['dataCadastro']))->days;
            
            $dataPlanoFim = new \DateTime($est['dataPlanoFim']);
            $diasExpiracao = $hoje->diff($dataPlanoFim)->days;
            

            // Novos (menos de 30 dias)
            if ($diasCadastro <= 30) {
                $novos[] = $est;
            }

            // Expirados
            if ($dataPlanoFim < $hoje) {
                $expirados[] = $est;
            }
            // Próximos a vencer (entre 0 e 15 dias)
            elseif ($diasExpiracao <= 15) {
                $proximos_vencer[] = $est;
            }
            // Ativos
            else {
                $ativos[] = $est;
            }
        }

        // Estatísticas de invoices (SIMPLIFICADO - sem InvoiceService)
        $invoiceRepository = $this->getRepositorio(\App\Entity\Fatura::class);
        
        try {
            // Buscar estatísticas direto do repository
            $invoicesPorStatus = $invoiceRepository->getInvoicesPorStatus();
            
            $invoiceStats = [
                'total_pendente' => 0,
                'total_pago' => 0,
                'valor_pendente' => 0,
                'valor_recebido' => 0,
            ];
            
            foreach ($invoicesPorStatus as $stat) {
                if ($stat['status'] === 'pendente') {
                    $invoiceStats['total_pendente'] = $stat['quantidade'];
                    $invoiceStats['valor_pendente'] = $stat['total'] ?? 0;
                } elseif ($stat['status'] === 'pago') {
                    $invoiceStats['total_pago'] = $stat['quantidade'];
                    $invoiceStats['valor_recebido'] = $stat['total'] ?? 0;
                }
            }
        } catch (\Exception $e) {
            // Se falhar, usar valores padrão
            $invoiceStats = [
                'total_pendente' => 0,
                'total_pago' => 0,
                'valor_pendente' => 0,
                'valor_recebido' => 0,
            ];
        }

        // Receita do mês
        try {
            $receitaMes = $invoiceRepository->getTotalReceitaMes($inicioMes, $fimMes);
        } catch (\Exception $e) {
            $receitaMes = 0;
        }

        // Últimos invoices
        try {
            $ultimosInvoices = $invoiceRepository->createQueryBuilder('i')
                ->orderBy('i.dataEmissao', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            $ultimosInvoices = [];
        }

        $data = [
            'total_estabelecimentos' => count($estabelecimentos),
            'novos_cadastros' => count($novos),
            'ativos' => count($ativos),
            'expirados' => count($expirados),
            'proximos_vencer' => count($proximos_vencer),
            'receita_mes' => number_format($receitaMes ?? 0, 2, ',', '.'),
            'invoices_pendentes' => $invoiceStats['total_pendente'] ?? 0,
            'invoices_pagos' => $invoiceStats['total_pago'] ?? 0,
            'valor_pendente' => number_format($invoiceStats['valor_pendente'] ?? 0, 2, ',', '.'),
            'valor_recebido' => number_format($invoiceStats['valor_recebido'] ?? 0, 2, ',', '.'),
            'estabelecimentos_novos' => $novos,
            'estabelecimentos_expirados' => $expirados,
            'estabelecimentos_proximos_vencer' => $proximos_vencer,
            'ultimos_invoices' => $ultimosInvoices,
        ];

        return $this->render('superadmin/dashboard.html.twig', $data);
    }

    /**
     * @Route("/estabelecimentos", name="superadmin_estabelecimentos")
     */
    public function estabelecimentos(Request $request): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $filtro = $request->query->get('filtro', 'todos');
        $hoje = new \DateTime();

        $qb = $this->getRepositorio(\App\Entity\Estabelecimento::class)->createQueryBuilder('e');

        switch ($filtro) {
            case 'novos':
                $qb->where('e.dataCadastro >= :data')
                   ->setParameter('data', (new \DateTime('-30 days')));
                break;
            case 'ativos':
                $qb->where('e.dataPlanoFim > :hoje')
                   ->andWhere('e.status = :status')
                   ->setParameter('hoje', $hoje)
                   ->setParameter('status', 'Ativo');
                break;
            case 'expirados':
                $qb->where('e.dataPlanoFim < :hoje')
                   ->setParameter('hoje', $hoje);
                break;
            case 'proximos_vencer':
                $qb->where('e.dataPlanoFim BETWEEN :hoje AND :limite')
                   ->setParameter('hoje', $hoje)
                   ->setParameter('limite', (new \DateTime('+15 days')));
                break;
        }

        $qb->orderBy('e.dataCadastro', 'DESC');
        $estabelecimentos = $qb->getQuery()->getResult();

        return $this->render('superadmin/estabelecimentos.html.twig', [
            'estabelecimentos' => $estabelecimentos,
            'filtro_atual' => $filtro,
        ]);
    }

    /**
     * @Route("/estabelecimento/{id}", name="superadmin_estabelecimento_detalhes")
     */
    public function detalhesEstabelecimento(Request $request, int $id): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($id);
        
        if (!$estabelecimento) {
            throw $this->createNotFoundException('Estabelecimento não encontrado');
        }

        // Buscar invoices do estabelecimento
        $invoices = $this->getRepositorio(\App\Entity\Fatura::class)->findByEstabelecimento($id);

        // Calcular dias até expiração
        $hoje = new \DateTime();
        $diasExpiracao = $hoje->diff($estabelecimento->getDataPlanoFim())->days;
        $expirado = $estabelecimento->getDataPlanoFim() < $hoje;

        return $this->render('superadmin/estabelecimento_detalhes.html.twig', [
            'estabelecimento' => $estabelecimento,
            'invoices' => $invoices,
            'dias_expiracao' => $diasExpiracao,
            'expirado' => $expirado,
        ]);
    }

    /**
     * @Route("/invoices", name="superadmin_invoices")
     */
    public function invoices(Request $request): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $status = $request->query->get('status', 'todos');

        $qb = $this->getRepositorio(\App\Entity\Fatura::class)->createQueryBuilder('i');

        if ($status !== 'todos') {
            $qb->where('i.status = :status')
               ->setParameter('status', $status);
        }

        $qb->orderBy('i.dataEmissao', 'DESC');
        $invoices = $qb->getQuery()->getResult();

        return $this->render('superadmin/invoices.html.twig', [
            'invoices' => $invoices,
            'status_atual' => $status,
        ]);
    }

    /**
     * @Route("/invoice/{id}/marcar-pago", name="superadmin_invoice_marcar_pago", methods={"POST"})
     */
    public function marcarInvoicePago(Request $request, int $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }

        $invoice = $this->getRepositorio(\App\Entity\Fatura::class)->find($id);
        
        if (!$invoice) {
            return new JsonResponse(['success' => false, 'error' => 'Invoice não encontrado'], 404);
        }

        try {
            // Marcar como pago manualmente (SEM InvoiceService)
            $invoice->setStatus('pago');
            $invoice->setDataPagamento(new \DateTime());
            
            $em = $this->getDoctrine()->getManager();
            $em->persist($invoice);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Invoice marcado como pago']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/relatorios", name="superadmin_relatorios")
     */
    public function relatorios(Request $request): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $ano = $request->query->get('ano', date('Y'));
        
        $invoiceRepository = $this->getRepositorio(\App\Entity\Fatura::class);
        
        // Receita por mês do ano
        $receitaPorMes = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = new \DateTime("{$ano}-{$mes}-01");
            $fim = new \DateTime($inicio->format('Y-m-t'));
            
            try {
                $receita = $invoiceRepository->getTotalReceitaMes($inicio, $fim);
            } catch (\Exception $e) {
                $receita = 0;
            }
            
            $receitaPorMes[] = [
                'mes' => $mes,
                'nome_mes' => $inicio->format('M'),
                'receita' => $receita ?? 0,
            ];
        }

        return $this->render('superadmin/relatorios.html.twig', [
            'receita_por_mes' => $receitaPorMes,
            'ano_selecionado' => $ano,
        ]);
    }

    /**
     * @Route("/acessar-estabelecimento/{id}", name="superadmin_acessar_estabelecimento")
     */
    public function acessarEstabelecimento(Request $request, int $id): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($id);
        
        if (!$estabelecimento) {
            $this->addFlash('error', 'Estabelecimento não encontrado.');
            return $this->redirectToRoute('superadmin_dashboard');
        }

        // Salvar TODAS as chaves do contexto original do Super Admin
        // para restauração fiel ao sair do modo impersonation
        $request->getSession()->set('superadmin_original_context', [
            // chave camelCase usada pelo LoginController
            'estabelecimentoId'   => $request->getSession()->get('estabelecimentoId'),
            // chave snake_case usada por DefaultController::getIdBase() e TenantContext
            'estabelecimento_id'  => $request->getSession()->get('estabelecimento_id'),
            'isSuperAdmin'        => true,
            'accessLevel'         => $request->getSession()->get('accessLevel'),
            'user_status'         => $request->getSession()->get('user_status'),
        ]);

        // Carregar configurações do estabelecimento impersonado (mailer, gateway, etc.)
        $configs = $this->getRepositorio(\App\Entity\Config::class)
            ->findBy(['estabelecimento_id' => $id]);

        
        $_ENV['DBNAMETENANT'] = "homepet_{$id}";

        if (!empty($configs)) {
            foreach ($configs as $config) {
                if ($config->getTipo() === 'mailer') {
                    // reaproveitamos os setters de ambiente que o authenticator usa
                }
                // Configurações de gateway de pagamento
                if ($config->getTipo() === 'gateway_payment') {
                    $_ENV[strtoupper($config->getChave())]    = $config->getValor();
                    $_SERVER[strtoupper($config->getChave())] = $config->getValor();
                }
            }
        }

        // Gravar nas DUAS chaves que o sistema consome:
        //   - estabelecimentoId  (camelCase) → lida pelo LoginController e template Twig
        //   - estabelecimento_id (snake_case) → lida por DefaultController::getIdBase()
        //                                       e TenantContext::loadFromSession()
        $request->getSession()->set('estabelecimentoId',  $id);
        $request->getSession()->set('estabelecimento_id', $id);

        // Demais flags de contexto
        $request->getSession()->set('isSuperAdmin',              false);
        $request->getSession()->set('accessLevel',               'Admin');
        $request->getSession()->set('user_status',               'Admin');
        $request->getSession()->set('impersonating_establishment', true);
        $request->getSession()->set('impersonating_name',        $estabelecimento->getRazaoSocial());

        $this->addFlash('info', "Você está acessando o estabelecimento: {$estabelecimento->getRazaoSocial()}");

        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/voltar-superadmin", name="superadmin_voltar")
     */
    public function voltarSuperAdmin(Request $request): Response
    {
        if (!$request->getSession()->has('superadmin_original_context')) {
            return $this->redirectToRoute('superadmin_dashboard');
        }

        // Restaurar TODAS as chaves do contexto original do Super Admin
        $originalContext = $request->getSession()->get('superadmin_original_context');

        // Restaura as duas variantes de chave usadas pelo sistema
        $request->getSession()->set('estabelecimentoId',  $originalContext['estabelecimentoId']  ?? null);
        $request->getSession()->set('estabelecimento_id', $originalContext['estabelecimento_id'] ?? null);

        $request->getSession()->set('isSuperAdmin',  $originalContext['isSuperAdmin']  ?? true);
        $request->getSession()->set('accessLevel',   $originalContext['accessLevel']   ?? 'Super Admin');
        $request->getSession()->set('user_status',   $originalContext['user_status']   ?? 'Super Admin');

        $request->getSession()->remove('impersonating_establishment');
        $request->getSession()->remove('impersonating_name');
        $request->getSession()->remove('superadmin_original_context');

        $this->addFlash('success', 'Você voltou para a visualização de Super Admin.');

        return $this->redirectToRoute('superadmin_dashboard');
    }
}