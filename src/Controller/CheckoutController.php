<?php

namespace App\Controller;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

class CheckoutController extends DefaultController
{
    /**
     * @Route("/checkout", name="app_checkout", methods={"GET"})
     */
    public function index(CsrfTokenManagerInterface $csrfTokenManager, Request $request): Response
    {
        $publicKey = $_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? '';

        $usuario = $this->getRepositorio(\App\Entity\Usuario::class)->find('66');
        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find('65');
        $plano = $this->getRepositorio(\App\Entity\Plano::class)->find('4');
        $serialize = [
            'user' => serialize($usuario),
            'estabelecimento' => serialize($estabelecimento),
            'plano' => serialize($plano),
        ];
        // dd(unserialize(json_decode(base64_decode($request->get('hash')), 1)['plano']));
        // dd(base64_encode(json_encode($serialize)));
        if ($publicKey === '') {
            throw $this->createNotFoundException(
                'Configure MERCADOPAGO_ACCESS_TOKEN no arquivo .env.local.'
            );
        }

        return $this->render('checkout/index.html.twig', [
            'public_key' => $publicKey,
            // Valor demonstrativo. Em produção, obtenha o valor do pedido no servidor.
            'amount' => 49.90,
            'description' => 'Pedido demonstrativo',
            'csrf_token' => $csrfTokenManager->getToken('checkout_payment')->getValue(),
        ]);
    }

    /**
     * @Route("/checkout/pagar", name="app_checkout_pay", methods={"POST"})
     */
    public function pay(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['error' => 'Requisição inválida.'], 400);
        }

        if (!$csrfTokenManager->isTokenValid(
            new \Symfony\Component\Security\Csrf\CsrfToken(
                'checkout_payment',
                (string) ($payload['_token'] ?? '')
            )
        )) {
            return $this->json([
                'error' => 'Sessão expirada. Atualize a página e tente novamente.',
            ], 419);
        }

        $accessToken = $_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? '';

        if ($accessToken === '') {
            return $this->json([
                'error' => 'MERCADOPAGO_ACCESS_TOKEN não está configurado no servidor.',
            ], 500);
        }

        $method = (string) ($payload['method'] ?? '');
        $email = filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $cpf = preg_replace('/\D+/', '', (string) ($payload['cpf'] ?? ''));

        if (!$email || strlen($cpf) !== 11) {
            return $this->json([
                'error' => 'Informe um e-mail válido e CPF com 11 dígitos.',
            ], 422);
        }

        // Nunca confie em valores enviados pelo navegador.
        // Em produção, carregue o pedido no banco e calcule o total no servidor.
        $amount = 49.90;
        $description = 'Pedido demonstrativo';

        $paymentData = [
            'transaction_amount' => $amount,
            'description' => $description,
            'payer' => [
                'email' => $email,
                'identification' => [
                    'type' => 'CPF',
                    'number' => $cpf,
                ],
            ],
        ];

        if ($method === 'pix') {
            $paymentData['payment_method_id'] = 'pix';
        } elseif ($method === 'card') {
            $token = (string) ($payload['token'] ?? '');
            $paymentMethodId = (string) ($payload['payment_method_id'] ?? '');
            $installments = max(1, (int) ($payload['installments'] ?? 1));
            $cardKind = (string) ($payload['card_kind'] ?? 'credit');

            if ($token === '' || $paymentMethodId === '') {
                return $this->json([
                    'error' => 'Não foi possível tokenizar o cartão. Confira os dados e tente novamente.',
                ], 422);
            }

            $paymentData['token'] = $token;
            $paymentData['payment_method_id'] = $paymentMethodId;
            $paymentData['installments'] = $cardKind === 'debit' ? 1 : $installments;
        } else {
            return $this->json(['error' => 'Selecione PIX ou cartão.'], 422);
        }

        try {
            MercadoPagoConfig::setAccessToken($accessToken);

            $client = new PaymentClient();
            $options = new RequestOptions();
            $options->setCustomHeaders([
                'X-Idempotency-Key: ' . Uuid::v4()->toRfc4122(),
            ]);

            $payment = $client->create($paymentData, $options);

            $response = [
                'id' => $payment->id ?? null,
                'status' => $payment->status ?? 'unknown',
                'status_detail' => $payment->status_detail ?? null,
            ];

            if ($method === 'pix') {
                $transactionData = $payment->point_of_interaction->transaction_data ?? null;
                $response['qr_code_base64'] = $transactionData->qr_code_base64 ?? null;
                $response['qr_code'] = $transactionData->qr_code ?? null;
                $response['ticket_url'] = $transactionData->ticket_url ?? null;
            }

            return $this->json($response);
        } catch (\Throwable $exception) {
            // Registre detalhes técnicos em log privado; nunca exponha credenciais ao cliente.
            return $this->json([
                'error' => 'O Mercado Pago não conseguiu processar o pagamento. Verifique os dados e as credenciais de teste.',
            ], 502);
        }
    }
}