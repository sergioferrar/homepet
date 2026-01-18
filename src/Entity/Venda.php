<?php

namespace App\Entity;

use App\Repository\VendaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VendaRepository::class)
 * @ORM\Table(name="venda")
 */
class Venda
{
    /** 
     * @ORM\Id 
     * @ORM\GeneratedValue 
     * @ORM\Column(type="integer") 
     */
    private $id;

    /** @ORM\Column(type="integer", name="estabelecimento_id") */
    private $estabelecimentoId;

    /** @ORM\Column(type="string", length=255) */
    private $cliente;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $total;

    /** @ORM\Column(type="decimal", precision=10, scale=2, nullable=true) */
    private $troco;

    /** @ORM\Column(type="string", length=50, name="metodo_pagamento") */
    private $metodoPagamento;

    /** @ORM\Column(type="string", length=50, nullable=true, name="bandeira_cartao") */
    private $bandeiraCartao;

    /** @ORM\Column(type="integer", nullable=true) */
    private $parcelas;

    /** @ORM\Column(type="datetime") */
    private $data;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacao;

    /** @ORM\Column(type="integer", nullable=true, name="pet_id") */
    private $petId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $origem;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    // --- Getters e Setters ---

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getCliente(): ?string { return $this->cliente; }
    public function setCliente(string $cliente): self { $this->cliente = $cliente; return $this; }

    public function getTotal(): ?float { return (float)$this->total; }
    public function setTotal(float $total): self { $this->total = $total; return $this; }

    public function getTroco(): ?float { return (float)$this->troco; }
    public function setTroco(?float $troco): self { $this->troco = $troco; return $this; }

    public function getMetodoPagamento(): ?string { return $this->metodoPagamento; }
    public function setMetodoPagamento(string $metodo): self { $this->metodoPagamento = $metodo; return $this; }

    public function getBandeiraCartao(): ?string { return $this->bandeiraCartao; }
    public function setBandeiraCartao(?string $bandeira): self { $this->bandeiraCartao = $bandeira; return $this; }

    public function getParcelas(): ?int { return $this->parcelas; }
    public function setParcelas(?int $parcelas): self { $this->parcelas = $parcelas; return $this; }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self { $this->data = $data; return $this; }

    public function getObservacao(): ?string { return $this->observacao; }
    public function setObservacao(?string $obs): self { $this->observacao = $obs; return $this; }

    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(?int $petId): self { $this->petId = $petId; return $this; }

    public function getOrigem(): ?string
    {
        return $this->origem;
    }

    public function setOrigem(string $origem): self
    {
        $this->origem = $origem;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
