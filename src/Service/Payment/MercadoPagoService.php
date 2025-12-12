<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Ramsey\Uuid\Uuid;

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

	public function oauthMP()
	{
		// Retorna o access token diretamente do .env
		// Evita erro de OAuth quando as credenciais não estão completas
		return [
			'access_token' => $this->accessToken
		];
	}
	
	public function oauthMPOriginal()
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

	public function createPayment__(array $data): array
	{
		$autentication = $this->oauthMP();
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
				'success' => $_ENV['PAGAMENTO_URL'] . 'pagamento/sucesso',
				'failure' => $_ENV['PAGAMENTO_URL'] . 'pagamento/falha',
				'pending' => $_ENV['PAGAMENTO_URL'] . 'pagamento/pendente'
			],
			'auto_return' => 'approved',
			'notification_url' => $_ENV['PAGAMENTO_URL'] . 'pagamento/retorno'
		];

		$response = $this->client->request('POST', 'https://api.mercadopago.com/checkout/preferences', [
			'headers' => [
				'Authorization' => 'Bearer ' . $autentication['access_token'],
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

	public function createPreference(array $data, $autentication)
	{

		$payload = [];

		$payload['items'][0]["id"] = "Sound system";
		$payload['items'][0]["title"] = "Dummy Title";
		$payload['items'][0]["description"] = "Dummy description";
		$payload['items'][0]["picture_url"] = "https://www.myapp.com/myimage.jpg";
		$payload['items'][0]["category_id"] = "car_electronics";
		$payload['items'][0]["quantity"] = 1;
		$payload['items'][0]["currency_id"] = "BRL";
		$payload['items'][0]["unit_price"] = 1;

		$payload['payer']['name'] = $data['comprador']['name'] ?? 'Fulano';
		$payload['payer']['surname'] = '';
		$payload['payer']['phone']['area_code'] = 11;
		$payload['payer']['phone']['number'] = "987654321";
		$payload['payer']['address']['zip_code'] = "12312-123";
		$payload['payer']['address']['street_name'] = "Av das Nacoes Unidas";
		$payload['payer']['address']['street_number'] = 3003;

		$payload['payer']['entity_type'] = "individual";
		$payload['payer']['type'] = "customer";
		$payload['payer']['email'] = $data['email'] ?? 'adiliogobira@gmail.com';
		$payload['payer']['identification']['type'] = 'cpf';
		$payload['payer']['identification']['number'] = "10156568624";
		$payload['payer']['date_created'] = date(\DateTime::ATOM);

		// $payload['payment_methods'] = [];
		$payload['payment_methods']['default_payment_method_id'] = "amex";
		$payload['payment_methods']['installments'] = 10;
		$payload['payment_methods']['default_installments'] = 5;

		// $payload['shipments']['local_pickup'] = false;
		// $payload['shipments']['dimensions'] = "32 x 25 x 16";
		// $payload['shipments']['default_shipping_method'] = null;
		// // $payload['shipments']['free_methods'] = ['id'=> null];
		// $payload['shipments']['cost'] = 20;
		// $payload['shipments']['free_shipping'] = true;
		// $payload['shipments']['receiver_address']['zip_code'] = '72549555';
		// $payload['shipments']['receiver_address']['street_name'] = 'Street address test';
		// $payload['shipments']['receiver_address']['city_name'] = 'São Paulo';
		// $payload['shipments']['receiver_address']['state_name'] = 'São Paulo';
		// $payload['shipments']['receiver_address']['street_number'] = 100;
		// $payload['shipments']['receiver_address']['country_name'] ='Brazil';

		$payload['back_urls']['success'] = $_ENV['PAGAMENTO_URL'] . 'pagamento/sucesso';
		$payload['back_urls']['failure'] = $_ENV['PAGAMENTO_URL'] . 'pagamento/falha';
		$payload['back_urls']['pending'] = $_ENV['PAGAMENTO_URL'] . 'pagamento/pendente';

		$payload['notification_url'] = $_ENV['PAGAMENTO_URL'] . 'pagamento/retorno';
		// $payload['additional_info'] = '';
		$payload['auto_return'] = 'approved';
		// $payload['external_reference'] = '1643827245';
		// $payload['expires'] = false;
		// $payload['expiration_date_from'] = date(\DateTime::ATOM, strtotime("+1minute"));
		// $payload['expiration_date_to'] = date(\DateTime::ATOM, strtotime("+5minute"));
		// $payload['marketplace'] = 'NONE';
		// $payload['marketplace_fee'] = 0;
		// $payload['differential_pricing'] = ['id' => 1];
		// $payload['metadata'] = null;

		$response = $this->client->request('POST', 'https://api.mercadopago.com/checkout/preferences', [
			'headers' => [
				'Authorization' => 'Bearer ' . $autentication['access_token'],
				'Content-Type' => 'application/json',
			],
			'json' => $payload
		]);

		// $content = $response->toArray();
		return $response->toArray();
		// dd($response->toArray());
	}

	public function criarPagamento(array $data, $token)
	{
		
		$response = $this->client->request('POST', 'https://api.mercadopago.com/v1/payments', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
				'X-Idempotency-Key' => Uuid::uuid4()->toString(),
			],
			'json' => $data
		]);

		return $response->toArray();
	}

	/**
	 * Consulta o status de um pagamento no Mercado Pago
	 */
	public function consultarPagamento(string $paymentId, string $token): array
	{
		$response = $this->client->request('GET', "https://api.mercadopago.com/v1/payments/{$paymentId}", [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
			]
		]);

		return $response->toArray();
	}

	public function createPayment(array $data)
	{
		$autentication = $this->oauthMP();
		// dd($autentication);

		$payload = [];
		$preference = $this->createPreference($data, $autentication);
		$payload = $preference;
		dd($preference);

		$payload['additional_info']['items'] = $preference['items'];
		$payload['additional_info']['payer'] = $preference['payer'];

		// $payload['application_fee'] = null;
		// $payload['binary_mode'] = false;
		// $payload['campaign_id'] = null;
		$payload['capture'] = true;
		// $payload['coupon_amount'] = null;
		$payload['description'] = "Pagamento do plano {$data['title']}";
		$payload['differential_pricing_id'] = null;
		$payload['external_reference'] = $data['planoId'];
		$payload['installments'] = 1;
		// $payload['metadata'] = null;

		$payload['payer']['entity_type'] = "individual";
		$payload['payer']['type'] = "customer";
		$payload['payer']['id'] = null;
		$payload['payer']['email'] = $preference['payer']['email'];
		$payload['payer']['identification']['type'] = null;
		$payload['payer']['identification']['number'] = null;

		$payload['payment_method_id'] = $preference['payment_methods']['default_payment_method_id'];
		$payload['token'] = 'ff8080814c11e237014c1ff593b57b4d';
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