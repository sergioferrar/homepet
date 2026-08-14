<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="orcamento_item")
 */
class OrcamentoItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Orcamento::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $orcamento;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $descricao;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $tipo; // servico, produto, banho_tosa, clinica

    /**
     * @ORM\Column(type="integer")
     */
    private $quantidade;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valorUnitario;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $subtotal;

    // Getters e Setters

    public function getId(): ?int { return $this->id; }

    public function getOrcamento(): ?Orcamento { return $this->orcamento; }
    public function setOrcamento(Orcamento $orcamento): self { $this->orcamento = $orcamento; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(string $descricao): self { $this->descricao = $descricao; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getQuantidade(): ?int { return $this->quantidade; }
    public function setQuantidade(int $qtd): self { $this->quantidade = $qtd; return $this; }

    public function getValorUnitario(): ?float { return (float)$this->valorUnitario; }
    public function setValorUnitario(float $valor): self { $this->valorUnitario = $valor; return $this; }

    public function getSubtotal(): ?float { return (float)$this->subtotal; }
    public function setSubtotal(float $subtotal): self { $this->subtotal = $subtotal; return $this; }
}
