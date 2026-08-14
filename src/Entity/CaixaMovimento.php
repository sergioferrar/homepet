<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="caixa_movimento")
 */
class CaixaMovimento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $descricao;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valor;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $tipo = 'SAIDA'; // ou 'ENTRADA'

    /**
     * @ORM\Column(type="datetime")
     */
    private $data;

    // ===== Getters e Setters =====

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(string $descricao): self { $this->descricao = $descricao; return $this; }

    public function getValor(): ?float { return $this->valor; }
    public function setValor(float $valor): self { $this->valor = $valor; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self { $this->data = $data; return $this; }
}
