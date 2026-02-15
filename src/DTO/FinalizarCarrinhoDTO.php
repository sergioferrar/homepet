<?php

namespace App\DTO;

/**
 * DTO para finalização de venda em carrinho
 */
class FinalizarCarrinhoDTO
{
    public int $vendaId;
    public string $metodoPagamento;
    public ?string $bandeiraCartao = null;
    public ?int $parcelas = null;

    public static function fromArray(int $vendaId, array $dados): self
    {
        $dto = new self();
        
        $dto->vendaId = $vendaId;
        $dto->metodoPagamento = $dados['metodo_pagamento'] ?? '';
        $dto->bandeiraCartao = $dados['bandeira_cartao'] ?? null;
        $dto->parcelas = !empty($dados['parcelas']) ? (int)$dados['parcelas'] : null;

        return $dto;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->metodoPagamento)) {
            $errors[] = 'Método de pagamento não informado.';
        }

        if ($this->metodoPagamento === 'cartao' && empty($this->bandeiraCartao)) {
            $errors[] = 'Bandeira do cartão não informada.';
        }

        return $errors;
    }
}
