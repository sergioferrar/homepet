<?php

namespace App\DTO;

/**
 * DTO para registro de saída de caixa
 */
class RegistrarSaidaDTO
{
    public string $descricao;
    public float $valor;
    public bool $verificarSaldo = false;
    public bool $registrarFinanceiro = false;
    public string $metodoPagamento = 'Dinheiro';

    public static function fromArray(array $dados): self
    {
        $dto = new self();
        
        $dto->descricao = trim($dados['descricao'] ?? '');
        $dto->valor = (float)($dados['valor'] ?? 0);
        $dto->verificarSaldo = (bool)($dados['verificar_saldo'] ?? false);
        $dto->registrarFinanceiro = (bool)($dados['registrar_financeiro'] ?? false);
        $dto->metodoPagamento = $dados['metodo_pagamento'] ?? 'Dinheiro';

        return $dto;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->descricao)) {
            $errors[] = 'Informe a descrição da saída.';
        }

        if ($this->valor <= 0) {
            $errors[] = 'Informe um valor válido.';
        }

        return $errors;
    }
}