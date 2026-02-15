<?php

namespace App\Tests\Service;

use App\DTO\RegistrarVendaDTO;
use PHPUnit\Framework\TestCase;

class RegistrarVendaDTOTest extends TestCase
{
    public function testValidacaoComDadosValidos()
    {
        $dto = RegistrarVendaDTO::fromArray([
            'itens' => [
                ['id' => 'prod_1', 'nome' => 'Ração', 'quantidade' => 2, 'valor' => 50, 'tipo' => 'Produto']
            ],
            'total' => 100.0,
            'metodo' => 'dinheiro',
            'origem' => 'PDV'
        ]);

        $errors = $dto->validate();

        $this->assertEmpty($errors);
        $this->assertEquals(100.0, $dto->total);
        $this->assertEquals('dinheiro', $dto->metodo);
    }

    public function testValidacaoSemItens()
    {
        $dto = RegistrarVendaDTO::fromArray([
            'itens' => [],
            'total' => 100.0,
            'metodo' => 'dinheiro',
            'origem' => 'PDV'
        ]);

        $errors = $dto->validate();

        $this->assertNotEmpty($errors);
        $this->assertContains('Nenhum item informado.', $errors);
    }

    public function testValidacaoTotalInvalido()
    {
        $dto = RegistrarVendaDTO::fromArray([
            'itens' => [
                ['id' => 'prod_1', 'nome' => 'Ração', 'quantidade' => 2, 'valor' => 50, 'tipo' => 'Produto']
            ],
            'total' => 0,
            'metodo' => 'dinheiro',
            'origem' => 'PDV'
        ]);

        $errors = $dto->validate();

        $this->assertNotEmpty($errors);
        $this->assertContains('Valor total inválido.', $errors);
    }

    public function testValidacaoMetodoPagamentoVazio()
    {
        $dto = RegistrarVendaDTO::fromArray([
            'itens' => [
                ['id' => 'prod_1', 'nome' => 'Ração', 'quantidade' => 2, 'valor' => 50, 'tipo' => 'Produto']
            ],
            'total' => 100.0,
            'metodo' => '',
            'origem' => 'PDV'
        ]);

        $errors = $dto->validate();

        $this->assertNotEmpty($errors);
        $this->assertContains('Método de pagamento não informado.', $errors);
    }
}
