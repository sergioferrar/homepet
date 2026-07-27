<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Venda;
use App\Repository\VendaRepository;
use App\Tests\Integration\TenantIntegrationTestCase;

/**
 * @covers \App\Repository\VendaRepository
 * @group integracao
 */
class VendaRepositoryTest extends TenantIntegrationTestCase
{
    private VendaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = static::getContainer()->get('doctrine')->getRepository(Venda::class);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Vínculo com o atendimento
    // ═════════════════════════════════════════════════════════════════════════

    public function testInserirVendaGravaConsultaId(): void
    {
        $vendaId = $this->repo->inserirVenda(self::TENANT_ID, [
            'estabelecimento_id' => self::TENANT_ID,
            'cliente'            => 'João da Silva',
            'pet_id'             => $this->fixtures['pet_id'],
            'consulta_id'        => $this->fixtures['consulta_a_id'],
            'parcelas'           => 1,
            'origem'             => 'clinica',
            'metodo_pagamento'   => 'pix',
            'status'             => 'Aberta',
            'observacao'         => null,
        ]);

        $venda = $this->repo->buscarPorId(self::TENANT_ID, $vendaId);

        $this->assertSame($this->fixtures['consulta_a_id'], (int) $venda['consulta_id']);
        $this->assertSame(0.0, (float) $venda['total'], 'Venda nasce zerada; o total é fechado depois.');
    }

