<?php

namespace App\DTO;

/**
 * DTO para validação de registro de venda
 */
class RegistrarVendaDTO
{
    public array $itens = [];
    public float $total;
    public string $metodo;
    public ?int $clienteId = null;
    public ?float $troco = null;
    public ?string $bandeira = null;
    public ?int $parcelas = null;
    public ?string $observacao = null;
    public ?int $petId = null;
    public string $origem;

    public static function fromArray(array $dados): self
    {
        $dto = new self();
        
        $dto->itens = $dados['itens'] ?? [];
        $dto->total = (float)($dados['total'] ?? 0);
        $dto->metodo = $dados['metodo'] ?? '';
        $dto->clienteId = !empty($dados['cliente_id']) ? (int)$dados['cliente_id'] : null;
        $dto->troco = !empty($dados['troco']) ? (float)$dados['troco'] : null;
        $dto->bandeira = $dados['bandeira'] ?? null;
        $dto->parcelas = !empty($dados['parcelas']) ? (int)$dados['parcelas'] : null;
        $dto->observacao = $dados['observacao'] ?? null;
        $dto->petId = !empty($dados['pet_id']) ? (int)$dados['pet_id'] : null;
        $dto->origem = $dados['origem'] ?? 'PDV';

        return $dto;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->itens)) {
            $errors[] = 'Nenhum item informado.';
        }

        if ($this->total <= 0) {
            $errors[] = 'Valor total inválido.';
        }

        if (empty($this->metodo)) {
            $errors[] = 'Método de pagamento não informado.';
        }

        foreach ($this->itens as $item) {
            if (empty($item['id']) || empty($item['nome'])) {
                $errors[] = 'Item inválido encontrado.';
                break;
            }
            if (!isset($item['quantidade']) || $item['quantidade'] <= 0) {
                $errors[] = "Quantidade inválida para o item '{$item['nome']}'.";
                break;
            }
            if (!isset($item['valor']) || $item['valor'] < 0) {
                $errors[] = "Valor inválido para o item '{$item['nome']}'.";
                break;
            }
        }

        return $errors;
    }
}