<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ramsey\Uuid\Uuid;

class MercadoPagoService implements PaymentGatewayInterface
{
    private HttpClientInterface $client;
    private string $accessToken;
    private string $environment; // 'sandbox' | 'production'

    public function __construct(
        HttpClientInterface $client,
        string $mercadoPagoToken,
        string $mercadoPagoEnv = 'production'
    ) {
        $this->client      = $client;
        $this->accessToken = $mercadoPagoToken;
        $this->environment = $mercadoPagoEnv;
    }

    public function getGatewayName(): string
    {
        return 'mercadopago';
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    private function getHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type'  => 'application/json',
        ];

        // Header obrigatório para usar o ambiente de testes do MP
        if ($this->isSandbox()) {
            $headers['X-Sandbox'] = '1';
        }

        return $headers;
    }

    /**
     * Retorna o init_point correto conforme o ambiente
     */
    private function resolveInitPoint(array $content): ?string
    {
        if ($this->isSandbox()) {
            return $content['sandbox_init_point'] ?? $content['init_point'] ?? null;
        }
        return $content['init_point'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preference (pagamento avulso)
    // ─────────────────────────────────────────────────────────────────────────

    public function createPayment(array $data): array
    {
        $baseUrl = rtrim($_ENV['PAGAMENTO_URL'] ?? '', '/');

        $body = [
            'items' => [[
                'title'      => $data['title'] ?? 'Assinatura Sistema',
                'quantity'   => 1,
                'unit_price' => (float) ($data['price'] ?? 0),
            ]],
            'payer' => [
                'email' => $data['email'] ?? '',
                'name'  => $data['comprador']['name'] ?? '',
            ],
            'back_urls' => [
                'success' => $baseUrl . '/pagamento/retorno',
                'failure' => $baseUrl . '/pagamento/falha',
                'pending' => $baseUrl . '/pagamento/pendente',
            ],
            'auto_return'        => 'approved',
            'notification_url'   => $baseUrl . '/pagamento/webhook/mercadopago',
            'external_reference' => (string) ($data['external_reference'] ?? ''),
        ];

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/checkout/preferences', [
                'headers' => array_merge($this->getHeaders(), [
                    'X-Idempotency-Key' => Uuid::uuid4()->toString(),
                ]),
                'json' => $body,
            ]);

            $content    = $response->toArray(false);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 201) {
                return [
                    'success'            => true,
                    'payment_url'        => $this->resolveInitPoint($content),
                    'preference_id'      => $content['id'] ?? null,
                    'sandbox_init_point' => $content['sandbox_init_point'] ?? null,
                ];
            }

            return $this->buildError($statusCode, $content);

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preapproval (assinatura recorrente)
    // ─────────────────────────────────────────────────────────────────────────

    public function createSubscription(array $data): array
    {
        $baseUrl  = rtrim($_ENV['PAGAMENTO_URL'] ?? '', '/');
        $backUrl  = $baseUrl . '/pagamento/retorno';
        $notifUrl = $baseUrl . '/pagamento/webhook/mercadopago';

        // MP exige start_date no futuro, no formato "2025-06-19T10:00:00.000-03:00"
        $startDate = isset($data['start_date'])
            ? $data['start_date']
            : (new \DateTime('+1 day', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:s.000P');

        $body = [
            'reason'             => $data['title'] ?? 'Assinatura Sistema',
            'payer_email'        => $data['email'] ?? '',
            'back_url'           => $backUrl,
            'notification_url'   => $notifUrl,
            'status'             => 'pending',
            'external_reference' => (string) ($data['external_reference'] ?? ''),
            'auto_recurring'     => [
                'frequency'          => 1,
                'frequency_type'     => 'months',
                'transaction_amount' => round((float) ($data['price'] ?? 0), 2),
                'currency_id'        => 'BRL',
                'start_date'         => $startDate,
            ],
        ];

        if (!empty($data['end_date'])) {
            $body['auto_recurring']['end_date'] = $data['end_date'];
        }

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/preapproval', [
                'headers' => array_merge($this->getHeaders(), [
                    'X-Idempotency-Key' => Uuid::uuid4()->toString(),
                ]),
                'json' => $body,
            ]);

            $content    = $response->toArray(false);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 201) {
                return [
                    'success'            => true,
                    'subscription_id'    => $content['id'] ?? null,
                    'init_point'         => $this->resolveInitPoint($content),
                    'sandbox_init_point' => $content['sandbox_init_point'] ?? null,
                    'status'             => $content['status'] ?? null,
                ];
            }

            return $this->buildError($statusCode, $content);

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $response = $this->client->request('PUT', "https://api.mercadopago.com/preapproval/{$subscriptionId}", [
                'headers' => $this->getHeaders(),
                'json'    => ['status' => 'cancelled'],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateSubscriptionAmount(string $subscriptionId, float $novoValor): array
    {
        try {
            $response = $this->client->request('PUT', "https://api.mercadopago.com/preapproval/{$subscriptionId}", [
                'headers' => $this->getHeaders(),
                'json'    => [
                    'auto_recurring' => [
                        'transaction_amount' => $novoValor,
                        'currency_id'        => 'BRL',
                    ],
                ],
            ]);

            $content = $response->toArray(false);

            return [
                'success'  => $response->getStatusCode() === 200,
                'status'   => $content['status'] ?? null,
                'raw_data' => $content,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consultas
    // ─────────────────────────────────────────────────────────────────────────

    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = $this->client->request('GET', "https://api.mercadopago.com/v1/payments/{$paymentId}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray(false);

            return [
                'success'            => true,
                'status'             => $data['status'] ?? 'unknown',
                'status_detail'      => $data['status_detail'] ?? null,
                'transaction_amount' => $data['transaction_amount'] ?? 0,
                'payment_method_id'  => $data['payment_method_id'] ?? null,
                'date_approved'      => $data['date_approved'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'raw_data'           => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSubscriptionStatus(string $subscriptionId): array
    {
        try {
            $response = $this->client->request('GET', "https://api.mercadopago.com/preapproval/{$subscriptionId}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray(false);

            return [
                'success'           => true,
                'status'            => $data['status'] ?? 'unknown',
                'reason'            => $data['reason'] ?? null,
                'payer_email'       => $data['payer_email'] ?? null,
                'auto_recurring'    => $data['auto_recurring'] ?? [],
                'next_payment_date' => $data['next_payment_date'] ?? null,
                'init_point'        => $this->resolveInitPoint($data),
                'raw_data'          => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function processWebhook(array $data): array
    {
        $type = $data['type'] ?? null;
        $id   = $data['data']['id'] ?? null;

        if (!$id) {
            return ['success' => false, 'error' => 'ID não encontrado no webhook'];
        }

        if ($type === 'payment') {
            return $this->getPaymentStatus($id);
        } elseif ($type === 'preapproval') {
            return $this->getSubscriptionStatus($id);
        }

        return ['success' => false, 'error' => 'Tipo de webhook não suportado: ' . $type];
    }

    public function createDirectPayment(array $data): array
    {
        $payload = [
            'transaction_amount' => (float) $data['amount'],
            'description'        => $data['description'] ?? 'Pagamento',
            'payment_method_id'  => $data['payment_method_id'],
            'payer'              => ['email' => $data['email']],
            'external_reference' => $data['external_reference'] ?? null,
        ];

        if (isset($data['token'])) {
            $payload['token'] = $data['token'];
        }

        if (isset($data['identification'])) {
            $payload['payer']['identification'] = $data['identification'];
        }

        try {
            $response = $this->client->request('POST', 'https://api.mercadopago.com/v1/payments', [
                'headers' => array_merge($this->getHeaders(), [
                    'X-Idempotency-Key' => Uuid::uuid4()->toString(),
                ]),
                'json' => $payload,
            ]);

            $content    = $response->toArray(false);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 201) {
                return [
                    'success'       => true,
                    'payment_id'    => $content['id'] ?? null,
                    'status'        => $content['status'] ?? null,
                    'status_detail' => $content['status_detail'] ?? null,
                    'raw_data'      => $content,
                ];
            }

            return $this->buildError($statusCode, $content);

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compatibilidade: retorna o access_token já configurado no serviço.
     * O token é injetado via constructor — não há necessidade de uma chamada OAuth separada.
     */
    public function oauthMP(): array
    {
        return ['access_token' => $this->accessToken];
    }

    /**
     * Consulta um pagamento pelo ID. O parâmetro $token é mantido por
     * compatibilidade mas ignorado — o serviço já usa o token injetado.
     */
    public function consultarPagamento(string $paymentId, string $token = ''): array
    {
        $status = $this->getPaymentStatus($paymentId);
        // Retorna no formato esperado pelo PagamentoController legado
        return $status['raw_data'] ?? $status;
    }

    private function buildError(int $statusCode, array $content): array
    {
        $mpMessage = $content['message'] ?? ($content['error'] ?? 'Erro desconhecido');
        $mpCause   = isset($content['cause']) ? json_encode($content['cause']) : '';

        return [
            'success' => false,
            'error'   => "[MP {$statusCode}] {$mpMessage}" . ($mpCause ? " | Cause: {$mpCause}" : ''),
            'raw'     => $content,
        ];
    }
}
