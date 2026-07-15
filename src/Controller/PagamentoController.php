<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Service\Payment\MercadoPagoService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PagamentoController extends DefaultController
{

    /**
     * @Route("/pagamento/retorno", name="pagamento_retorno")
     *
     * Retorno síncrono após pagamento (redirect do MP).
     * NÃO depende de sessão — usa external_reference (invoice_id) para ativar o estabelecimento.
     */
    public function notificacao(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        // O fluxo de cadastro usa assinatura recorrente (preapproval), portanto o MP
        // retorna preapproval_id. Pagamentos avulsos retornam payment_id/collection_id.
        $paymentId     = $request->get('payment_id') ?: $request->get('collection_id');
        $preapprovalId = $request->get('preapproval_id');
        $status        = $request->get('status') ?? $request->get('collection_status');
        // O MP já envia o external_reference na própria URL de retorno; usamos como base.
        $externalReference = $request->get('external_reference');

        if (!$paymentId && !$preapprovalId) {
            return new Response('ID de pagamento ausente', 400);
        }

        $emEstabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class);
        $emUsuario = $this->getRepositorio(\App\Entity\Usuario::class);
        $emFatura = $this->getRepositorio(\App\Entity\Fatura::class);

        try {
            // ── 1) Decide a aprovação ──
            // Confia no status que o MP envia no redirect e apenas *reforça* com a
            // consulta à API. A consulta nunca pode derrubar o fluxo nem reverter um
            // pagamento já aprovado; se ela falhar, mantemos a decisão do redirect.
            $aprovado      = in_array($status, ['approved', 'authorized'], true);
            $paymentMethod = null;

            try {
                if ($preapprovalId) {
                    $subscription = $mercadoPagoService->getSubscriptionStatus($preapprovalId);
                    dd($subscription);
                    if (!empty($subscription['success'])) {
                        $statusConsultado  = $subscription['status'] ?? $status;
                        $aprovado          = $aprovado || in_array($statusConsultado, ['authorized', 'approved'], true);
                        $externalReference = $externalReference ?: ($subscription['raw_data']['external_reference'] ?? null);
                    } else {
                        // Ex.: 401 (token de teste consultando assinatura de produção) ou 404 (id inválido).
                        $this->logger->error('Não foi possível validar a assinatura no Mercado Pago.', [
                            'preapproval_id' => $preapprovalId,
                            'http_code'      => $subscription['http_code'] ?? null,
                            'error'          => $subscription['error'] ?? null,
                        ]);
                    }
                } elseif ($paymentId) {
                    $payment = $mercadoPagoService->getPaymentStatus($paymentId);
                    if (!empty($payment['success'])) {
                        $statusConsultado  = $payment['status'] ?? $status;
                        $aprovado          = $aprovado || $statusConsultado === 'approved';
                        $externalReference = $externalReference ?: ($payment['external_reference'] ?? null);
                        $paymentMethod     = $payment['payment_method_id'] ?? null;
                    } else {
                        $this->logger->error('Não foi possível validar o pagamento no Mercado Pago.', [
                            'payment_id' => $paymentId,
                            'http_code'  => $payment['http_code'] ?? null,
                            'error'      => $payment['error'] ?? null,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // A consulta falhou — segue com o status recebido no redirect.
                $this->logger->error('Falha ao consultar status no Mercado Pago.', ['message' => $e->getMessage()]);
            }

            if (!$aprovado) {
                // Pagamento pendente ou recusado — webhook confirmará depois.
                return $this->render('pagamento/pendente.html.twig', [
                    'status' => $status,
                    'mensagem' => 'Seu pagamento está sendo processado. Você receberá um e-mail quando for confirmado.',
                ]);
            }

            // ── 2) Localiza a invoice de forma segura ──
            // O external_reference pode vir como id (inteiro), número da invoice ou um
            // hash gerado pelo próprio MP; por isso NUNCA passamos direto para find().
            $invoice = $this->localizarInvoice($emFatura, $externalReference, $request);

            $eid = null;
            $usuario = null;

            if ($invoice) {
                $eid = $invoice->getEstabelecimentoId();
                $usuario = $emUsuario->findOneBy(['petshop_id' => $eid]);

                // Marca a invoice como paga (não pode derrubar a ativação)
                try {
                    $invoiceService = $this->container->get(\App\Service\InvoiceService::class);
                    $invoiceService->markAsPaid($invoice, [
                        'payment_id' => $paymentId ?: $preapprovalId,
                        'payment_status' => 'approved',
                        'payment_method' => $paymentMethod,
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Falha ao marcar invoice como paga.', ['message' => $e->getMessage()]);
                }
            }

            // Fallback do estabelecimento via sessão
            if (!$eid) {
                $sessionData = $request->getSession()->get('finaliza');
                if (!empty($sessionData['eid'])) {
                    $eid = $sessionData['eid'];
                    $usuario = $usuario ?: $emUsuario->findOneBy(['petshop_id' => $eid]);
                }
            }

            // ── 3) Ativa o estabelecimento ──
            if ($eid) {
                try {
                    $uid = $usuario ? $usuario->getId() : 0;
                    $emEstabelecimento->aprovacao($uid, $eid);
                } catch (\Exception $e) {
                    $this->logger->error('Falha ao ativar estabelecimento.', ['eid' => $eid, 'message' => $e->getMessage()]);
                }
            } else {
                // Pagamento aprovado mas não conseguimos identificar o estabelecimento.
                // O webhook (assíncrono) ainda poderá ativá-lo; registramos para diagnóstico.
                $this->logger->error('Pagamento aprovado, mas estabelecimento não identificado no retorno.', [
                    'external_reference' => $externalReference,
                    'payment_id'         => $paymentId,
                    'preapproval_id'     => $preapprovalId,
                ]);
            }

            // ── 4) E-mail de confirmação com o link de acesso ──
            // Não deve bloquear o fluxo: se falhar, apenas registra o erro.
            if ($usuario) {
                try {
                    $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                    $emailService = $this->container->get(\App\Service\EmailService::class);
                    $emailService->sendEmail(
                        $usuario->getEmail(),
                        'Assinatura Confirmada - Sistema HomePet',
                        $this->renderView('emails/assinatura_confirmada.html.twig', [
                            'invoice'   => $invoice,
                            'usuario'   => $usuario,
                            'login_url' => $loginUrl,
                        ])
                    );
                } catch (\Exception $e) {
                    $this->logger->error('Falha ao enviar e-mail de confirmação de assinatura.', ['message' => $e->getMessage()]);
                }
            }

            // Pagamento aprovado e estabelecimento ativado — envia o usuário para a
            // tela de login com a mensagem de estabelecimento ativado.
            $mensagem = base64_encode('Estabelecimento ativado com sucesso! Faça login para acessar a plataforma.');
            return $this->redirectToRoute('app_login', ['confirmation' => $mensagem]);

        } catch (\Exception $e) {
            $this->logger->error('Erro no retorno de pagamento.', ['message' => $e->getMessage()]);
            return $this->render('pagamento/falha.html.twig', [
                'erro' => 'Erro ao processar pagamento: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Localiza a Fatura a partir do external_reference (ou da sessão) sem lançar exceção.
     *
     * O id da Fatura é inteiro, mas o external_reference devolvido pelo MP pode ser um
     * hash — por isso só chamamos find() quando o valor é numérico, caindo para o número
     * da invoice e, por fim, para o invoice_id guardado na sessão.
     */
    private function localizarInvoice($emFatura, ?string $externalReference, Request $request): ?\App\Entity\Fatura
    {
        $invoice = null;

        if ($externalReference !== null && $externalReference !== '') {
            if (ctype_digit((string) $externalReference)) {
                $invoice = $emFatura->find((int) $externalReference);
            }
            if (!$invoice) {
                $invoice = $emFatura->findOneBy(['numeroInvoice' => $externalReference]);
            }
        }

        // Fallback: invoice_id salvo na sessão durante o checkout.
        if (!$invoice) {
            $sessionData = $request->getSession()->get('finaliza');
            if (!empty($sessionData['invoice_id']) && ctype_digit((string) $sessionData['invoice_id'])) {
                $invoice = $emFatura->find((int) $sessionData['invoice_id']);
            }
        }

        return $invoice;
    }

    /**
     * @Route("/pagamento/webhook/mercadopago", name="pagamento_webhook_mercadopago", methods={"POST"})
     */
    public function webhookMercadoPago(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new Response('Invalid payload', 400);
        }

        try {
            $paymentGatewayFactory = $this->container->get(\App\Service\Payment\PaymentGatewayFactory::class);
            $gateway = $paymentGatewayFactory->getGateway('mercadopago');

            $result = $gateway->processWebhook($data);

            if ($result['success']) {
                // Se for pagamento de invoice (external_reference = invoice_id)
                if (isset($result['external_reference'])) {
                    $invoiceId = $result['external_reference'];
                    $invoice = $this->getRepositorio(\App\Entity\Fatura::class)->find($invoiceId);

                    if ($invoice && $result['status'] === 'approved') {
                        $invoiceService = $this->container->get(\App\Service\InvoiceService::class);
                        $invoiceService->markAsPaid($invoice, $result);

                        // Ativar estabelecimento se estava inativo
                        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)
                            ->find($invoice->getEstabelecimentoId());

                        if ($estabelecimento && $estabelecimento->getStatus() !== 'Ativo') {
                            $estabelecimento->setStatus('Ativo');
                            $estabelecimento->setDataAtualizacao(new \DateTime());
                            $this->getRepositorio(\App\Entity\Estabelecimento::class)->add($estabelecimento, true);
                        }

                        // Enviar email de confirmação/renovação
                        $usuario = $this->getRepositorio(\App\Entity\Usuario::class)
                            ->findOneBy(['petshop_id' => $estabelecimento?->getId()]);

                        if ($usuario && $estabelecimento) {
                            $emailService = $this->container->get(\App\Service\EmailService::class);
                            $ehAssinatura = $invoice->getTipo() === 'assinatura';
                            $template = $ehAssinatura
                                ? 'emails/assinatura_confirmada.html.twig'
                                : 'emails/assinatura_renovada.html.twig';

                            $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

                            $emailService->sendEmail(
                                $usuario->getEmail(),
                                $ehAssinatura
                                    ? 'Assinatura Confirmada - Sistema HomePet'
                                    : 'Assinatura Renovada - Sistema HomePet',
                                $this->renderView($template, [
                                    'invoice'         => $invoice,
                                    'estabelecimento' => $estabelecimento,
                                    'usuario'         => $usuario,
                                    'login_url'       => $loginUrl,
                                ])
                            );
                        }
                    }
                }
            }

            return new Response('OK', 200);
        } catch (\Exception $e) {
            error_log('Erro no webhook: ' . $e->getMessage());
            return new Response('Error', 500);
        }
    }

    /**
     * @Route("/pagamento/sucesso", name="pagamento_sucesso")
     */
    public function success(Request $request): Response
    {
        return $this->render('pagamento/sucesso.html.twig', []);
    }

    /**
     * @Route("/pagamento/falha", name="pagamento_falha")
     */
    public function fail(Request $request): Response
    {
        return $this->render('pagamento/falha.html.twig', []);
    }

    /**
     * @Route("/pagamento/pendente", name="pagamento_pendente")
     */
    public function pendding(Request $request): Response
    {

        return $this->render('pagamento/pendente.html.twig', []);
    }

    /**
     * @Route("/pagamento/pix/executar/process_payment", name="pagar_com_pix")
     */
    public function pagarPix(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        // dd($request);
        $autentication = $mercadoPagoService->oauthMP();

        // $payment = $mercadoPagoService->criarPagamento($payload, $autentication['access_token']);
        $payment = $mercadoPagoService->criarPagamento([
            "transaction_amount" => (float) $request->get('transactionAmount'),
            "payment_method_id" => 'pix',
            "payer" => [
                "email" => $request->get('email'),
            ],
        ], $autentication['access_token']);
        // dd($payment);

        // Salva o payment_id na sessão para verificação posterior
        $request->getSession()->set('payment_id', $payment['id']);

        $data = [];
        $data['pix_entities'] = $payment['point_of_interaction'];
        $data['valor'] = (float) $request->get('transactionAmount');
        $data['payment_id'] = $payment['id'];
        // dd($data);
        return $this->render('pagamento/pix.html.twig', $data);
    }

    /**
     * @Route("/pagamento/verificar-status", name="pagamento_verificar_status", methods={"GET"})
     */
    public function verificarStatus(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        $paymentId = $request->query->get('payment_id');

        if (!$paymentId) {
            return $this->json(['status' => 'error', 'message' => 'Payment ID não fornecido']);
        }

        try {
            $autentication = $mercadoPagoService->oauthMP();
            $payment = $mercadoPagoService->consultarPagamento($paymentId, $autentication['access_token']);

            // Se aprovado, ativa o estabelecimento
            if ($payment['status'] === 'approved' && $request->getSession()->has('finaliza')) {
                $finaliza = $request->getSession()->get('finaliza');
                $this->getRepositorio(\App\Entity\Estabelecimento::class)
                    ->aprovacao($finaliza['uid'], $finaliza['eid']);
            }

            return $this->json([
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/pagamento/cartao/processar", name="pagar_com_cartao", methods={"POST"})
     */
    public function pagarCartao(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $autentication = $mercadoPagoService->oauthMP();

            // Prepara os dados do pagamento
            $paymentData = [
                'transaction_amount' => (float) $data['amount'],
                'token' => $data['token'],
                'description' => $data['description'],
                'installments' => (int) $data['installments'],
                'payment_method_id' => $data['payment_method_id'],
                'issuer_id' => $data['issuer_id'],
                'payer' => [
                    'email' => $data['email'],
                    'identification' => [
                        'type' => $data['payer']['identification']['type'],
                        'number' => $data['payer']['identification']['number'],
                    ],
                ],
            ];

            // Cria o pagamento
            $payment = $mercadoPagoService->criarPagamento($paymentData, $autentication['access_token']);

            // Se aprovado, ativa o estabelecimento
            if ($payment['status'] === 'approved') {
                $this->getRepositorio(\App\Entity\Estabelecimento::class)
                    ->aprovacao(
                        $request->getSession()->get('finaliza')['uid'],
                        $request->getSession()->get('finaliza')['eid']
                    );
            }

            return $this->json([
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'] ?? null,
                'payment_id' => $payment['id'],
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
