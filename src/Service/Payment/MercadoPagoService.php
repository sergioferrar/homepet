<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MercadoPagoService
{
    private $client;
    private $em;
    private $accessToken;
    private $oathData;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em, string $mercadoPagoToken)
    {
        $this->client = $client;
        $this->em = $em;
        $this->accessToken = $mercadoPagoToken;
    }

    private function oauthMP()
    {
        $data = [];
        $data["client_secret"] = $_ENV['MERCADO_PAGO_CLIENT_SECRET'];
        $data["client_id"] = $_ENV['MERCADO_PAGO_CLIENT_ID'];
        $data["grant_type"] = "client_credentials";
        $data["code"] = "TG-XXXXXXXX-241983636";
        $data["code_verifier"] = "47DEQpj8HBSa-_TImW-5JCeuQeRkm5NMpJWZG3hSuFU";
        $data["redirect_uri"] = "https://www.mercadopago.com.br/developers/example/redirect-url";
        $data["refresh_token"] = "TG-XXXXXXXX-241983636";
        $data["test_token"] = "false";
        
        if($_ENV['MERCADO_PAGO_ENV'] == 'sandbox'){
            $data["test_token"] = "true";
        }

        $response = $this->client->request('POST', 'https://api.mercadopago.com/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ])->toArray();

        return $response;
    }

    public function createPayment(array $data)
    {
        $autentication = $this->oauthMP();
        

        $payload = [];

        $payload['additional_info']['items'][0]['id'] = $data['planoId'];
        $payload['additional_info']['items'][0]['title'] = $data['title'];
        $payload['additional_info']['items'][0]['quantity'] = 1;
        $payload['additional_info']['items'][0]['unit_price'] = $data['price'];
        $payload['additional_info']['items'][0]['type'] = "signature";
        $payload['additional_info']['items'][0]['warranty'] = false;
        $payload['additional_info']['items'][0]['category_descriptor']['passenger'] = new \stdClass();
        $payload['additional_info']['items'][0]['category_descriptor']['route'] = new \stdClass();

        $payload['additional_info']['payer']['first_name'] = $data['comprador']['name'];
        $payload['additional_info']['payer']['last_name'] = '';
        $payload['additional_info']['payer']['phone']['area_code'] = 11;
        $payload['additional_info']['payer']['phone']['number'] = "987654321";
        $payload['additional_info']['payer']['address']['zip_code'] = "12312-123";
        $payload['additional_info']['payer']['address']['street_name'] = "Av das Nacoes Unidas";
        $payload['additional_info']['payer']['address']['street_number'] = 3003;

        $payload['application_fee'] = null;
        $payload['binary_mode'] = false;
        $payload['campaign_id'] = null;
        $payload['capture'] = true;
        $payload['coupon_amount'] = null;
        $payload['description'] = "Pagamento do plano {$data['title']}";
        $payload['differential_pricing_id'] = null;
        $payload['external_reference'] = $data['planoId'];
        $payload['installments'] = 1;
        $payload['metadata'] = null;

        $payload['payer']['entity_type'] = "individual";
        $payload['payer']['type'] = "customer";
        $payload['payer']['id'] = null;
        $payload['payer']['email'] = $data['email'];
        $payload['payer']['identification']['type'] = null;
        $payload['payer']['identification']['number'] = null;

        $payload['payment_method_id'] = "pix";
        $payload['transaction_amount'] = 1;

        $response = $this->client->request('POST', 'https://api.mercadopago.com/v1/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $autentication['access_token'],
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => '0d5020ed-1af6-469c-ae06-c3bec19954bb',
            ],
            'json' => $payload
        ]);

        // die($response->getContent());
        // dd($response->getStatusCode());
        // if (200 !== $response->getStatusCode()) {
        //     return ['success' => false, 'message' => 'Erro ao criar pagamento'];
        // }
        $content = $response->toArray();
        // dd($response->toArray());

        return ['init_point' => $content['point_of_interaction']['transaction_data']['ticket_url']];
    }

    public function handleWebhook(array $data)
    {
        if (!isset($data['data']['id']) || !isset($data['type']) || $data['type'] !== 'payment') {
            return;
        }

        // Busca detalhes completos do pagamento
        $paymentId = $data['data']['id'];
        $response = $this->client->request('GET', 'https://api.mercadopago.com/v1/payments/' . $paymentId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ]
        ]);
        dd($response);
        if ($response->getStatusCode() === 200) {
            $paymentData = $response->toArray();
            return ['success' => true, 'init_point' => $paymentData['init_point']];
            // $payment = new Payment();
            // $payment->setTransactionId($paymentData['id']);
            // $payment->setStatus($paymentData['status']);
            // $payment->setPayload(json_encode($paymentData));
            // $payment->setCreatedAt(new \DateTime());

            // $this->em->persist($payment);
            // $this->em->flush();
        }
    }

    // public function handleWebhook(array $data): void
    // {
    //     $payment = new Payment();
    //     $payment->setTransactionId($data['data']['id'] ?? '');
    //     $payment->setStatus($data['type'] ?? 'unknown');
    //     $payment->setPayload(json_encode($data));
    //     $payment->setCreatedAt(new \DateTime());

    //     $this->em->persist($payment);
    //     $this->em->flush();
    // }
}