<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\EstoqueMovimentoRepository;

/**
 * @ORM\Entity(repositoryClass=EstoqueMovimentoRepository::class)
 * @ORM\Table(name="estoque_movimento")
 */
class EstoqueMovimento
{
    /** 
     * @ORM\Id 
     * @ORM\GeneratedValue 
     * @ORM\Column(type="integer") 
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Produto::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $produto;

    /** @ORM\Column(type="integer", name="estabelecimento_id") */
    private $estabelecimentoId;

    /** @ORM\Column(type="integer") */
    private $quantidade;

    /** 
     * @ORM\Column(type="string", length=20)
     * tipo: 'ENTRADA' | 'SAIDA' | 'AJUSTE'
     */
    private $tipo;

    /** @ORM\Column(type="string", length=100, nullable=true) */
    private $origem;

    /** @ORM\Column(type="datetime") */
    private $data;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacao;

    // --- Getters e Setters ---

    public function getId(): ?int { return $this->id; }

    public function getProduto(): ?Produto { return $this->produto; }
    public function setProduto(Produto $produto): self { $this->produto = $produto; return $this; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getQuantidade(): ?int { return $this->quantidade; }
    public function setQuantidade(int $qtd): self { $this->quantidade = $qtd; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = strtoupper($tipo); return $this; }

    public function getOrigem(): ?string { return $this->origem; }
    public function setOrigem(?string $origem): self { $this->origem = $origem; return $this; }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self { $this->data = $data; return $this; }

    public function getObservacao(): ?string { return $this->observacao; }
    public function setObservacao(?string $obs): self { $this->observacao = $obs; return $this; }
}
