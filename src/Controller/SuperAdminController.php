<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Invoice;
use App\Repository\EstabelecimentoRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
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
    public function index(Request $request, InvoiceService $invoiceService): Response
    {
        // Verifica se o usuário é super admin
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $hoje = new \DateTime();
        $inicioMes = new \DateTime('first day of this month');
        $fimMes = new \DateTime('last day of this month');

        // Buscar todos estabelecimentos
        $estabelecimentos = $this->getRepositorio(\App\Entity\Estabelecimento::class)->findAll();

        // Separar estabelecimentos por status
        $novos = [];
        $ativos = [];
        $expirados = [];
        $proximos_vencer = [];

        foreach ($estabelecimentos as $est) {
            $diasCadastro = $hoje->diff($est->getDataCadastro())->days;
            $diasExpiracao = $hoje->diff($est->getDataPlanoFim())->days;
            $dataPlanoFim = $est->getDataPlanoFim();

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

        // Estatísticas de invoices
        $invoiceStats = $invoiceService->getInvoiceStats();

        // Receita do mês
        $receitaMes = $this->getRepositorio(\App\Entity\Invoice::class)->getTotalReceitaMes($inicioMes, $fimMes);

        // Últimos invoices
        $ultimosInvoices = $this->getRepositorio(\App\Entity\Invoice::class)->createQueryBuilder('i')
            ->orderBy('i.dataEmissao', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

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
        // dd($data);

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
        $invoices = $this->getRepositorio(\App\Entity\Invoice::class)->findByEstabelecimento($id);

        // Calcular dias até expiração
        $hoje = new \DateTime();
        $diasExpiracao = $hoje->diff($estabelecimento->getDataPlanoFim())->days;
        $expirado = $estabelecimento->getDataPlanoFim() < $hoje;

        $data = [

            'estabelecimento' => $estabelecimento,
            'invoices' => $invoices,
            'dias_expiracao' => $diasExpiracao,
            'expirado' => $expirado,
        ];
        // dd($data);

        return $this->render('superadmin/estabelecimento_detalhes.html.twig', $data);
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

        $qb = $this->getRepositorio(\App\Entity\Invoice::class)->createQueryBuilder('i');

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

        $invoice = $this->getRepositorio(\App\Entity\Invoice::class)->find($id);
        
        if (!$invoice) {
            return new JsonResponse(['success' => false, 'error' => 'Invoice não encontrado'], 404);
        }

        try {
            $this->invoiceService->markAsPaid($invoice, [
                'payment_method' => 'manual',
                'marked_by' => $this->getUser()->getId(),
            ]);

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
        
        // Receita por mês do ano
        $receitaPorMes = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = new \DateTime("{$ano}-{$mes}-01");
            $fim = new \DateTime($inicio->format('Y-m-t'));
            
            $receita = $this->getRepositorio(\App\Entity\Invoice::class)->getTotalReceitaMes($inicio, $fim);
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
     * 
     * Permite que o Super Admin acesse temporariamente um estabelecimento
     * para visualizar/gerenciar como se fosse um usuário daquele estabelecimento
     */
    public function acessarEstabelecimento(Request $request, int $id, EstabelecimentoRepository $estabelecimentoRepository): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        $estabelecimento = $estabelecimentoRepository->find($id);
        
        if (!$estabelecimento) {
            $this->addFlash('error', 'Estabelecimento não encontrado.');
            return $this->redirectToRoute('superadmin_dashboard');
        }

        // Salvar contexto original do Super Admin
        $request->getSession()->set('superadmin_original_context', [
            'estabelecimentoId' => $request->getSession()->get('estabelecimentoId'),
            'isSuperAdmin' => true,
            'accessLevel' => $request->getSession()->get('accessLevel'),
        ]);

        // Temporariamente "se passar" por um usuário do estabelecimento
        $request->getSession()->set('estabelecimentoId', $id);
        $request->getSession()->set('isSuperAdmin', false);
        $request->getSession()->set('accessLevel', 'Admin'); // Acesso como admin do estabelecimento
        $request->getSession()->set('impersonating_establishment', true);

        $this->addFlash('info', "Você está acessando o estabelecimento: {$estabelecimento->getRazaoSocial()}");

        // Redirecionar para o dashboard do estabelecimento
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/voltar-superadmin", name="superadmin_voltar")
     * 
     * Volta para o contexto original do Super Admin após acessar um estabelecimento
     */
    public function voltarSuperAdmin(Request $request): Response
    {
        if (!$request->getSession()->has('superadmin_original_context')) {
            // Não estava acessando estabelecimento
            return $this->redirectToRoute('superadmin_dashboard');
        }

        // Restaurar contexto original
        $originalContext = $request->getSession()->get('superadmin_original_context');
        
        $request->getSession()->set('estabelecimentoId', $originalContext['estabelecimentoId']);
        $request->getSession()->set('isSuperAdmin', $originalContext['isSuperAdmin']);
        $request->getSession()->set('accessLevel', $originalContext['accessLevel']);
        $request->getSession()->remove('impersonating_establishment');
        $request->getSession()->remove('superadmin_original_context');

        $this->addFlash('success', 'Você voltou para a visualização de Super Admin.');

        return $this->redirectToRoute('superadmin_dashboard');
    }
}