<?php

namespace App\Service\Payment;

interface PaymentGatewayInterface
{
    /**
     * Criar um pagamento único
     * 
     * @param array $data Dados do pagamento
     * @return array Retorna informações do pagamento criado
     */
    public function createPayment(array $data): array;

    /**
     * Criar uma assinatura recorrente
     * 
     * @param array $data Dados da assinatura
     * @return array Retorna informações da assinatura criada
     */
    public function createSubscription(array $data): array;

    /**
     * Cancelar uma assinatura
     * 
     * @param string $subscriptionId ID da assinatura
     * @return bool Retorna true se cancelado com sucesso
     */
    public function cancelSubscription(string $subscriptionId): bool;

    /**
     * Consultar status de um pagamento
     * 
     * @param string $paymentId ID do pagamento
     * @return array Retorna informações do pagamento
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Consultar status de uma assinatura
     * 
     * @param string $subscriptionId ID da assinatura
     * @return array Retorna informações da assinatura
     */
    public function getSubscriptionStatus(string $subscriptionId): array;

    /**
     * Processar webhook do gateway
     * 
     * @param array $data Dados recebidos do webhook
     * @return array Retorna dados processados
     */
    public function processWebhook(array $data): array;

    /**
     * Obter nome do gateway
     * 
     * @return string Nome do gateway
     */
    public function getGatewayName(): string;
}
