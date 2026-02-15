<?php

namespace App\Tests\Service;

use App\Service\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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
