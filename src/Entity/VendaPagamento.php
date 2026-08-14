<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Uma forma de pagamento de uma venda.
 *
 * Uma venda pode ter uma ou mais formas (pagamento dividido). Ex.: uma venda de
 * R$ 300,00 paga com R$ 200,00 no cartão e R$ 100,00 no PIX gera dois registros.
 *
 * @ORM\Entity
 * @ORM\Table(name="venda_pagamento")
 */
class VendaPagamento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="integer", name="estabelecimento_id") */
    private $estabelecimentoId;

    /** @ORM\Column(type="integer", name="venda_id") */
    private $vendaId;

    /** @ORM\Column(type="string", length=20) */
    private $metodo;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $valor;

    /** @ORM\Column(type="string", length=50, nullable=true, name="bandeira_cartao") */
    private $bandeiraCartao;

    /** @ORM\Column(type="integer", nullable=true) */
    private $parcelas;

    /** @ORM\Column(type="datetime") */
    private $data;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getVendaId(): ?int
    {
        return $this->vendaId;
    }

    public function setVendaId(int $vendaId): self
    {
        $this->vendaId = $vendaId;
        return $this;
    }

    public function getMetodo(): ?string
    {
        return $this->metodo;
    }

    public function setMetodo(string $metodo): self
    {
        $this->metodo = $metodo;
        return $this;
    }

    public function getValor(): float
    {
        return (float) $this->valor;
    }

    public function setValor(float $valor): self
    {
        $this->valor = $valor;
        return $this;
    }

    public function getBandeiraCartao(): ?string
    {
        return $this->bandeiraCartao;
    }

    public function setBandeiraCartao(?string $bandeiraCartao): self
    {
        $this->bandeiraCartao = $bandeiraCartao;
        return $this;
    }

    public function getParcelas(): ?int
    {
        return $this->parcelas;
    }

    public function setParcelas(?int $parcelas): self
    {
        $this->parcelas = $parcelas;
        return $this;
    }

    public function getData(): ?\DateTimeInterface
    {
        return $this->data;
    }

    public function setData(\DateTimeInterface $data): self
    {
        $this->data = $data;
        return $this;
    }
}
