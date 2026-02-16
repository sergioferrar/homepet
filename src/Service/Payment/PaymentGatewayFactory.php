<?php

namespace App\Service\Payment;

use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentGatewayFactory
{
    private $container;
    private $gateways = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Registra um gateway de pagamento
     */
    public function registerGateway(string $name, PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    /**
     * Obtém um gateway específico pelo nome
     */
    public function getGateway(string $name): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$name])) {
            throw new \InvalidArgumentException("Gateway '{$name}' não está registrado.");
        }

        return $this->gateways[$name];
    }

    /**
     * Obtém o gateway padrão configurado
     */
    public function getDefaultGateway(): PaymentGatewayInterface
    {
        $defaultGatewayName = $_ENV['DEFAULT_PAYMENT_GATEWAY'] ?? 'mercadopago';
        
        return $this->getGateway($defaultGatewayName);
    }

    /**
     * Lista todos os gateways disponíveis
     */
    public function getAvailableGateways(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * Verifica se um gateway está registrado
     */
    public function hasGateway(string $name): bool
    {
        return isset($this->gateways[$name]);
    }
}
