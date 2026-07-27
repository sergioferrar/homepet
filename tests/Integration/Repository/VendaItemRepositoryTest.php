<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Servico;
use App\Entity\VendaItem;
use App\Repository\ServicoRepository;
use App\Repository\VendaItemRepository;
use App\Tests\Integration\TenantIntegrationTestCase;

/**
 * @covers \App\Repository\VendaItemRepository
 * @covers \App\Repository\ServicoRepository
 * @group integracao
 */
class VendaItemRepositoryTest extends TenantIntegrationTestCase
{
    private VendaItemRepository $repo;
    private ServicoRepository $servicoRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $doctrine = static::getContainer()->get('doctrine');
        $this->repo = $doctrine->getRepository(VendaItem::class);
        $this->servicoRepo = $doctrine->getRepository(Servico::class);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // listarPorVendas — o método que substituiu o laço com vazamento
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * O código antigo declarava o array de itens FORA do laço de vendas, então
     * os itens de uma venda apareciam também na seguinte. É o que "embolava"
     * as informações na tela.
     *
     * @group regressao
     */
    public function testItensNaoVazamEntreVendas(): void
    {
        $vendaA = $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', [
            ['ref' => 'S-' . $this->fixtures['servico_consulta_id'], 'qtd' => 1, 'unit' => 150.00, 'nome' => 'Consulta Clínica'],
        ]);

        $vendaB = $this->criarVenda($this->fixtures['consulta_b_id'], 'Paga', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 1, 'unit' => 45.00, 'nome' => 'Antipulgas 10kg'],
            ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'qtd' => 3, 'unit' => 80.00, 'nome' => 'Internação / Diária'],
        ]);

        $itens = $this->repo->listarPorVendas(self::TENANT_ID, [$vendaA, $vendaB]);

        $this->assertCount(1, $itens[$vendaA], 'A venda A tem exatamente 1 item.');
        $this->assertCount(2, $itens[$vendaB], 'A venda B tem exatamente 2 itens.');
        $this->assertSame('Consulta Clínica', $itens[$vendaA][0]['item']);
        $this->assertNotContains('Consulta Clínica', array_column($itens[$vendaB], 'item'));
    }

    /**
     * Cada item guarda a SUA quantidade — o produto continua 1 mesmo ao lado
     * de uma internação de 3 diárias.
     *
     * @group regressao
     */
    public function testCadaItemMantemSuaPropriaQuantidade(): void
    {
        $venda = $this->criarVenda($this->fixtures['consulta_b_id'], 'Paga', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 1, 'unit' => 45.00, 'nome' => 'Antipulgas 10kg'],
            ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'qtd' => 3, 'unit' => 80.00, 'nome' => 'Internação / Diária'],
        ]);

        $itens = $this->repo->listarPorVendas(self::TENANT_ID, [$venda])[$venda];

        $this->assertSame(1, $itens[0]['quantidade']);
        $this->assertSame(45.00, $itens[0]['subtotal']);
        $this->assertSame(3, $itens[1]['quantidade']);
        $this->assertSame(240.00, $itens[1]['subtotal']);
        $this->assertSame(285.00, array_sum(array_column($itens, 'subtotal')));
    }

    /**
     * O subtotal vem do banco (já com desconto), em vez de ser recalculado
     * como qtd × unitário — que fazia o card divergir de venda.total.
     */
    public function testSubtotalRespeitaODescontoGravado(): void
    {
        $venda = $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', [
            ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'qtd' => 3, 'unit' => 80.00, 'desconto' => 40.00, 'nome' => 'Internação / Diária'],
        ]);

        $item = $this->repo->listarPorVendas(self::TENANT_ID, [$venda])[$venda][0];

        $this->assertSame(200.00, $item['subtotal'], '240 − 40 de desconto');
        $this->assertSame(40.00, $item['desconto'], 'O desconto é derivado de bruto − subtotal.');
    }

    public function testResultadoEhIndexadoPorVendaId(): void
    {
        $venda = $this->criarVenda(null, 'Paga', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 2, 'unit' => 45.00, 'nome' => 'Antipulgas'],
        ]);

        $itens = $this->repo->listarPorVendas(self::TENANT_ID, [$venda]);

        $this->assertSame([$venda], array_keys($itens));
    }

    public function testVendaSemItensNaoApareceNoResultado(): void
    {
        $vazia = $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', []);

        $this->assertArrayNotHasKey($vazia, $this->repo->listarPorVendas(self::TENANT_ID, [$vazia]));
    }

    public function testListaVaziaNaoDisparaConsulta(): void
    {
        $this->assertSame([], $this->repo->listarPorVendas(self::TENANT_ID, []));
    }

    public function testIdsInvalidosSaoDescartados(): void
    {
        $this->assertSame([], $this->repo->listarPorVendas(self::TENANT_ID, [0, null, '']));
    }

    /** O INNER JOIN com venda impede ler itens de outro estabelecimento. */
    public function testNaoRetornaItensDeOutroEstabelecimento(): void
    {
        $vendaVizinha = $this->criarVenda(null, 'Paga', [
            ['ref' => 'P-1', 'qtd' => 1, 'unit' => 999.00, 'nome' => 'Item do vizinho'],
        ], self::TENANT_VIZINHO_ID, 1);

        $this->assertSame([], $this->repo->listarPorVendas(self::TENANT_ID, [$vendaVizinha]));
    }

    public function testInserirItemGravaTodasAsColunas(): void
    {
        $venda = $this->criarVenda($this->fixtures['consulta_a_id'], 'Aberta', []);

        $this->repo->inserirItem(self::TENANT_ID, [
            'venda_id'       => $venda,
            'tipo'           => 'servico',
            'produto_id'     => $this->fixtures['servico_diaria_id'],
            'produto'        => 'Internação / Diária',
            'quantidade'     => 3,
            'valor_unitario' => 80.00,
            'subtotal'       => 240.00,
        ]);

        $item = $this->repo->listarPorVendas(self::TENANT_ID, [$venda])[$venda][0];

        $this->assertSame('servico', $item['tipo']);
        $this->assertSame(3, $item['quantidade']);
        $this->assertSame(80.00, $item['valor']);
        $this->assertSame(240.00, $item['subtotal']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ServicoRepository — API removida no DBAL 3
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Usava Connection::query()->fetch(), que não existe mais no DBAL 3
     * (Call to undefined method Doctrine\DBAL\Result::fetch()). Como é chamado
     * para TODO item de serviço, derrubava a venda inteira.
     *
     * @group regressao
     */
    public function testListaServicoPorIdRetornaArray(): void
    {
        $servico = $this->servicoRepo->listaServicoPorId(self::TENANT_ID, $this->fixtures['servico_diaria_id']);

        $this->assertIsArray($servico);
        $this->assertSame('Internação / Diária', $servico['nome']);
        $this->assertSame(80.0, (float) $servico['valor']);
    }

    public function testListaServicoInexistenteRetornaNull(): void
    {
        $this->assertNull($this->servicoRepo->listaServicoPorId(self::TENANT_ID, 987654));
    }

    /**
     * O id era concatenado direto na query. Agora vai por bind e passa por
     * cast: o payload malicioso vira apenas o inteiro 1, nenhum SQL extra é
     * executado e a tabela continua de pé.
     *
     * @group seguranca
     */
    public function testListaServicoPorIdEhImuneAInjecao(): void
    {
        $resultado = $this->servicoRepo->listaServicoPorId(
            self::TENANT_ID,
            "1 OR 1=1; DROP TABLE servico"
        );

        // A tabela sobreviveu e continua com os dois serviços semeados
        $this->assertSame(
            2,
            (int) $this->conn()->fetchOne('SELECT COUNT(*) FROM ' . $this->tabela('servico')),
            'O payload executou SQL extra.'
        );

        // No máximo UMA linha volta — a do id 1, resultado do cast
        if ($resultado !== null) {
            $this->assertSame(1, (int) $resultado['id'], 'A cláusula OR não pode ter ampliado o filtro.');
        }

        $this->addToAssertionCount(1);
    }

    public function testServicoDeOutroEstabelecimentoNaoEhVisivel(): void
    {
        $this->assertNull(
            $this->servicoRepo->listaServicoPorId(self::TENANT_VIZINHO_ID, $this->fixtures['servico_diaria_id'])
        );
    }
}
