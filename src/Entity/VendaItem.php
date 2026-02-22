<?php

namespace App\Entity;

use App\Repository\VendaItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VendaItemRepository::class)
 * @ORM\Table(name="venda_item")
 */
class VendaItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="integer", name="venda_id") */
    private $vendaId;

    /** @ORM\Column(type="string", length=255) */
    private $produto;

    /** @ORM\Column(type="integer") */
    private $quantidade;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $valorUnitario;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $subtotal;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $tipo;

    // --- Getters e Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVendaId(): self
    {
        return $this->vendaId;
    }

    public function setVendaId($venda): self
    {
        $this->vendaId = $venda;
        return $this;
    }

    public function getProduto(): ?string
    {
        return $this->produto;
    }

    public function setProduto(string $produto): self
    {
        $this->produto = $produto;
        return $this;
    }

    public function getQuantidade(): ?int
    {
        return $this->quantidade;
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }

    public function getValorUnitario(): ?float
    {
        return (float)$this->valorUnitario;
    }

    public function setValorUnitario(float $valor): self
    {
        $this->valorUnitario = $valor;
        return $this;
    }

    public function getSubtotal(): ?float
    {
        return (float)$this->subtotal;
    }

    public function setSubtotal(float $subtotal): self
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }
}
