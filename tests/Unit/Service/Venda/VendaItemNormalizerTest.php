<?php

namespace App\Tests\Unit\Service\Venda;

use App\Service\Venda\VendaItemNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Testes da normalização de itens da venda da ficha do pet.
 *
 * O grupo "regressao" cobre o bug em que a quantidade de diárias era aplicada
 * ao item errado da venda.
 *
 * @covers \App\Service\Venda\VendaItemNormalizer
 */
class VendaItemNormalizerTest extends TestCase
{
    private VendaItemNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new VendaItemNormalizer();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REGRESSÃO — o bug relatado
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Cenário exato do chamado: um produto de R$ 45 e uma internação de R$ 80
     * com 3 diárias. Antes, os 3 caíam no produto (45 × 3 = 135).
     *
     * @group regressao
     */
    public function testQuantidadeNaoVazaEntreItens(): void
    {
        $linhas = $this->normalizer->normalizar([
            'itens' => [
                ['ref' => 'P-7', 'quantidade' => 1, 'desconto' => 0],   // produto R$ 45
                ['ref' => 'S-3', 'quantidade' => 3, 'desconto' => 0],   // internação R$ 80
            ],
        ]);

        $this->assertCount(2, $linhas);

        $this->assertSame('produto', $linhas[0]['tipo']);
        $this->assertSame(7, $linhas[0]['id']);
        $this->assertSame(1, $linhas[0]['quantidade'], 'O produto NÃO pode herdar as diárias da internação.');

        $this->assertSame('servico', $linhas[1]['tipo']);
        $this->assertSame(3, $linhas[1]['id']);
        $this->assertSame(3, $linhas[1]['quantidade']);
    }

    /**
     * O total da venda do cenário acima tem que fechar em R$ 285 (45 + 240),
     * e não nos R$ 215 que o bug produzia.
     *
     * @group regressao
     */
    public function testTotalDoCenarioRelatado(): void
    {
        $precos = ['P-7' => 45.00, 'S-3' => 80.00];

        $linhas = $this->normalizer->normalizar([
            'itens' => [
                ['ref' => 'P-7', 'quantidade' => 1, 'desconto' => 0],
                ['ref' => 'S-3', 'quantidade' => 3, 'desconto' => 0],
            ],
        ]);

        $total = 0.0;
        foreach ($linhas as $i => $linha) {
            $ref = ($linha['tipo'] === 'produto' ? 'P-' : 'S-') . $linha['id'];
            $total += $this->normalizer->calcularSubtotal(
                $precos[$ref],
                $linha['quantidade'],
                $linha['desconto'],
            )['subtotal'];
        }

        $this->assertSame(285.00, round($total, 2));
    }

    /**
     * A ordem das linhas não pode mudar o resultado — o bug só aparecia quando
     * a internação NÃO era o primeiro item, o que fazia o problema parecer
     * intermitente.
     *
     * @group regressao
     * @dataProvider ordensDeItens
     */
    public function testResultadoIndependeDaOrdemDosItens(array $itens, array $quantidadesEsperadas): void
    {
        $linhas = $this->normalizer->normalizar(['itens' => $itens]);

        $this->assertSame($quantidadesEsperadas, array_column($linhas, 'quantidade'));
    }

