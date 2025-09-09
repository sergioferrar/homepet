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
	public function notificacao(Request $request): Response
	{
		$notificationCode = $request->get('notificationCode');
		if (!$notificationCode) {
			return new Response('Código de notificação ausente', 400);
		}


		$emEstabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class);
		$emUsuario = $this->getRepositorio(\App\Entity\Usuario::class);

		$this->getRepositorio(\App\Entity\Estabelecimento::class)
		->aprovacao(
			$request->getSession()->get('finaliza')['uid'], 
			$request->getSession()->get('finaliza')['eid']
		);
		// Consulta no PagSeguro o status da transação
		// Essa parte seria outro método no serviço: consultarTransacao($notificationCode)

		// Simula o processamento do pagamento
		// Aqui você deveria fazer: validar status = "aprovado", e ativar o plano
		// Exemplo:
		// $estabelecimento = $em->getRepository(Estabelecimento::class)->findOneBy([...]);
		// $estabelecimento->setStatus('ativo');
		// $em->flush();
		return $this->render('pagamento/confirmacao.html.twig', [
			'estabelecimento' => $request->getSession()->get('finaliza')['eid'],
		]);
		// return new Response('Notificação recebida e processada', 200);
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
			"transaction_amount" => (float) $request->get('transactionAmount'),
			"payment_method_id" => 'pix',
			"payer" => [
				"email" => $request->get('email'),
			]
		], $autentication['access_token']);

		$data = [];
		$data['pix_entities'] = $payment['point_of_interaction'];
		$data['valor'] = (float) $request->get('transactionAmount');
		// dd($data);
		return $this->render('pagamento/pix.html.twig', $data);
		dd($payment);

		MercadoPagoConfig::setAccessToken($autentication['access_token']);

		$client = new PaymentClient();
		$request_options = new RequestOptions();
		$request_options->setCustomHeaders(["X-Idempotency-Key: 0d5020ed-1af6-469c-ae06-c3bec19954bb"]);

		$payment = $client->create([
			"transaction_amount" => (float) $request->get('transactionAmount'),
			"payment_method_id" => 'pix',
			"payer" => [
				"email" => $request->get('email')
			]
		], $request_options);

		dd($payment);
	}
}
