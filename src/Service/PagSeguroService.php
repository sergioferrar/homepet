<?php

namespace App\Service;

/**
 * Serviço para integração com a API do PagBank/PagSeguro
 * Implementa checkout transparente para pagamentos
 */
class PagSeguroService
{

    public function __construct($email, $token) {
        //dd($email, $token);
        \PagSeguro\Library::initialize();
        $this->conecta($email, $token);

        try {
            /**
             * @todo For use with application credentials use:
             * \PagSeguro\Configuration\Configure::getApplicationCredentials()
             *  ->setAuthorizationCode("FD3AF1B214EC40F0B0A6745D041BFDDD")
             */
            $sessionCode = \PagSeguro\Services\Session::create(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
            // dd(\PagSeguro\Configuration\Configure::getAccountCredentials());

            //echo "<strong>ID de sess&atilde;o criado: </strong>{$sessionCode->getResult()}";
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function executeCheckout($client, $product)
    {
        $payment = new \PagSeguro\Domains\Requests\Payment();

        $payment->addItems()->withParameters(
            $product['id'],
            $product['titulo'],
            1,
            $product['valor']
        );

        $payment->setCurrency("BRL");

        $payment->setRedirectUrl("http://localhost/homepet/public/landing/getstarted");

        // Set your customer information.
        $payment->setSender()->setName($client['nome']);
        $payment->setSender()->setEmail($client['email']);
       
        // $payment->setSender()->setDocument()->withParameters(
        //     'CPF',
        //     '10156568624'
        // );
        $payment->setShipping()->setAddress()->withParameters(
            $client['rua'],
            $client['numero'],
            $client['bairro'],
            $client['cep'],
            $client['cidade'],
            $client['estado'],
            'BRA',
            ''
        );

        $payment->setShipping()->setCost()->withParameters(0.00);
        $payment->setShipping()->setType()->withParameters(\PagSeguro\Enum\Shipping\Type::SEDEX);

        //Add metadata items
        // $payment->addMetadata()->withParameters('PASSENGER_CPF', '10156568624');
        // $payment->addMetadata()->withParameters('GAME_NAME', 'DOTA');
        // $payment->addMetadata()->withParameters('PASSENGER_PASSPORT', '23456', 1);

        //Add items by parameter
        $payment->addParameter()->withParameters('itemId', $product['id'])->index(3);
        $payment->addParameter()->withParameters('itemDescription', $product['titulo'])->index(3);
        $payment->addParameter()->withParameters('itemQuantity', '1')->index(3);
        $payment->addParameter()->withParameters('itemAmount', $product['valor'])->index(3);

        //Add items by parameter using an array
        $payment->addParameter()->withArray(['notificationURL', 'http://localhost/homepet/public/pagseguro/retorno']);

        /***
         * Pre Approval information
         */
        // $data = new \Datetime("now");
        // $dataInicial = $data->format('Y-m-dTH:i:s');
        // $dataFinal = $data->modify("+30 days");//->modify('+30 days');
        // $payment->setPreApproval()->setCharge('manual');
        // $payment->setPreApproval()->setName($product['titulo']);
        // // $payment->setPreApproval()->setDetails("Todo dia 30 será cobrado o valor de R100,00 referente ao seguro contra
        // //             roubo do Notebook Prata.");
        // $payment->setPreApproval()->setAmountPerPayment('64.00');
        // $payment->setPreApproval()->setMaxAmountPerPeriod('200.00');
        // $payment->setPreApproval()->setPeriod('Monthly');
        // $payment->setPreApproval()->setMaxTotalAmount('2400.00');
        // $payment->setPreApproval()->setInitialDate($dataInicial);
        // $payment->setPreApproval()->setFinalDate($dataFinal);

        $payment->setRedirectUrl("http://localhost/homepet/public/landing/getstarted");
        $payment->setNotificationUrl("http://localhost/homepet/public/pagseguro/retorno");
        $payment->setReviewUrl("http://localhost/homepet/public/landing/getstarted");

        try {

            /**
             * @todo For checkout with application use:
             * \PagSeguro\Configuration\Configure::getApplicationCredentials()
             *  ->setAuthorizationCode("FD3AF1B214EC40F0B0A6745D041BF50D")
             */
            $result = $payment->register(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );

            echo "<h2>Criando requisi&ccedil;&atilde;o de pagamento</h2>"
                . "<p>URL do pagamento: <strong>$result</strong></p>"
                . "<p><a title=\"URL do pagamento\" href=\"$result\" target=\_blank\">Ir para URL do pagamento.</a></p>";
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function conecta($email, $token){
        \PagSeguro\Configuration\Configure::setEnvironment($_ENV['PAGSEGURO_ENV']);//production or sandbox
        \PagSeguro\Configuration\Configure::setAccountCredentials(
            $email,
            $token
        );
    }
}

