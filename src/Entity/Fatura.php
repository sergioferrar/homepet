<?php

namespace App\Entity;

use App\Repository\FaturaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FaturaRepository::class)
 * @ORM\Table(name="invoice")
 */
class Fatura
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="estabelecimento_id")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(type="string", length=50, name="numero_invoice")
     */
    private $numeroInvoice;

    /**
     * @ORM\Column(type="string", length=30)
     */
    private $tipo; // 'assinatura', 'renovacao'

    /**
     * @ORM\Column(type="string", length=30)
     */
    private $status; // 'pendente', 'pago', 'cancelado', 'vencido'

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, name="valor_total")
     */
    private $valorTotal;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true, name="valor_desconto")
     */
    private $valorDesconto;

    /**
     * @ORM\Column(type="integer", name="plano_id")
     */
    private $planoId;

    /**
     * @ORM\Column(type="datetime", name="data_emissao")
     */
    private $dataEmissao;

    /**
     * @ORM\Column(type="datetime", name="data_vencimento")
     */
    private $dataVencimento;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="data_pagamento")
     */
    private $dataPagamento;

    /**
     * @ORM\Column(type="string", length=100, nullable=true, name="payment_gateway")
     */
    private $paymentGateway;

    /**
     * @ORM\Column(type="string", length=255, nullable=true, name="payment_id")
     */
    private $paymentId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true, name="subscription_id")
     */
    private $subscriptionId;

    /**
     * @ORM\Column(type="text", nullable=true, name="payment_data")
     */
    private $paymentData;

    /**
     * @ORM\Column(type="text", nullable=true, name="observacoes")
     */
    private $observacoes;

    /**
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="updated_at")
     */
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->dataEmissao = new \DateTime();
        $this->status = 'pendente';
    }

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

    public function getNumeroInvoice(): ?string
    {
        return $this->numeroInvoice;
    }

    public function setNumeroInvoice(string $numeroInvoice): self
    {
        $this->numeroInvoice = $numeroInvoice;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getValorTotal(): ?string
    {
        return $this->valorTotal;
    }

    public function setValorTotal(string $valorTotal): self
    {
        $this->valorTotal = $valorTotal;
        return $this;
    }

    public function getValorDesconto(): ?string
    {
        return $this->valorDesconto;
    }

    public function setValorDesconto(?string $valorDesconto): self
    {
        $this->valorDesconto = $valorDesconto;
        return $this;
    }

    public function getPlanoId(): ?int
    {
        return $this->planoId;
    }

    public function setPlanoId(int $planoId): self
    {
        $this->planoId = $planoId;
        return $this;
    }

    public function getDataEmissao(): ?\DateTimeInterface
    {
        return $this->dataEmissao;
    }

    public function setDataEmissao(\DateTimeInterface $dataEmissao): self
    {
        $this->dataEmissao = $dataEmissao;
        return $this;
    }

    public function getDataVencimento(): ?\DateTimeInterface
    {
        return $this->dataVencimento;
    }

    public function setDataVencimento(\DateTimeInterface $dataVencimento): self
    {
        $this->dataVencimento = $dataVencimento;
        return $this;
    }

    public function getDataPagamento(): ?\DateTimeInterface
    {
        return $this->dataPagamento;
    }

    public function setDataPagamento(?\DateTimeInterface $dataPagamento): self
    {
        $this->dataPagamento = $dataPagamento;
        return $this;
    }

    public function getPaymentGateway(): ?string
    {
        return $this->paymentGateway;
    }

    public function setPaymentGateway(?string $paymentGateway): self
    {
        $this->paymentGateway = $paymentGateway;
        return $this;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function setPaymentId(?string $paymentId): self
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(?string $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;
        return $this;
    }

    public function getPaymentData(): ?string
    {
        return $this->paymentData;
    }

    public function setPaymentData(?string $paymentData): self
    {
        $this->paymentData = $paymentData;
        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(?string $observacoes): self
    {
        $this->observacoes = $observacoes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getValorLiquido(): float
    {
        $total = (float) $this->valorTotal;
        $desconto = (float) ($this->valorDesconto ?? 0);
        return $total - $desconto;
    }
}
