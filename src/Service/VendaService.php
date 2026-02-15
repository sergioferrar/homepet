<?php

namespace App\Service;


use App\Entity\Venda;
use App\Entity\Produto;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 
 */
class VendaService
{
	
	private EntityManagerInterface $em;
    private VendaCalculoService $calculoService;

    const VENDA_ABERTA = 'Aberta';
    const VENDA_FINALIZADA = 'Finalizada';
    const VENDA_PENDENTE = 'Pendente';
    const VENDA_CANCELADA = 'Cancelada';
    const VENDA_FIADO = 'Fiado';

    public function __construct(
        EntityManagerInterface $em,
        VendaCalculoService $calculoService
    ) {
        $this->em = $em;
        $this->calculoService = $calculoService;
    }

    public function iniciarVenda(int $usuarioId, int $caixaId): Venda
    {
        $venda = new Venda();
        $venda->setUsuarioId($usuarioId);
        $venda->setCaixaId($caixaId);
        $venda->setStatus(Venda::STATUS_ABERTA);
        $venda->setCriadoEm(new \DateTimeImmutable());

        $this->em->persist($venda);
        $this->em->flush();

        return $venda;
    }

    public function adicionarProduto(Venda $venda, Produto $produto, int $quantidade): void
    {
        if (!$venda->isAberta()) {
            throw new \DomainException('Não é possível alterar uma venda finalizada.');
        }

        $venda->adicionarItem($produto, $quantidade);

        $this->calculoService->recalcularTotais($venda);

        $this->em->flush();
    }

    public function recalcularTotais(Venda $venda): void
    {
        $subtotal = 0;

        foreach ($venda->getItens() as $item) {
            $subtotal += $item->getSubtotal();
        }

        $venda->setSubtotal($subtotal);
        $venda->setDesconto(0);
        $venda->setTotal($subtotal);
    }

    public function finalizar(Venda $venda): void
    {
        if (!$venda->isAberta()) {
            throw new \DomainException('Venda não está aberta.');
        }

        if ($venda->getTotal() <= 0) {
            throw new \DomainException('Venda não possui valor válido.');
        }

        $venda->setStatus(Venda::STATUS_FINALIZADA);
        $venda->setFinalizadaEm(new \DateTimeImmutable());

        $this->em->flush();
    }

    public function cancelar(Venda $venda, string $motivo): void
    {
        if ($venda->isFinalizada()) {
            throw new \DomainException('Venda finalizada não pode ser cancelada diretamente.');
        }

        $venda->setStatus(Venda::STATUS_CANCELADA);
        $venda->setMotivoCancelamento($motivo);
        $venda->setCanceladaEm(new \DateTimeImmutable());

        $this->em->flush();
    }
}