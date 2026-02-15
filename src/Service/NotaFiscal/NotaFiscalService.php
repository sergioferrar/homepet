<?php

namespace App\Service\NotaFiscal;

use App\Entity\Venda;
use Psr\Log\LoggerInterface;

/**
 * Implementação stub do serviço de Nota Fiscal
 * Preparado para futura integração com APIs de NF-e
 * 
 * Para integrar:
 * 1. Instalar SDK da API de NF-e escolhida (ex: Focus NFe, Bling, etc)
 * 2. Implementar métodos desta classe
 * 3. Configurar credenciais no .env
 * 4. Ativar no fluxo de finalização de venda
 */
class NotaFiscalService implements NotaFiscalServiceInterface
{
    private LoggerInterface $logger;
    private bool $habilitado = false;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        
        // TODO: Ler do .env se está habilitado
        // $this->habilitado = $_ENV['NOTA_FISCAL_ENABLED'] ?? false;
    }

    public function emitir(Venda $venda): array
    {
        if (!$this->habilitado) {
            $this->logger->info('Emissão de NF-e desabilitada. Venda #{id} registrada sem nota.', [
                'id' => $venda->getId()
            ]);
            
            return [
                'sucesso' => false,
                'motivo' => 'Serviço de nota fiscal não habilitado',
                'venda_id' => $venda->getId()
            ];
        }

        // TODO: Implementar integração real
        // Exemplo de estrutura:
        // 
        // try {
        //     $nfe = $this->apiClient->emitirNFe([
        //         'natureza_operacao' => 'Venda',
        //         'cliente' => [
        //             'nome' => $venda->getCliente(),
        //             // ... outros dados do cliente
        //         ],
        //         'itens' => $this->montarItens($venda),
        //         'forma_pagamento' => $venda->getMetodoPagamento(),
        //         'valor_total' => $venda->getTotal(),
        //     ]);
        //
        //     return [
        //         'sucesso' => true,
        //         'numero' => $nfe['numero'],
        //         'chave_acesso' => $nfe['chave'],
        //         'data_emissao' => new \DateTime(),
        //     ];
        // } catch (\Exception $e) {
        //     throw new NotaFiscalException('Erro ao emitir nota fiscal', [
        //         'erro_api' => $e->getMessage(),
        //         'venda_id' => $venda->getId()
        //     ]);
        // }

        $this->logger->warning('Método emitir() não implementado. Venda #{id}', [
            'id' => $venda->getId()
        ]);

        return [
            'sucesso' => false,
            'motivo' => 'Integração de nota fiscal aguardando implementação'
        ];
    }

    public function cancelar(string $chaveAcesso, string $motivo): bool
    {
        if (!$this->habilitado) {
            $this->logger->info('Cancelamento de NF-e desabilitado.');
            return false;
        }

        // TODO: Implementar cancelamento real
        $this->logger->warning('Método cancelar() não implementado. Chave: {chave}', [
            'chave' => $chaveAcesso
        ]);

        return false;
    }

    public function consultar(string $chaveAcesso): array
    {
        if (!$this->habilitado) {
            return [
                'disponivel' => false,
                'status' => 'Serviço desabilitado'
            ];
        }

        // TODO: Implementar consulta real
        $this->logger->warning('Método consultar() não implementado. Chave: {chave}', [
            'chave' => $chaveAcesso
        ]);

        return [
            'disponivel' => false,
            'status' => 'Aguardando implementação'
        ];
    }

    public function isDisponivel(): bool
    {
        return $this->habilitado;
    }
}
