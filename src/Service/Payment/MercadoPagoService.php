<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ramsey\Uuid\Uuid;

class MercadoPagoService implements PaymentGatewayInterface
{
    private $client;
    private $accessToken;

    public function __construct(HttpClientInterface $client, string $mercadoPagoToken)
    {
        $this->client = $client;
        $this->accessToken = $mercadoPagoToken;
    }

    public function getGatewayName(): string
    {
        return 'mercadopago';
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    public function createPayment(array $data): array
    {
        $body = [
            'items' => [[
                'title' => $data['title'] ?? 'Assinatura Sistema',
                'quantity' => 1,
                'unit_price' => (float) ($data['price'] ?? 0)
            ]],
            'payer' => [
                'email' => $data['email'] ?? 'test@test.com',
                'name' => $data['comprador']['name'] ?? '',
            ],
            'back_urls' => [
                'success' => ($_ENV['PAGAMENTO_URL'] ?? '') . 'pagamento/sucesso',
                'failure' => ($_ENV['PAGAMENTO_URL'] ?? '') . 'pagamento/falha',
                'pending' => ($_ENV['PAGAMENTO_URL'] ?? '') . 'pagamento/pendente'
            ],
            'auto_return' => 'approved',
            'notification_url' => ($_ENV['PAGAMENTO_URL'] ?? '') . 'pagamento/webhook/mercadopago',
            'external_reference' => $data['external_reference'] ?? null,
        ];

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/checkout/preferences', [
                'headers' => $this->getHeaders(),
                'json' => $body
            ]);

            $content = $response->toArray();

            return [
                'success' => true,
                'payment_url' => $content['init_point'] ?? null,
                'preference_id' => $content['id'] ?? null,
                'sandbox_init_point' => $content['sandbox_init_point'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createSubscription(array $data): array
    {
        // Mercado Pago usa o conceito de "preapproval" para assinaturas
        $body = [
            'reason' => $data['title'] ?? 'Assinatura Sistema',
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float) ($data['price'] ?? 0),
                'currency_id' => 'BRL',
            ],
            'payer_email' => $data['email'] ?? 'test@test.com',
            'back_url' => ($_ENV['PAGAMENTO_URL'] ?? '') . 'pagamento/sucesso',
            'status' => 'pending',
            'external_reference' => $data['external_reference'] ?? null,
        ];

        // Data de início (hoje)
        if (isset($data['start_date'])) {
            $body['auto_recurring']['start_date'] = $data['start_date'];
        }

        // Data de fim (opcional)
        if (isset($data['end_date'])) {
            $body['auto_recurring']['end_date'] = $data['end_date'];
        }

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/preapproval', [
                'headers' => $this->getHeaders(),
                'json' => $body
            ]);

            $content = $response->toArray();

            return [
                'success' => true,
                'subscription_id' => $content['id'] ?? null,
                'init_point' => $content['init_point'] ?? null,
                'sandbox_init_point' => $content['sandbox_init_point'] ?? null,
                'status' => $content['status'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $response = $this->client->request('PUT', "https://api.mercadopago.com/preapproval/{$subscriptionId}", [
                'headers' => $this->getHeaders(),
                'json' => [
                    'status' => 'cancelled'
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = $this->client->request('GET', "https://api.mercadopago.com/v1/payments/{$paymentId}", [
                'headers' => $this->getHeaders()
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'status_detail' => $data['status_detail'] ?? null,
                'transaction_amount' => $data['transaction_amount'] ?? 0,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'date_approved' => $data['date_approved'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'raw_data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSubscriptionStatus(string $subscriptionId): array
    {
        try {
            $response = $this->client->request('GET', "https://api.mercadopago.com/preapproval/{$subscriptionId}", [
                'headers' => $this->getHeaders()
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'reason' => $data['reason'] ?? null,
                'payer_email' => $data['payer_email'] ?? null,
                'auto_recurring' => $data['auto_recurring'] ?? [],
                'next_payment_date' => $data['next_payment_date'] ?? null,
                'raw_data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function processWebhook(array $data): array
    {
        $type = $data['type'] ?? null;
        $id = $data['data']['id'] ?? null;

        if (!$id) {
            return [
                'success' => false,
                'error' => 'ID não encontrado no webhook'
            ];
        }

        // Webhook pode ser de payment ou de preapproval (assinatura)
        if ($type === 'payment') {
            return $this->getPaymentStatus($id);
        } elseif ($type === 'preapproval') {
            return $this->getSubscriptionStatus($id);
        }

        return [
            'success' => false,
            'error' => 'Tipo de webhook não suportado: ' . $type
        ];
    }

    /**
     * Cria um pagamento direto (sem redirecionamento)
     */
    public function createDirectPayment(array $data): array
    {
        $payload = [
            'transaction_amount' => (float) $data['amount'],
            'description' => $data['description'] ?? 'Pagamento',
            'payment_method_id' => $data['payment_method_id'],
            'payer' => [
                'email' => $data['email'],
            ],
            'external_reference' => $data['external_reference'] ?? null,
        ];

        // Se tiver token do cartão
        if (isset($data['token'])) {
            $payload['token'] = $data['token'];
        }

        // Se tiver identificação
        if (isset($data['identification'])) {
            $payload['payer']['identification'] = $data['identification'];
        }

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/v1/payments', [
                'headers' => array_merge($this->getHeaders(), [
                    'X-Idempotency-Key' => Uuid::uuid4()->toString(),
                ]),
                'json' => $payload
            ]);

            $content = $response->toArray();

            return [
                'success' => true,
                'payment_id' => $content['id'] ?? null,
                'status' => $content['status'] ?? null,
                'status_detail' => $content['status_detail'] ?? null,
                'raw_data' => $content,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
