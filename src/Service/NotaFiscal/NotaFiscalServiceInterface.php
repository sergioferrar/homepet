<?php

namespace App\Service\NotaFiscal;

use App\Entity\Venda;

/**
 * Interface para integração com serviços de emissão de Nota Fiscal
 * Preparação para futura expansão sem quebrar código existente
 */
interface NotaFiscalServiceInterface
{
    /**
     * Emite nota fiscal para uma venda
     * 
     * @param Venda $venda
     * @return array Retorna dados da nota emitida (número, chave de acesso, etc)
     * @throws NotaFiscalException
     */
    public function emitir(Venda $venda): array;

    /**
     * Cancela uma nota fiscal emitida
     * 
     * @param string $chaveAcesso
     * @param string $motivo
     * @return bool
     * @throws NotaFiscalException
     */
    public function cancelar(string $chaveAcesso, string $motivo): bool;

    /**
     * Consulta status de uma nota fiscal
     * 
     * @param string $chaveAcesso
     * @return array Status da nota (autorizada, cancelada, etc)
     * @throws NotaFiscalException
     */
    public function consultar(string $chaveAcesso): array;

    /**
     * Verifica se o serviço está disponível
     * 
     * @return bool
     */
    public function isDisponivel(): bool;
}

/**
 * Exceção específica para erros de Nota Fiscal
 */
class NotaFiscalException extends \Exception
{
    private ?array $detalhes = null;

    public function __construct(string $message, ?array $detalhes = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->detalhes = $detalhes;
    }

    public function getDetalhes(): ?array
    {
        return $this->detalhes;
    }
}
