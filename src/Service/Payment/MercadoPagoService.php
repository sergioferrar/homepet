<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class MercadoPagoService
{
    private $client;
    private $em;
    private $accessToken;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em, string $mercadoPagoToken)
    {
        $this->client = $client;
        $this->em = $em;
        $this->accessToken = $mercadoPagoToken;
    }

    public function createPayment(array $data): array
    {
        $body = [
            'items' => [[
                'title' => $data['title'] ?? 'Produto',
                'quantity' => $data['quantity'] ?? 1,
                'unit_price' => (float) ($data['price'] ?? 0)
            ]],
            'payer' => [
                'email' => $data['email'] ?? 'test@test.com'
            ],
            'back_urls' => [
                'success' => $data['success_url'] ?? 'https://seusite.com/sucesso',
                'failure' => $data['failure_url'] ?? 'https://seusite.com/falha',
                'pending' => $data['pending_url'] ?? 'https://seusite.com/pendente'
            ],
            'auto_return' => 'approved',
            'notification_url' => $data['webhook_url'] ?? 'https://seusite.com/webhook/mercadopago'
        ];

        $response = $this->client->request('POST', 'https://api.mercadopago.com/checkout/preferences', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ],
            'json' => $body
        ]);
        // die($response->getContent());
        // dd($response->getStatusCode());
        // if (200 !== $response->getStatusCode()) {
        //     return ['success' => false, 'message' => 'Erro ao criar pagamento'];
        // }

        $content = $response->toArray();
        // dd($response->toArray());
        return ['success' => true, 'init_point' => $content['init_point']];
    }

    public function handleWebhook(array $data): void
    {
        $payment = new Payment();
        $payment->setTransactionId($data['data']['id'] ?? '');
        $payment->setStatus($data['type'] ?? 'unknown');
        $payment->setPayload(json_encode($data));
        $payment->setCreatedAt(new \DateTime());

        $this->em->persist($payment);
        $this->em->flush();
    }
}