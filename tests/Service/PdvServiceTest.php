<?php

namespace App\Tests\Service;

use App\Service\TenantContext;
use App\Service\EstoqueService;
use App\Service\PdvService;
use App\DTO\RegistrarVendaDTO;
use App\Entity\Produto;
use App\Entity\Cliente;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Psr\Log\LoggerInterface;

/**
 * Exemplos de testes unitários para os services do PDV
 * 
 * Para rodar:
 * php bin/phpunit tests/Service/PdvServiceTest.php
 */

class TenantContextTest extends TestCase
{
    public function testGetEstabelecimentoIdFromSession()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('estabelecimento_id', 123);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $tenantContext = new TenantContext($requestStack);

        $this->assertEquals(123, $tenantContext->getEstabelecimentoId());
    }

    public function testIsSuperAdmin()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('estabelecimento_id', 123);
        $session->set('user_status', 'Super Admin');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $tenantContext = new TenantContext($requestStack);

        $this->assertTrue($tenantContext->isSuperAdmin());
        $this->assertFalse($tenantContext->shouldFilterByEstabelecimento());
    }

    public function testIsAdminRegular()
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('estabelecimento_id', 123);
        $session->set('user_status', 'Admin');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $tenantContext = new TenantContext($requestStack);

        $this->assertFalse($tenantContext->isSuperAdmin());
        $this->assertTrue($tenantContext->shouldFilterByEstabelecimento());
    }
}

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

/**
 * INSTRUÇÕES PARA RODAR OS TESTES:
 * 
 * 1. Instale o PHPUnit (se ainda não tiver):
 *    composer require --dev phpunit/phpunit
 * 
 * 2. Configure o phpunit.xml.dist na raiz do projeto:
 *    <?xml version="1.0" encoding="UTF-8"?>
 *    <phpunit bootstrap="tests/bootstrap.php">
 *        <testsuites>
 *            <testsuite name="Project Test Suite">
 *                <directory>tests</directory>
 *            </testsuite>
 *        </testsuites>
 *    </phpunit>
 * 
 * 3. Execute os testes:
 *    php bin/phpunit
 * 
 * 4. Para um teste específico:
 *    php bin/phpunit tests/Service/PdvServiceTest.php
 * 
 * 5. Para ver cobertura de código:
 *    php bin/phpunit --coverage-html coverage/
 */

/**
 * EXEMPLO DE TESTE DE INTEGRAÇÃO (com banco de dados):
 * 
 * class PdvServiceIntegrationTest extends KernelTestCase
 * {
 *     private $pdvService;
 *     private $em;
 * 
 *     protected function setUp(): void
 *     {
 *         self::bootKernel();
 *         $container = self::$container;
 * 
 *         $this->pdvService = $container->get(PdvService::class);
 *         $this->em = $container->get('doctrine.orm.entity_manager');
 *     }
 * 
 *     public function testRegistrarVendaCompleta()
 *     {
 *         // Arrange
 *         $dto = RegistrarVendaDTO::fromArray([...]);
 * 
 *         // Act
 *         $resultado = $this->pdvService->registrarVenda($dto);
 * 
 *         // Assert
 *         $this->assertTrue($resultado['ok']);
 *         $this->assertArrayHasKey('venda_id', $resultado);
 * 
 *         // Verifica se foi realmente salvo no banco
 *         $venda = $this->em->getRepository(Venda::class)->find($resultado['venda_id']);
 *         $this->assertNotNull($venda);
 *     }
 * }
 */
