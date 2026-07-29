<?php

namespace App\DTO;

/**
 * DTO para validação de registro de venda
 */
class RegistrarVendaDTO
{
    public array $itens = [];
    public float $total;
    public float $desconto = 0.0;
    public string $metodo;
    public ?int $clienteId = null;
    public ?float $troco = null;
    public ?string $bandeira = null;
    public ?int $parcelas = null;
    public ?string $observacao = null;
    public ?int $petId = null;
    public string $origem;

    /**
     * Formas de pagamento (pagamento dividido). Cada item:
     *   ['metodo' => 'pix', 'valor' => 100.0, 'bandeira' => null, 'parcelas' => null]
     * Vazio = pagamento simples (usa $metodo).
     *
     * @var array<int, array>
     */
    public array $pagamentos = [];

    public static function fromArray(array $dados): self
    {
        $dto = new self();
        
        $dto->itens = $dados['itens'] ?? [];
        $dto->total = (float)($dados['total'] ?? 0);
        $dto->desconto = (float)($dados['desconto'] ?? 0);
        $dto->metodo = $dados['metodo'] ?? '';
        $dto->clienteId = !empty($dados['cliente_id']) ? (int)$dados['cliente_id'] : null;
        $dto->troco = !empty($dados['troco']) ? (float)$dados['troco'] : null;
        $dto->bandeira = $dados['bandeira'] ?? null;
        $dto->parcelas = !empty($dados['parcelas']) ? (int)$dados['parcelas'] : null;
        $dto->observacao = $dados['observacao'] ?? null;
        $dto->petId = !empty($dados['pet_id']) ? (int)$dados['pet_id'] : null;
        $dto->origem = $dados['origem'] ?? 'PDV';

        // Normaliza as formas de pagamento divididas (se houver)
        $dto->pagamentos = [];
        foreach ($dados['pagamentos'] ?? [] as $p) {
            $metodo = trim((string)($p['metodo'] ?? ''));
            $valor  = (float)($p['valor'] ?? 0);
            if ($metodo === '' || $valor <= 0) {
                continue;
            }
            $dto->pagamentos[] = [
                'metodo'   => $metodo,
                'valor'    => round($valor, 2),
                'bandeira' => !empty($p['bandeira']) ? (string)$p['bandeira'] : null,
                'parcelas' => !empty($p['parcelas']) ? (int)$p['parcelas'] : null,
            ];
        }

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

        // Validação do pagamento dividido (quando informado)
        if (!empty($this->pagamentos)) {
            $soma = 0.0;
            foreach ($this->pagamentos as $p) {
                if ($p['metodo'] === 'pendente') {
                    $errors[] = 'Pagamento dividido não pode incluir a forma "Pendente".';
                    break;
                }
                $soma += (float) $p['valor'];
            }
            // A soma das formas deve cobrir o total (o excedente é troco, p/ dinheiro).
            if ($this->total > 0 && $soma + 0.01 < $this->total) {
                $errors[] = 'A soma das formas de pagamento (R$ ' . number_format($soma, 2, ',', '.')
                    . ') é menor que o total (R$ ' . number_format($this->total, 2, ',', '.') . ').';
            }
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