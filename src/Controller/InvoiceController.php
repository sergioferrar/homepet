<?php

namespace App\Controller;

use App\Entity\Fatura;
use App\Entity\Estabelecimento;
use App\Repository\InvoiceRepository;
use App\Repository\EstabelecimentoRepository;
use App\Service\InvoiceService;
use App\Service\Payment\PaymentGatewayFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/invoice")
 */
class InvoiceController extends DefaultController
{


    /**
     * @Route("/minhas", name="invoice_minhas")
     */
    public function minhasInvoices(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Buscar estabelecimento do usuário
        $estabelecimentoId = $user->getPetshopId();
        
        $invoices = $this->getRepositorio(\App\Entity\Fatura::class)->findByEstabelecimento($estabelecimentoId);

        return $this->render('invoice/minhas.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * @Route("/detalhes/{id}", name="invoice_detalhes")
     */
    public function detalhes(Request $request, int $id): Response
    {
        $invoice = $this->getRepositorio(\App\Entity\Fatura::class)->find($id);
        
        if (!$invoice) {
            throw $this->createNotFoundException('Invoice não encontrado');
        }

        // Verifica se o usuário tem permissão para ver este invoice
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $user->getPetshopId() !== $invoice->getEstabelecimentoId()) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        return $this->render('invoice/detalhes.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * @Route("/pagar/{id}", name="invoice_pagar")
     */
    public function pagar(Request $request, int $id, EstabelecimentoRepository $estabelecimentoRepository,PaymentGatewayFactory $paymentGatewayFactory): Response
    {
        $invoice = $this->getRepositorio(\App\Entity\Fatura::class)->find($id);
        
        if (!$invoice) {
            throw $this->createNotFoundException('Invoice não encontrado');
        }

        if ($invoice->getStatus() === 'pago') {
            $this->addFlash('warning', 'Esta fatura já foi paga.');
            return $this->redirectToRoute('invoice_detalhes', ['id' => $id]);
        }

        // Buscar estabelecimento
        $estabelecimento = $estabelecimentoRepository->find($invoice->getEstabelecimentoId());
        $plano = $this->getRepositorio(\App\Entity\Plano::class)->find($invoice->getPlanoId());

        // Criar pagamento recorrente
        $gateway = $paymentGatewayFactory->getDefaultGateway();

        $paymentData = [
            'title' => "Assinatura - {$plano->getTitulo()}",
            'price' => $invoice->getValorLiquido(),
            'email' => $this->getUser()->getEmail(),
            'external_reference' => $invoice->getId(),
            'comprador' => [
                'name' => $estabelecimento->getRazaoSocial(),
            ],
        ];

        try {
            $result = $gateway->createSubscription($paymentData);

            if ($result['success']) {
                // Atualizar invoice com subscription_id
                $invoice->setSubscriptionId($result['subscription_id']);
                $invoice->setPaymentGateway($gateway->getGatewayName());
                $this->getRepositorio(\App\Entity\Fatura::class)->add($invoice, true);

                // Redirecionar para página de pagamento
                return $this->redirect($result['init_point']);
            } else {
                $this->addFlash('error', 'Erro ao criar pagamento: ' . ($result['error'] ?? 'Erro desconhecido'));
                return $this->redirectToRoute('invoice_detalhes', ['id' => $id]);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro ao processar pagamento: ' . $e->getMessage());
            return $this->redirectToRoute('invoice_detalhes', ['id' => $id]);
        }
    }

    /**
     * @Route("/renovar-assinatura", name="invoice_renovar_assinatura")
     */
    public function renovarAssinatura(Request $request, EstabelecimentoRepository $estabelecimentoRepository, InvoiceService $invoiceService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $estabelecimento = $estabelecimentoRepository->find($user->getPetshopId());
        
        if (!$estabelecimento) {
            throw $this->createNotFoundException('Estabelecimento não encontrado');
        }

        $plano = $this->getRepositorio(\App\Entity\Plano::class)->find($estabelecimento->getPlanoId());

        // Criar invoice de renovação
        $invoice = $invoiceService->renewSubscription(
            $estabelecimento,
            $plano->getId(),
            $plano->getValor()
        );

        $this->addFlash('success', 'Invoice de renovação criado com sucesso!');
        
        // Redirecionar para página de pagamento
        return $this->redirectToRoute('invoice_pagar', ['id' => $invoice->getId()]);
    }

    /**
     * @Route("/criar", name="invoice_criar", methods={"POST"})
     */
    public function criar(Request $request, EstabelecimentoRepository $estabelecimentoRepository, InvoiceService $invoiceService): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }

        $estabelecimentoId = $request->request->get('estabelecimento_id');
        $planoId = $request->request->get('plano_id');
        $valorTotal = $request->request->get('valor_total');
        $tipo = $request->request->get('tipo', 'assinatura');

        $estabelecimento = $estabelecimentoRepository->find($estabelecimentoId);
        
        if (!$estabelecimento) {
            return new JsonResponse(['success' => false, 'error' => 'Estabelecimento não encontrado'], 404);
        }

        try {
            $invoice = $invoiceService->createInvoice($estabelecimento, [
                'tipo' => $tipo,
                'valor_total' => $valorTotal,
                'plano_id' => $planoId,
            ]);

            return new JsonResponse([
                'success' => true,
                'invoice_id' => $invoice->getId(),
                'numero_invoice' => $invoice->getNumeroInvoice(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/download/{id}", name="invoice_download")
     */
    public function download(Request $request, int $id): Response
    {
        $invoice = $this->getRepositorio(\App\Entity\Fatura::class)->find($id);
        
        if (!$invoice) {
            throw $this->createNotFoundException('Invoice não encontrado');
        }

        // Verifica permissão
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $user->getPetshopId() !== $invoice->getEstabelecimentoId()) {
            throw $this->createAccessDeniedException('Acesso negado');
        }

        // TODO: Implementar geração de PDF
        // Por enquanto, retorna HTML
        return $this->render('invoice/pdf.html.twig', [
            'invoice' => $invoice,
        ]);
    }
}