    public function testInserirVendaAceitaConsultaNula(): void
    {
        $vendaId = $this->repo->inserirVenda(self::TENANT_ID, [
            'estabelecimento_id' => self::TENANT_ID,
            'cliente'            => 'Consumidor Final',
            'pet_id'             => $this->fixtures['pet_id'],
            'consulta_id'        => null,
            'parcelas'           => 1,
            'origem'             => 'clinica',
            'metodo_pagamento'   => 'dinheiro',
            'status'             => 'Pendente',
            'observacao'         => 'avulsa',
        ]);

        $this->assertNull($this->repo->buscarPorId(self::TENANT_ID, $vendaId)['consulta_id']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Agrupamento por atendimento
    // ═════════════════════════════════════════════════════════════════════════

    public function testAgrupaVendasPorConsulta(): void
    {
        // Consulta A: 2 vendas | Consulta B: 1 venda | avulsa: 1 venda
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', [
            ['ref' => 'S-' . $this->fixtures['servico_consulta_id'], 'qtd' => 1, 'unit' => 150.00, 'nome' => 'Consulta Clínica'],
        ]);
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Pendente', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 1, 'unit' => 45.00, 'nome' => 'Antipulgas 10kg'],
        ]);
        $this->criarVenda($this->fixtures['consulta_b_id'], 'Paga', [
            ['ref' => 'S-' . $this->fixtures['servico_diaria_id'], 'qtd' => 3, 'unit' => 80.00, 'nome' => 'Internação / Diária'],
        ]);
        $this->criarVenda(null, 'Paga', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 2, 'unit' => 45.00, 'nome' => 'Antipulgas 10kg'],
        ]);

        $grupos = $this->repo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']);

        $this->assertCount(3, $grupos, 'Duas consultas + o grupo dos avulsos.');

        $consultaA = $grupos[$this->fixtures['consulta_a_id']];
        $this->assertSame(2, $consultaA['qtd_vendas']);
        $this->assertSame(195.00, $consultaA['total'], '150 + 45');
        $this->assertSame('Consulta', $consultaA['tipo']);
        $this->assertSame('Dra. Ana Prado', $consultaA['veterinario']);

        $consultaB = $grupos[$this->fixtures['consulta_b_id']];
        $this->assertSame(1, $consultaB['qtd_vendas']);
        $this->assertSame(240.00, $consultaB['total'], '3 diárias × 80');
        $this->assertSame('Retorno', $consultaB['tipo']);
    }

    /** Vendas sem consulta caem no grupo de chave 0 ("Sem atendimento"). */
    public function testVendasSemConsultaVaoParaOGrupoZero(): void
    {
        $this->criarVenda(null, 'Paga', [
            ['ref' => 'P-' . $this->fixtures['produto_id'], 'qtd' => 1, 'unit' => 45.00],
        ]);

        $grupos = $this->repo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']);

        $this->assertArrayHasKey(0, $grupos);
        $this->assertNull($grupos[0]['consulta_id']);
        $this->assertSame(45.00, $grupos[0]['total']);
    }

    /** O grupo dos avulsos vem por último; os atendimentos, do mais recente ao mais antigo. */
    public function testOrdemDosGrupos(): void
    {
        $this->criarVenda(null, 'Paga', [['ref' => 'P-1', 'qtd' => 1, 'unit' => 10.00]]);
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', [['ref' => 'S-1', 'qtd' => 1, 'unit' => 10.00]]);
        $this->criarVenda($this->fixtures['consulta_b_id'], 'Paga', [['ref' => 'S-1', 'qtd' => 1, 'unit' => 10.00]]);

        $chaves = array_keys($this->repo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']));

        $this->assertSame(
            [$this->fixtures['consulta_b_id'], $this->fixtures['consulta_a_id'], 0],
            $chaves,
            'Consulta de 22/07 antes da de 20/07, e os avulsos por último.'
        );
    }

    public function testVendasCanceladasFicamDeForaDoAgrupamento(): void
    {
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', [['ref' => 'S-1', 'qtd' => 1, 'unit' => 50.00]]);
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Cancelada', [['ref' => 'S-1', 'qtd' => 1, 'unit' => 999.00]]);

        $grupos = $this->repo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, $this->fixtures['pet_id']);

        $this->assertSame(1, $grupos[$this->fixtures['consulta_a_id']]['qtd_vendas']);
        $this->assertSame(50.00, $grupos[$this->fixtures['consulta_a_id']]['total']);
    }

    public function testPetSemVendasRetornaAgrupamentoVazio(): void
    {
        $this->assertSame([], $this->repo->listarPorPetAgrupadoPorConsulta(self::TENANT_ID, 987654));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // findByPet / vendaPorStatus
    // ═════════════════════════════════════════════════════════════════════════

    public function testFindByPetFiltraPorStatus(): void
    {
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', []);
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Pendente', []);

        $pagas = $this->repo->findByPet(self::TENANT_ID, $this->fixtures['pet_id'], 'Paga');

        $this->assertCount(1, $pagas);
        $this->assertSame('Paga', $pagas[0]['status']);
        $this->assertArrayHasKey('consulta_data', $pagas[0], 'O JOIN com consulta precisa vir junto.');
    }

    /**
     * O status ia concatenado direto no SQL. Uma aspa quebrava a query
     * (ou pior). Agora é bind: entrada maliciosa simplesmente não casa.
     *
     * @group seguranca
     */
    public function testStatusMaliciosoNaoQuebraAConsulta(): void
    {
        $this->criarVenda($this->fixtures['consulta_a_id'], 'Paga', []);

        $resultado = $this->repo->vendaPorStatus(
            self::TENANT_ID,
            $this->fixtures['pet_id'],
            "Paga' OR '1'='1"
        );

        $this->assertSame([], $resultado, 'Injeção não pode retornar linha nenhuma.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Regressões de escrita
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * O ENUM de venda.status não tinha 'Cancelada'; em sql_mode STRICT o
     * UPDATE era rejeitado sem o erro chegar à tela.
     *
     * @group regressao
     */
    public function testInativarGravaStatusCancelada(): void
    {
        $vendaId = $this->criarVenda($this->fixtures['consulta_a_id'], 'Pendente', []);

        $this->assertTrue($this->repo->inativar(self::TENANT_ID, $vendaId));
        $this->assertSame('Cancelada', $this->statusDaVenda($vendaId));
    }

    public function testInativarNaoAfetaVendaDeOutroEstabelecimento(): void
    {
        $vendaVizinha = $this->criarVenda(null, 'Pendente', [], self::TENANT_VIZINHO_ID, 1);

        $this->repo->inativar(self::TENANT_ID, $vendaVizinha);

        $this->assertSame('Pendente', $this->statusDaVenda($vendaVizinha, self::TENANT_VIZINHO_ID));
    }

    /**
     * O WHERE era `... AND status = 'Aberta' OR status = 'Pendente'`. Como AND
     * tem precedência sobre OR, a condição virava
     * `(id AND estab AND 'Aberta') OR ('Pendente')` e o UPDATE varria TODAS as
     * vendas pendentes da tabela — de qualquer estabelecimento.
     *
     * @group regressao
     */
    public function testFinalizarVendaNaoVazaParaOutrasVendasPendentes(): void
    {
        $alvo      = $this->criarVenda($this->fixtures['consulta_a_id'], 'Aberta', []);
        $intocada1 = $this->criarVenda($this->fixtures['consulta_a_id'], 'Pendente', []);
        $intocada2 = $this->criarVenda($this->fixtures['consulta_b_id'], 'Pendente', []);

        $this->repo->finalizarVenda(self::TENANT_ID, $alvo, 'pix', 'Paga');

        $this->assertSame('Paga', $this->statusDaVenda($alvo));
        $this->assertSame('Pendente', $this->statusDaVenda($intocada1), 'Venda pendente alheia foi alterada.');
        $this->assertSame('Pendente', $this->statusDaVenda($intocada2), 'Venda pendente alheia foi alterada.');
    }

    public function testAtualizarTotalGravaOValor(): void
    {
        $vendaId = $this->criarVenda($this->fixtures['consulta_a_id'], 'Aberta', []);

        $this->repo->atualizarTotal(self::TENANT_ID, $vendaId, 285.00);

        $this->assertSame(285.00, (float) $this->repo->buscarPorId(self::TENANT_ID, $vendaId)['total']);
    }

    public function testBuscarPorIdDeOutroEstabelecimentoRetornaNull(): void
    {
        $vendaVizinha = $this->criarVenda(null, 'Paga', [], self::TENANT_VIZINHO_ID, 1);

        $this->assertNull($this->repo->buscarPorId(self::TENANT_ID, $vendaVizinha));
    }
}
