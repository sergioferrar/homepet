<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Venda;
use App\Entity\VendaItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Venda
 * @covers \App\Entity\VendaItem
 */
class VendaTest extends TestCase
{
    // ── Venda ────────────────────────────────────────────────────────────────

    public function testVinculoComConsulta(): void
    {
        $venda = (new Venda())->setConsultaId(42);

        $this->assertSame(42, $venda->getConsultaId());
    }

    /** Venda avulsa (PDV, sem atendimento) precisa aceitar consulta nula. */
    public function testConsultaPodeSerNula(): void
    {
        $venda = (new Venda())->setConsultaId(null);

        $this->assertNull($venda->getConsultaId());
    }

    /** A coluna é DECIMAL — o Doctrine devolve string, o getter tem que dar float. */
    public function testTotalEhRetornadoComoFloat(): void
    {
        $venda = (new Venda())->setTotal(285.00);

        $this->assertIsFloat($venda->getTotal());
        $this->assertSame(285.00, $venda->getTotal());
    }

    public function testSettersEncadeiam(): void
    {
        $venda = (new Venda())
            ->setEstabelecimentoId(12)
            ->setCliente('Maria')
            ->setPetId(5)
            ->setConsultaId(7)
            ->setStatus('Pendente')
            ->setOrigem('clinica')
            ->setMetodoPagamento('pix')
            ->setTotal(100.0);

        $this->assertInstanceOf(Venda::class, $venda);
        $this->assertSame(12, $venda->getEstabelecimentoId());
        $this->assertSame('Maria', $venda->getCliente());
        $this->assertSame(5, $venda->getPetId());
        $this->assertSame(7, $venda->getConsultaId());
        $this->assertSame('Pendente', $venda->getStatus());
        $this->assertSame('clinica', $venda->getOrigem());
        $this->assertSame('pix', $venda->getMetodoPagamento());
    }

    public function testDataAceitaDateTime(): void
    {
        $data  = new \DateTime('2026-07-23 14:30:00');
        $venda = (new Venda())->setData($data);

        $this->assertSame('2026-07-23 14:30', $venda->getData()->format('Y-m-d H:i'));
    }

    // ── VendaItem ────────────────────────────────────────────────────────────

    public function testItemGuardaQuantidadeEValorPropriosDaLinha(): void
    {
        $item = (new VendaItem())
            ->setVendaId(1)
            ->setProdutoId(3)
            ->setProdutoNome('Internação / Diária')
            ->setQuantidade(3)
            ->setPrecoUnitario(80.00)
            ->setSubtotal(240.00)
            ->setTipo('servico');

        $this->assertSame(3, $item->getQuantidade());
        $this->assertSame(80.00, $item->getPrecoUnitario());
        $this->assertSame(240.00, $item->getSubtotal());
        $this->assertSame('Internação / Diária', $item->getProduto());
    }

    /** O tipo é ENUM minúsculo no banco — o setter normaliza. */
    public function testTipoEhNormalizadoParaMinusculo(): void
    {
        $this->assertSame('produto', (new VendaItem())->setTipo('PRODUTO')->getTipo());
        $this->assertSame('servico', (new VendaItem())->setTipo('Servico')->getTipo());
    }

    /** setValorUnitario é o alias legado de setPrecoUnitario. */
    public function testAliasLegadoDeValorUnitario(): void
    {
        $item = (new VendaItem())->setValorUnitario(45.50);

        $this->assertSame(45.50, $item->getValorUnitario());
        $this->assertSame(45.50, $item->getPrecoUnitario());
    }

    public function testSetVendaAceitaIntOuObjeto(): void
    {
        $this->assertSame(9, (new VendaItem())->setVenda(9)->getVendaId());
    }

    /** Item avulso pode não ter cadastro vinculado. */
    public function testProdutoIdPodeSerNulo(): void
    {
        $this->assertNull((new VendaItem())->setProdutoId(null)->getProdutoId());
    }

    /**
     * Um item de 3 diárias e outro de 1 unidade coexistem sem interferência —
     * é o contrato que o bug de índice quebrava na hora de montar a venda.
     *
     * @group regressao
     */
    public function testItensDaMesmaVendaMantemQuantidadesIndependentes(): void
    {
        $produto = (new VendaItem())->setVendaId(1)->setQuantidade(1)->setPrecoUnitario(45.00)->setSubtotal(45.00);
        $diaria  = (new VendaItem())->setVendaId(1)->setQuantidade(3)->setPrecoUnitario(80.00)->setSubtotal(240.00);

        $this->assertSame(1, $produto->getQuantidade());
        $this->assertSame(3, $diaria->getQuantidade());
        $this->assertSame(285.00, $produto->getSubtotal() + $diaria->getSubtotal());
    }
}
