<?php

namespace App\Tests\Service;

use App\Service\TenantContext;
use App\Service\EstoqueService;
use App\Entity\Produto;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EstoqueServiceTest extends TestCase
{
    private $em;
    private $tenantContext;
    private $estoqueService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->tenantContext->method('getEstabelecimentoId')->willReturn(123);

        $this->estoqueService = new EstoqueService($this->em, $this->tenantContext);
    }

    public function testValidarEstoqueSuficiente()
    {
        $produto = new Produto();
        $produto->setNome('Ração Premium');
        $produto->setEstoqueAtual(10);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($produto);

        $this->em->method('getRepository')->willReturn($repository);

        $itens = [
            [
                'id' => 'prod_1',
                'tipo' => 'Produto',
                'nome' => 'Ração Premium',
                'quantidade' => 5
            ]
        ];

        $resultado = $this->estoqueService->validarEstoque($itens);

        $this->assertIsArray($resultado);
        $this->assertCount(1, $resultado);
    }

    public function testValidarEstoqueInsuficiente()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Estoque insuficiente');

        $produto = new Produto();
        $produto->setNome('Ração Premium');
        $produto->setEstoqueAtual(2);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($produto);

        $this->em->method('getRepository')->willReturn($repository);

        $itens = [
            [
                'id' => 'prod_1',
                'tipo' => 'Produto',
                'nome' => 'Ração Premium',
                'quantidade' => 5
            ]
        ];

        $this->estoqueService->validarEstoque($itens);
    }

    public function testValidarEstoqueProdutoNaoEncontrado()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não encontrado');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturn($repository);

        $itens = [
            [
                'id' => 'prod_999',
                'tipo' => 'Produto',
                'nome' => 'Produto Inexistente',
                'quantidade' => 1
            ]
        ];

        $this->estoqueService->validarEstoque($itens);
    }
}
