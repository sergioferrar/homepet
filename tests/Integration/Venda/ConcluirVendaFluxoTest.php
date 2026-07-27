<?php

namespace App\Tests\Integration\Venda;

use App\Entity\Servico;
use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Service\Venda\VendaItemNormalizer;
use App\Tests\Integration\TenantIntegrationTestCase;

/**
 * Fluxo de conclusão de venda da ficha do pet, ponta a ponta contra o banco.
 *
 * Reproduz o mesmo pipeline de VendaController::concluirVenda() — normalizer +
 * repositories + baixa de estoque — sem passar pela camada HTTP, que exigiria
 * o banco de login provisionado. O que se testa aqui é exatamente a regra que
 * estava quebrada.
 *
 * @group integracao
 */
class ConcluirVendaFluxoTest extends TenantIntegrationTestCase
{
    private VendaItemNormalizer $normalizer;
    private $vendaRepo;
    private $itemRepo;
    private $servicoRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $doctrine = static::getContainer()->get('doctrine');
        $this->normalizer  = new VendaItemNormalizer();
        $this->vendaRepo   = $doctrine->getRepository(Venda::class);
        $this->itemRepo    = $doctrine->getRepository(VendaItem::class);
        $this->servicoRepo = $doctrine->getRepository(Servico::class);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // O caso do chamado
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Produto de R$ 45 (1 un.) + internação de R$ 80 (3 diárias).
     * Esperado: R$ 285. O bug produzia R$ 215 (45 × 3 + 80 × 1).
     *
     * @group regressao
     */
    public function testVendaComProdutoEInternacaoFechaEmDuzentosEOitentaECinco(): void
    {
        $vendaId = $this->concluirVenda([
            'consulta_id' => $this->fixtures['consulta_b_id'],
            'itens' => [
                ['ref' => 'P-' . $this->fixtures['produto_id'],        'quantidade' => 1, 'desconto' => 0],
                ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3, 'desconto' => 0],
            ],
        ]);

