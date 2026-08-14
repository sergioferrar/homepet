<?php

namespace App\Service\Venda;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Normaliza os itens enviados pelo formulário de venda da ficha do pet.
 *
 * Extraído de VendaController para permitir teste unitário puro: a classe não
 * depende de Request, container, banco nem sessão — só de arrays.
 *
 * ── Por que esta classe existe ───────────────────────────────────────────────
 * O formulário antigo enviava três arrays paralelos — descricao[], desconto[]
 * e quantidade_diarias[] — e o backend casava os três pelo índice numérico.
 * Só que o input de quantidade só era renderizado para serviços de internação,
 * então o array de quantidades chegava COMPACTADO:
 *
 *   Linha 0: Produto     R$ 45  → nenhum input de quantidade
 *   Linha 1: Internação  R$ 80  → quantidade_diarias[0] = 3
 *
 * O PHP lia $quantidades[0] = 3 e aplicava no PRODUTO (45 × 3 = 135), enquanto
 * a internação caía no default 1 (80 × 1). Total R$ 215 em vez de R$ 285.
 *
 * O formato preferido agora é indexado — itens[i][ref|quantidade|desconto] —
 * em que a quantidade está amarrada à linha, não à posição num array separado.
 */
final class VendaItemNormalizer
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Converte o payload bruto do formulário em uma lista fechada de linhas.
     *
     * @param array $payload Normalmente $request->request->all()
     *
     * @return array<int, array{tipo: string, id: int, quantidade: int, desconto: float}>
     */
    public function normalizar(array $payload): array
    {
        $itens = $payload['itens'] ?? null;

        if (is_array($itens) && $itens !== []) {
            return $this->normalizarFormatoIndexado($itens);
        }

        return $this->normalizarFormatoLegado($payload);
    }

    /**
     * Formato preferido: itens[i][ref], itens[i][quantidade], itens[i][desconto],
     * itens[i][pets][] (um ou mais pets do mesmo tutor).
     *
     * ── Atendimento com 2 ou mais pets ───────────────────────────────────────
     * Quando o mesmo procedimento é feito em vários pets do tutor (ex.: coleta
     * de sangue e exame de fezes nos dois cachorros), o formulário manda UMA
     * linha com vários pets marcados. Aqui essa linha é EXPANDIDA em uma linha
     * por pet — é isso que faz o valor multiplicar pela quantidade de pets
     * atendidos em vez de ser cobrado uma vez só.
     *
     *   itens[0][ref]=S-3, itens[0][pets][]=10, itens[0][pets][]=11
     *   → 2 linhas de S-3 (pet 10 e pet 11)
     *
     * A quantidade e o desconto informados valem POR PET: cada linha gerada
     * carrega os mesmos valores, exatamente como se o veterinário tivesse
     * lançado o item manualmente para cada pet.
     *
     * @return array<int, array{tipo: string, id: int, quantidade: int, desconto: float, pet_id: int|null}>
     */
    private function normalizarFormatoIndexado(array $itens): array
    {
        $linhas = [];

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ref = trim((string) ($item['ref'] ?? $item['descricao'] ?? ''));
            if ($ref === '') {
                continue;
            }

            $resolvido = $this->resolverReferencia($ref);
            if ($resolvido === null) {
                continue;
            }

            $quantidade = $this->paraQuantidade($item['quantidade'] ?? 1);
            $desconto   = $this->paraDinheiro($item['desconto'] ?? 0);

            foreach ($this->extrairPets($item) as $petId) {
                $linhas[] = [
                    'tipo'       => $resolvido['tipo'],
                    'id'         => $resolvido['id'],
                    'quantidade' => $quantidade,
                    'desconto'   => $desconto,
                    'pet_id'     => $petId,
                ];
            }
        }

        return $linhas;
    }

    /**
     * Lê os pets aos quais um item se aplica.
     *
     * Aceita o formato novo — itens[i][pets][] com vários ids — e o antigo,
     * itens[i][pet_id] com um id só, para não quebrar telas/integrações que
     * ainda mandam o campo único.
     *
     * Ids repetidos são descartados: marcar o mesmo pet duas vezes não pode
     * cobrar em dobro.
     *
     * @return array<int, int|null> Nunca vazio — sem pet informado devolve [null],
     *                              que gera uma linha única (comportamento antigo).
     */
    private function extrairPets(array $item): array
    {
        $brutos = $item['pets'] ?? $item['pet_ids'] ?? $item['pet_id'] ?? null;

        if (! is_array($brutos)) {
            $brutos = ($brutos === null || $brutos === '') ? [] : [$brutos];
        }

        $pets = [];

        foreach ($brutos as $bruto) {
            $bruto = trim((string) $bruto);

            if ($bruto === '' || ! ctype_digit($bruto) || (int) $bruto <= 0) {
                continue;
            }

            $pets[(int) $bruto] = (int) $bruto; // chave = id → dedupe preservando a ordem
        }

        return $pets === [] ? [null] : array_values($pets);
    }

    /**
     * Formato antigo: descricao[] + desconto[] (+ quantidade[] ou quantidade_diarias[]).
     *
     * quantidade_diarias[] só é aceito quando tem exatamente uma entrada por
     * linha de descricao[]. Se estiver compactado (o caso do bug), a quantidade
     * é ignorada e um warning vai para o log — bem melhor do que multiplicar o
     * item errado em silêncio.
     *
     * @return array<int, array{tipo: string, id: int, quantidade: int, desconto: float}>
     */
    private function normalizarFormatoLegado(array $payload): array
    {
        $descricoes  = array_values((array) ($payload['descricao'] ?? []));
        $descontos   = array_values((array) ($payload['desconto'] ?? []));
        $quantidades = array_values((array) ($payload['quantidade'] ?? []));
        $diarias     = array_values((array) ($payload['quantidade_diarias'] ?? []));

        $usarDiarias = $quantidades === []
            && $diarias !== []
            && count($diarias) === count($descricoes);

        if ($quantidades === [] && $diarias !== [] && ! $usarDiarias) {
            $this->logger->warning(
                'Venda clínica: quantidade_diarias[] desalinhado com descricao[] — quantidades ignoradas.',
                ['descricoes' => count($descricoes), 'diarias' => count($diarias)],
            );
        }

        $linhas = [];

        foreach ($descricoes as $i => $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }

            $resolvido = $this->resolverReferencia($ref);
            if ($resolvido === null) {
                continue;
            }

            $quantidadeBruta = $usarDiarias
                ? ($diarias[$i] ?? 1)
                : ($quantidades[$i] ?? 1);

            $linhas[] = [
                'tipo'       => $resolvido['tipo'],
                'id'         => $resolvido['id'],
                'quantidade' => $this->paraQuantidade($quantidadeBruta),
                'desconto'   => $this->paraDinheiro($descontos[$i] ?? 0),
                // O formato legado não tem vínculo com pet — mantém a chave
                // presente para o consumidor não precisar de ?? null.
                'pet_id'     => null,
            ];
        }

        return $linhas;
    }

    /**
     * Interpreta a referência de um item: "S-12" (serviço), "P-7" (produto)
     * ou apenas "12" (serviço — formato histórico do select da ficha).
     *
     * @return array{tipo: string, id: int}|null Null se a referência for inválida
     */
    public function resolverReferencia(string $ref): ?array
    {
        $ref = trim($ref);

        if (str_starts_with($ref, 'S-')) {
            $tipo = 'servico';
            $id   = substr($ref, 2);
        } elseif (str_starts_with($ref, 'P-')) {
            $tipo = 'produto';
            $id   = substr($ref, 2);
        } else {
            $tipo = 'servico';
            $id   = $ref;
        }

        if (! ctype_digit($id) || (int) $id <= 0) {
            return null;
        }

        return ['tipo' => $tipo, 'id' => (int) $id];
    }

    /**
     * Calcula o subtotal de uma linha, com o desconto travado no intervalo
     * [0, bruto] — desconto nunca pode deixar o item negativo.
     *
     * @return array{bruto: float, desconto: float, subtotal: float}
     */
    public function calcularSubtotal(float $valorUnitario, int $quantidade, float $desconto): array
    {
        $quantidade = max(1, $quantidade);
        $bruto      = round($valorUnitario * $quantidade, 2);
        $desconto   = min(max(0.0, $desconto), $bruto);

        return [
            'bruto'    => $bruto,
            'desconto' => round($desconto, 2),
            'subtotal' => round($bruto - $desconto, 2),
        ];
    }

    /** Quantidade nunca menor que 1, mesmo se vier vazia, negativa ou lixo. */
    private function paraQuantidade(mixed $valor): int
    {
        return max(1, (int) $valor);
    }

    /** Aceita "12,50" (pt-BR) e "12.50"; nunca retorna negativo. */
    private function paraDinheiro(mixed $valor): float
    {
        return max(0.0, (float) str_replace(',', '.', (string) $valor));
    }
}