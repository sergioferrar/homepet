<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Estabelecimento;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use App\Service\Payment\MercadoPagoService;


class PagamentoController extends DefaultController
{

    /**
     * @Route("/pagamento/retorno", name="pagamento_retorno")
     */
    public function notificacao(Request $request, MercadoPagoService $mercadoPagoService): Response
    {
        $paymentId = $request->get('payment_id');
        $status = $request->get('status');
        
        if (!$paymentId) {
            return new Response('ID de pagamento ausente', 400);
        }

        $emEstabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class);
        $emUsuario = $this->getRepositorio(\App\Entity\Usuario::class);

        // Verifica o status do pagamento no Mercado Pago
        try {
            $autentication = $mercadoPagoService->oauthMP();
            $payment = $mercadoPagoService->consultarPagamento($paymentId, $autentication['access_token']);
            
            // Só aprova se o pagamento foi confirmado
            if ($payment['status'] === 'approved' || $status === 'approved') {
                $this->getRepositorio(\App\Entity\Estabelecimento::class)
                    ->aprovacao(
                        $request->getSession()->get('finaliza')['uid'],
                        $request->getSession()->get('finaliza')['eid']
                    );
                
                return $this->render('pagamento/confirmacao.html.twig', [
                    'estabelecimento' => $request->getSession()->get('finaliza')['eid'],
                    'status' => 'aprovado',
                ]);
            } else {
                // Pagamento pendente ou recusado
                return $this->render('pagamento/pendente.html.twig', [
                    'status' => $payment['status'] ?? $status,
                    'mensagem' => 'Seu pagamento está sendo processado. Você receberá um e-mail quando for confirmado.',
                ]);
            }
        } catch (\Exception $e) {
            return $this->render('pagamento/falha.html.twig', [
                'erro' => 'Erro ao processar pagamento: ' . $e->getMessage(),
            ]);
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
        dd($request);
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
            "transaction_amount" => (float)$request->get('transactionAmount'),
            "payment_method_id" => 'pix',
            "payer" => [
                "email" => $request->get('email'),
            ]
        ], $autentication['access_token']);
        // dd($payment);
        
        // Salva o payment_id na sessão para verificação posterior
        $request->getSession()->set('payment_id', $payment['id']);
        
        $data = [];
        $data['pix_entities'] = $payment['point_of_interaction'];
        $data['valor'] = (float)$request->get('transactionAmount');
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
                'transaction_amount' => (float)$data['amount'],
                'token' => $data['token'],
                'description' => $data['description'],
                'installments' => (int)$data['installments'],
                'payment_method_id' => $data['payment_method_id'],
                'issuer_id' => $data['issuer_id'],
                'payer' => [
                    'email' => $data['email'],
                    'identification' => [
                        'type' => $data['payer']['identification']['type'],
                        'number' => $data['payer']['identification']['number']
                    ]
                ]
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
                'payment_id' => $payment['id']
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
