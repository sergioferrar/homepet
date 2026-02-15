<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Serviço centralizado para gerenciar contexto de tenant (estabelecimento)
 * Garante isolamento 100% seguro entre estabelecimentos
 */
class TenantContext
{
    private RequestStack $requestStack;
    private ?int $estabelecimentoId = null;
    private bool $isSuperAdmin = false;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        $this->loadFromSession();
    }

    private function loadFromSession(): void
    {
        $session = $this->requestStack->getSession();
        if ($session) {
            $this->estabelecimentoId = (int)$session->get('estabelecimento_id');
            $this->isSuperAdmin = $session->get('user_status') === 'Super Admin';
        }
    }

    public function getEstabelecimentoId(): int
    {
        if ($this->estabelecimentoId === null) {
            throw new \RuntimeException('Estabelecimento não identificado na sessão');
        }
        return $this->estabelecimentoId;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * Para Super Admin: permite acesso global (não filtra por estabelecimento)
     * Para Admin: sempre filtra pelo estabelecimento do usuário
     */
    public function shouldFilterByEstabelecimento(): bool
    {
        return !$this->isSuperAdmin;
    }

    /**
     * Retorna o ID do estabelecimento ou null para Super Admin em modo global
     */
    public function getEstabelecimentoIdForQuery(): ?int
    {
        return $this->shouldFilterByEstabelecimento() ? $this->estabelecimentoId : null;
    }
}
