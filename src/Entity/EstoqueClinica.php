<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="estoque_clinica")
 */
class EstoqueClinica
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="string", length=255) */
    private $produto;

    /** @ORM\Column(type="string", length=50) */
    private $tipo;

    /** @ORM\Column(type="integer") */
    private $quantidade;

    /** @ORM\Column(type="date") */
    private $validade;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    public function getId(): ?int { return $this->id; }
    public function getProduto(): string { return $this->produto; }
    public function setProduto(string $produto): self { $this->produto = $produto; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }
    public function getQuantidade(): int { return $this->quantidade; }
    public function setQuantidade(int $quantidade): self { $this->quantidade = $quantidade; return $this; }
    public function getValidade(): \DateTimeInterface { return $this->validade; }
    public function setValidade(\DateTimeInterface $validade): self { $this->validade = $validade; return $this; }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;

        return $this;
    }
}
