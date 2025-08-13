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

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em, string $mercadoPagoToken)
    {
        $this->client = $client;
        $this->em = $em;
        $this->accessToken = $mercadoPagoToken;
    }

    public function createPayment(array $data)
    {
        $body = [
            'items' => [[
                'id' => $data['planoId'] ?? 'Produto',
                'title' => $data['title'] ?? 'Produto',
                'quantity' => $data['quantity'] ?? 1,
                'unit_price' => (float) ($data['price'] ?? 0)
            ]],
            'payer' => [
                'email' => $data['email'] ?? 'test@test.com',
                'name' => $data['comprador']['name'],
                'address' => $data['comprador']['name'],
                'identification' => ['number' => $data['comprador']['idUsuario'],'type'=>'INT'],
                'address' => [
                    'zip_code'=>$data['comprador']['cep'],
                    'street_name'=>$data['comprador']['rua'],
                    'street_number'=>$data['comprador']['numero']
                ],
            ],
            'back_urls' => [
                'success' => $_ENV['PAGAMENTO_URL'] . 'pagamento/sucesso',
                'failure' => $_ENV['PAGAMENTO_URL'] . 'pagamento/falha',
                'pending' => $_ENV['PAGAMENTO_URL'] . 'pagamento/pendente'
            ],
            'redirect_urls' => [
                'success' => $_ENV['PAGAMENTO_URL'] . 'pagamento/sucesso',
                'failure' => $_ENV['PAGAMENTO_URL'] . 'pagamento/falha',
                'pending' => $_ENV['PAGAMENTO_URL'] . 'pagamento/pendente'
            ],
            'external_reference' => $data['planoId'],
            'auto_return' => 'approved',
            'additional_info' => 'Aquisição de plano de serviços de sistma de gestão de Pet shops e Clinicas veterinárias.',
            'notification_url' => $_ENV['PAGAMENTO_URL'] . 'pagamento/retorno'
        ];


        // return $this->handleWebhook($body);
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

        $data = [
        "description"=>$content['additional_info'],
        "installments"=>1,
        "payer"=>[
        "email"=>$data['email'],
        "identification"=>[
            "type"=>$content['payer']['identification']['type'],
            "number"=>$content['payer']['identification']['number']
        ]
        ],
       // "issuer_id"=>$content['issuer_id'],
        "payment_method_id"=>'master',
        "token"=>'bdc208cdb2555840cd3ddf918d842013',
        "transaction_amount"=>1
      ];

      $response = $this->client->request('POST', 'https://api.mercadopago.com/v1/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);

      //   dd($content, $response->toArray());
        dd($content, $data);
        // return ['success' => true, 'init_point' => $content['init_point']];
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