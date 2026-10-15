<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClienteCreditoMovimentoRepository;

/**
 * Movimento de crédito do cliente (entrada ou consumo).
 * O saldo é sempre calculado via SOMA no repositório.
 *
 * @ORM\Entity(repositoryClass=ClienteCreditoMovimentoRepository::class)
 */
class ClienteCreditoMovimento
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="integer", name="estabelecimento_id") */
    private $estabelecimentoId;

    /** @ORM\Column(type="integer", name="cliente_id") */
    private $clienteId;

    /** @ORM\Column(type="integer", nullable=true, name="pet_id") */
    private $petId;

    /** @ORM\Column(type="string", length=10) */
    private $tipo;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $valor;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $descricao;

    /** @ORM\Column(type="string", length=60, nullable=true) */
    private $origem;

    /** @ORM\Column(type="integer", nullable=true, name="origem_id") */
    private $origemId;

    /** @ORM\Column(type="datetime", name="criado_em") */
    private $criadoEm;

    /** @ORM\Column(type="integer", nullable=true, name="criado_por") */
    private $criadoPor;

    public function getId(): ?int
    {return $this->id;}

    public function getEstabelecimentoId(): ?int
    {return $this->estabelecimentoId;}
    public function setEstabelecimentoId(int $id): self
    {$this->estabelecimentoId = $id;return $this;}

    public function getClienteId(): ?int
    {return $this->clienteId;}
    public function setClienteId(int $id): self
    {$this->clienteId = $id;return $this;}

    public function getPetId(): ?int
    {return $this->petId;}
    public function setPetId(?int $id): self
    {$this->petId = $id;return $this;}

    public function getTipo(): ?string
    {return $this->tipo;}
    public function setTipo(string $tipo): self
    {$this->tipo = $tipo;return $this;}

    public function getValor(): float
    {return (float) $this->valor;}
    public function setValor(float $valor): self
    {$this->valor = $valor;return $this;}

    public function getDescricao(): ?string
    {return $this->descricao;}
    public function setDescricao(?string $d): self
    {$this->descricao = $d;return $this;}

    public function getOrigem(): ?string
    {return $this->origem;}
    public function setOrigem(?string $o): self
    {$this->origem = $o;return $this;}

    public function getOrigemId(): ?int
    {return $this->origemId;}
    public function setOrigemId(?int $o): self
    {$this->origemId = $o;return $this;}

    public function getCriadoEm(): ?\DateTimeInterface
    {return $this->criadoEm;}
    public function setCriadoEm(\DateTimeInterface $d) : self
    {$this->criadoEm = $d;return $this;}

    public function getCriadoPor(): ?int
    {return $this->criadoPor;}
    public function setCriadoPor(?int $u): self
    {$this->criadoPor = $u;return $this;}
}