    public function ordensDeItens(): array
    {
        return [
            'internação por último'  => [
                [['ref' => 'P-7', 'quantidade' => 1], ['ref' => 'S-3', 'quantidade' => 3]],
                [1, 3],
            ],
            'internação primeiro'    => [
                [['ref' => 'S-3', 'quantidade' => 3], ['ref' => 'P-7', 'quantidade' => 1]],
                [3, 1],
            ],
            'internação no meio'     => [
                [
                    ['ref' => 'P-7', 'quantidade' => 2],
                    ['ref' => 'S-3', 'quantidade' => 5],
                    ['ref' => 'P-9', 'quantidade' => 1],
                ],
                [2, 5, 1],
            ],
            'duas internações'       => [
                [['ref' => 'S-3', 'quantidade' => 3], ['ref' => 'S-4', 'quantidade' => 7]],
                [3, 7],
            ],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FORMATO LEGADO — compatibilidade e proteção contra o desalinhamento
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * quantidade_diarias[] compactado (1 entrada para 2 linhas) é o formato que
     * causava o bug. Em vez de aplicar no item errado, tudo vira quantidade 1.
     *
     * @group regressao
     */
    public function testDiariasDesalinhadasSaoIgnoradasEmVezDeAplicadasNoItemErrado(): void
    {
        $linhas = $this->normalizer->normalizar([
            'descricao'          => ['P-7', 'S-3'],
            'desconto'           => [0, 0],
            'quantidade_diarias' => [3],   // compactado: só a internação gerava input
        ]);

        $this->assertSame([1, 1], array_column($linhas, 'quantidade'));
    }

    /** E o desalinhamento tem que aparecer no log, não passar batido. */
    public function testDesalinhamentoGeraWarningNoLog(): void
    {
        $logger = new class extends AbstractLogger {
            public array $registros = [];

            public function log($level, $message, array $context = []): void
            {
                $this->registros[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        (new VendaItemNormalizer($logger))->normalizar([
            'descricao'          => ['P-7', 'S-3'],
            'quantidade_diarias' => [3],
        ]);

        $this->assertCount(1, $logger->registros);
        $this->assertSame('warning', $logger->registros[0]['level']);
        $this->assertStringContainsString('desalinhado', $logger->registros[0]['message']);
        $this->assertSame(['descricoes' => 2, 'diarias' => 1], $logger->registros[0]['context']);
    }

    /** Quando há uma diária por linha o array é confiável e deve ser usado. */
    public function testDiariasAlinhadasSaoAceitas(): void
    {
        $linhas = $this->normalizer->normalizar([
            'descricao'          => ['P-7', 'S-3'],
            'desconto'           => [0, 0],
            'quantidade_diarias' => [1, 3],
        ]);

        $this->assertSame([1, 3], array_column($linhas, 'quantidade'));
    }

    /** quantidade[] (novo nome no formato legado) tem prioridade sobre diárias. */
    public function testQuantidadeTemPrioridadeSobreDiarias(): void
    {
        $linhas = $this->normalizer->normalizar([
            'descricao'          => ['S-3'],
            'quantidade'         => [4],
            'quantidade_diarias' => [99],
        ]);

        $this->assertSame(4, $linhas[0]['quantidade']);
    }

    /** Select antigo mandava só o id, sem prefixo — tratado como serviço. */
    public function testReferenciaSemPrefixoEhTratadaComoServico(): void
    {
        $linhas = $this->normalizer->normalizar([
            'descricao' => ['12'],
            'desconto'  => [0],
        ]);

        $this->assertSame('servico', $linhas[0]['tipo']);
        $this->assertSame(12, $linhas[0]['id']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RESOLUÇÃO DE REFERÊNCIA
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider referenciasValidas */
    public function testResolverReferenciaValida(string $ref, string $tipo, int $id): void
    {
        $this->assertSame(['tipo' => $tipo, 'id' => $id], $this->normalizer->resolverReferencia($ref));
    }

    public function referenciasValidas(): array
    {
        return [
            'serviço com prefixo' => ['S-12', 'servico', 12],
            'produto com prefixo' => ['P-7', 'produto', 7],
            'sem prefixo'         => ['3', 'servico', 3],
            'com espaços'         => ['  S-5  ', 'servico', 5],
            'id grande'           => ['P-999999', 'produto', 999999],
        ];
    }

    /** @dataProvider referenciasInvalidas */
    public function testResolverReferenciaInvalidaRetornaNull(string $ref): void
    {
        $this->assertNull($this->normalizer->resolverReferencia($ref));
    }

    public function referenciasInvalidas(): array
    {
        return [
            'vazia'            => [''],
            'só o prefixo'     => ['S-'],
            'id zero'          => ['S-0'],
            'id negativo'      => ['S--5'],
            'não numérico'     => ['S-abc'],
            'texto solto'      => ['internação'],
            'decimal'          => ['S-1.5'],
            'tentativa de SQL' => ["S-1; DROP TABLE venda"],
        ];
    }

    /** Referências inválidas são descartadas, sem derrubar as válidas junto. */
    public function testItensInvalidosSaoDescartadosSemQuebrarOsValidos(): void
    {
        $linhas = $this->normalizer->normalizar([
            'itens' => [
                ['ref' => 'S-3', 'quantidade' => 1],
                ['ref' => '',    'quantidade' => 2],   // vazio
                ['ref' => 'X-9', 'quantidade' => 1],   // prefixo desconhecido → id "X-9" não numérico
                ['ref' => 'P-7', 'quantidade' => 1],
                'não é array',
            ],
        ]);

        $this->assertCount(2, $linhas);
        $this->assertSame([3, 7], array_column($linhas, 'id'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // QUANTIDADE E DESCONTO
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider quantidadesInvalidas */
    public function testQuantidadeNuncaEhMenorQueUm(mixed $entrada): void
    {
        $linhas = $this->normalizer->normalizar([
            'itens' => [['ref' => 'S-1', 'quantidade' => $entrada]],
        ]);

        $this->assertSame(1, $linhas[0]['quantidade']);
    }

    public function quantidadesInvalidas(): array
    {
        return [
            'zero'      => [0],
            'negativa'  => [-5],
            'vazia'     => [''],
            'texto'     => ['abc'],
            'null'      => [null],
        ];
    }

    /** Desconto aceita vírgula decimal (pt-BR) e nunca fica negativo. */
    public function testDescontoAceitaVirgulaENuncaEhNegativo(): void
    {
        $linhas = $this->normalizer->normalizar([
            'itens' => [
                ['ref' => 'S-1', 'desconto' => '12,50'],
                ['ref' => 'S-2', 'desconto' => '7.25'],
                ['ref' => 'S-3', 'desconto' => -30],
            ],
        ]);

        $this->assertSame(12.50, $linhas[0]['desconto']);
        $this->assertSame(7.25, $linhas[1]['desconto']);
        $this->assertSame(0.0, $linhas[2]['desconto']);
    }

    public function testCalcularSubtotalMultiplicaPelaQuantidade(): void
    {
        $this->assertSame(
            ['bruto' => 240.00, 'desconto' => 0.0, 'subtotal' => 240.00],
            $this->normalizer->calcularSubtotal(80.00, 3, 0.0),
        );
    }

    public function testCalcularSubtotalAplicaDesconto(): void
    {
        $r = $this->normalizer->calcularSubtotal(80.00, 3, 40.00);

        $this->assertSame(240.00, $r['bruto']);
        $this->assertSame(40.00, $r['desconto']);
        $this->assertSame(200.00, $r['subtotal']);
    }

    /** Desconto maior que o item é travado no bruto — subtotal nunca negativo. */
    public function testDescontoMaiorQueOItemNaoGeraSubtotalNegativo(): void
    {
        $r = $this->normalizer->calcularSubtotal(45.00, 1, 500.00);

        $this->assertSame(45.00, $r['desconto']);
        $this->assertSame(0.0, $r['subtotal']);
    }

    public function testCalcularSubtotalArredondaParaDuasCasas(): void
    {
        $r = $this->normalizer->calcularSubtotal(33.333, 3, 0.0);

        $this->assertSame(100.00, $r['subtotal']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PAYLOADS VAZIOS
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider payloadsVazios */
    public function testPayloadVazioRetornaListaVazia(array $payload): void
    {
        $this->assertSame([], $this->normalizer->normalizar($payload));
    }

    public function payloadsVazios(): array
    {
        return [
            'nada'                => [[]],
            'itens vazio'         => [['itens' => []]],
            'descricao vazia'     => [['descricao' => []]],
            'só método pagamento' => [['metodo_pagamento' => 'pix']],
            'itens só com lixo'   => [['itens' => [['ref' => ''], ['ref' => 'abc']]]],
        ];
    }

    /** itens[] tem precedência: se vier preenchido, o legado é ignorado. */
    public function testFormatoIndexadoTemPrecedenciaSobreOLegado(): void
    {
        $linhas = $this->normalizer->normalizar([
            'itens'     => [['ref' => 'S-1', 'quantidade' => 9]],
            'descricao' => ['S-99'],
        ]);

        $this->assertCount(1, $linhas);
        $this->assertSame(1, $linhas[0]['id']);
        $this->assertSame(9, $linhas[0]['quantidade']);
    }
}