        $venda = $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaId);
        $itens = $this->itemRepo->listarPorVendas(self::TENANT_ID, [$vendaId])[$vendaId];

        $this->assertSame(285.00, (float) $venda['total']);
        $this->assertCount(2, $itens);

        $produto = $itens[0];
        $this->assertSame('produto', $produto['tipo']);
        $this->assertSame(1, $produto['quantidade'], 'O produto não pode receber as 3 diárias.');
        $this->assertSame(45.00, $produto['subtotal']);

        $diaria = $itens[1];
        $this->assertSame('servico', $diaria['tipo']);
        $this->assertSame(3, $diaria['quantidade']);
        $this->assertSame(240.00, $diaria['subtotal']);
    }

    /** A ordem dos itens no formulário não pode alterar o total. */
    public function testTotalIndependeDaOrdemDosItens(): void
    {
        $itens = [
            ['ref' => 'P-' . $this->fixtures['produto_id'],        'quantidade' => 1],
            ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3],
        ];

        $vendaA = $this->concluirVenda(['itens' => $itens]);
        $vendaB = $this->concluirVenda(['itens' => array_reverse($itens)]);

        $this->assertSame(
            (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaA)['total'],
            (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaB)['total'],
        );
    }

    /**
     * Payload no formato antigo com quantidade_diarias[] compactado: em vez de
     * multiplicar o item errado, tudo vira quantidade 1.
     *
     * @group regressao
     */
    public function testPayloadLegadoCompactadoNaoMultiplicaOItemErrado(): void
    {
        $vendaId = $this->concluirVenda([
            'descricao'          => ['P-' . $this->fixtures['produto_id'], 'S-' . $this->fixtures['servico_diaria_id']],
            'desconto'           => [0, 0],
            'quantidade_diarias' => [3],   // compactado — o formato que causava o bug
        ]);

        $itens = $this->itemRepo->listarPorVendas(self::TENANT_ID, [$vendaId])[$vendaId];

        $this->assertSame([1, 1], array_column($itens, 'quantidade'));
        $this->assertSame(125.00, (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaId)['total'], '45 + 80');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Vínculo com o atendimento
    // ═════════════════════════════════════════════════════════════════════════

    public function testVendaFicaVinculadaAoAtendimentoEscolhido(): void
    {
        $vendaId = $this->concluirVenda([
            'consulta_id' => $this->fixtures['consulta_a_id'],
            'itens' => [['ref' => 'S-' . $this->fixtures['servico_consulta_id'], 'quantidade' => 1]],
        ]);

        $grupos = $this->vendaRepo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']);

        $this->assertArrayHasKey($this->fixtures['consulta_a_id'], $grupos);
        $this->assertSame([$vendaId], array_map('intval', array_column($grupos[$this->fixtures['consulta_a_id']]['vendas'], 'id')));
    }

    /** Duas consultas, vendas diferentes: cada uma no seu grupo. */
    public function testVendasDeConsultasDiferentesNaoSeMisturam(): void
    {
        $this->concluirVenda([
            'consulta_id' => $this->fixtures['consulta_a_id'],
            'itens' => [['ref' => 'S-' . $this->fixtures['servico_consulta_id'], 'quantidade' => 1]],
        ]);
        $this->concluirVenda([
            'consulta_id' => $this->fixtures['consulta_b_id'],
            'itens' => [['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3]],
        ]);

        $grupos = $this->vendaRepo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']);

        $this->assertSame(150.00, $grupos[$this->fixtures['consulta_a_id']]['total']);
        $this->assertSame(240.00, $grupos[$this->fixtures['consulta_b_id']]['total']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Desconto e quantidade
    // ═════════════════════════════════════════════════════════════════════════

    public function testDescontoEhAplicadoApenasNaLinhaCorrespondente(): void
    {
        $vendaId = $this->concluirVenda([
            'itens' => [
                ['ref' => 'P-' . $this->fixtures['produto_id'],        'quantidade' => 1, 'desconto' => 5],
                ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3, 'desconto' => 0],
            ],
        ]);

        $itens = $this->itemRepo->listarPorVendas(self::TENANT_ID, [$vendaId])[$vendaId];

        $this->assertSame(40.00, $itens[0]['subtotal'], '45 − 5');
        $this->assertSame(240.00, $itens[1]['subtotal'], 'A diária não pode receber o desconto do produto.');
        $this->assertSame(280.00, (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaId)['total']);
    }

    public function testDescontoMaiorQueOItemNaoGeraTotalNegativo(): void
    {
        $vendaId = $this->concluirVenda([
            'itens' => [['ref' => 'P-' . $this->fixtures['produto_id'], 'quantidade' => 1, 'desconto' => 5000]],
        ]);

        $this->assertSame(0.00, (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaId)['total']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Estoque
    // ═════════════════════════════════════════════════════════════════════════

    public function testBaixaDeEstoqueUsaAQuantidadeDaPropriaLinha(): void
    {
        $this->concluirVenda([
            'itens' => [
                ['ref' => 'P-' . $this->fixtures['produto_id'],        'quantidade' => 2],
                ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3],
            ],
        ]);

        $estoque = (int) $this->conn()->fetchOne(
            'SELECT estoque_atual FROM ' . $this->tabela('produto') . ' WHERE id = ?',
            [$this->fixtures['produto_id']]
        );

        $this->assertSame(8, $estoque, 'Estoque inicial 10 − 2 vendidos (e não − 3, das diárias).');
    }

    public function testServicoNaoMovimentaEstoque(): void
    {
        $this->concluirVenda([
            'itens' => [['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'quantidade' => 3]],
        ]);

        $movimentos = (int) $this->conn()->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->tabela('estoque_movimento')
        );

        $this->assertSame(0, $movimentos);
    }

    public function testMovimentoDeEstoqueEhRegistrado(): void
    {
        $this->concluirVenda([
            'itens' => [['ref' => 'P-' . $this->fixtures['produto_id'], 'quantidade' => 2]],
        ]);

        $movimento = $this->conn()->fetchAssociative(
            'SELECT * FROM ' . $this->tabela('estoque_movimento') . ' ORDER BY id DESC LIMIT 1'
        );

        $this->assertSame('SAIDA', $movimento['tipo']);
        $this->assertSame(2, (int) $movimento['quantidade']);
        $this->assertSame($this->fixtures['produto_id'], (int) $movimento['produto_id']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Itens inválidos
    // ═════════════════════════════════════════════════════════════════════════

    public function testItemInexistenteEhIgnoradoSemDerrubarAVenda(): void
    {
        $vendaId = $this->concluirVenda([
            'itens' => [
                ['ref' => 'S-987654', 'quantidade' => 1],                              // não existe
                ['ref' => 'S-' . $this->fixtures['servico_consulta_id'], 'quantidade' => 1],
            ],
        ]);

        $itens = $this->itemRepo->listarPorVendas(self::TENANT_ID, [$vendaId])[$vendaId];

        $this->assertCount(1, $itens);
        $this->assertSame(150.00, (float) $this->vendaRepo->buscarPorId(self::TENANT_ID, $vendaId)['total']);
    }

    public function testPayloadSemItensValidosNaoGeraVenda(): void
    {
        $linhas = $this->normalizer->normalizar(['itens' => [['ref' => ''], ['ref' => 'abc']]]);

        $this->assertSame([], $linhas, 'Sem linhas válidas o controller devolve 422 antes de abrir a transação.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Pipeline (espelha VendaController::concluirVenda)
    // ═════════════════════════════════════════════════════════════════════════

    private function concluirVenda(array $payload): int
    {
        $linhas = $this->normalizer->normalizar($payload);
        $this->assertNotSame([], $linhas, 'O payload do teste não produziu nenhuma linha válida.');

        $conn = $this->conn();

        $vendaId = $this->vendaRepo->inserirVenda(self::TENANT_ID, [
            'estabelecimento_id' => self::TENANT_ID,
            'cliente'            => 'João da Silva',
            'pet_id'             => $this->fixtures['pet_id'],
            'consulta_id'        => $payload['consulta_id'] ?? null,
            'parcelas'           => 1,
            'origem'             => 'clinica',
            'metodo_pagamento'   => $payload['metodo_pagamento'] ?? 'pix',
            'status'             => 'Aberta',
            'observacao'         => null,
        ]);

        $total = 0.0;

        foreach ($linhas as $linha) {
            if ($linha['tipo'] === 'servico') {
                $registro = $this->servicoRepo->listaServicoPorId(self::TENANT_ID, $linha['id']);
                if (! $registro) {
                    continue;
                }
                $nome = $registro['nome'];
                $unitario = (float) $registro['valor'];
            } else {
                $registro = $conn->fetchAssociative(
                    'SELECT id, nome, preco_venda, estoque_atual FROM ' . $this->tabela('produto')
                    . ' WHERE id = :id AND estabelecimento_id = :estab',
                    ['id' => $linha['id'], 'estab' => self::TENANT_ID]
                );
                if (! $registro) {
                    continue;
                }
                $nome = $registro['nome'];
                $unitario = (float) $registro['preco_venda'];
            }

            $calculo = $this->normalizer->calcularSubtotal($unitario, $linha['quantidade'], $linha['desconto']);

            $this->itemRepo->inserirItem(self::TENANT_ID, [
                'venda_id'       => $vendaId,
                'tipo'           => $linha['tipo'],
                'produto_id'     => $linha['id'],
                'produto'        => $nome,
                'quantidade'     => $linha['quantidade'],
                'valor_unitario' => $unitario,
                'subtotal'       => $calculo['subtotal'],
            ]);

            $total += $calculo['subtotal'];

            if ($linha['tipo'] === 'produto') {
                $anterior = (int) $registro['estoque_atual'];
                $novo = max(0, $anterior - $linha['quantidade']);

                $conn->executeStatement(
                    'UPDATE ' . $this->tabela('produto') . ' SET estoque_atual = :novo WHERE id = :id',
                    ['novo' => $novo, 'id' => $linha['id']]
                );

                $conn->insert($this->tabela('estoque_movimento'), [
                    'produto_id'         => $linha['id'],
                    'estabelecimento_id' => self::TENANT_ID,
                    'quantidade'         => $linha['quantidade'],
                    'tipo'               => 'SAIDA',
                    'origem'             => 'Venda Clínica #' . $vendaId,
                    'data'               => (new \DateTime())->format('Y-m-d H:i:s'),
                    'observacao'         => 'teste',
                ]);
            }
        }

        $this->vendaRepo->atualizarTotal(self::TENANT_ID, $vendaId, round($total, 2));

        return $vendaId;
    }
}
