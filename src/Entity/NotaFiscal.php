<?php

namespace App\Entity;

use App\Repository\NotaFiscalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro local de cada NFS-e emitida via Asaas.
 *
 * @ORM\Entity(repositoryClass=NotaFiscalRepository::class)
 * @ORM\Table(name="nota_fiscal")
 */
class NotaFiscal
{
    // ── Status espelhados do Asaas ──────────────────────────────────
    public const STATUS_AGENDADA    = 'SCHEDULED';
    public const STATUS_SINCRONIZADA= 'SYNCHRONIZED';
    public const STATUS_AUTORIZADA  = 'AUTHORIZED';
    public const STATUS_CANCELADA   = 'CANCELED';
    public const STATUS_ERRO        = 'ERROR';
    public const STATUS_PENDENTE    = 'PENDING'; // aguardando envio

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * ID do estabelecimento emissor
     *
     * @ORM\Column(type="integer", name="estabelecimento_id")
     */
    private int $estabelecimentoId;

    /**
     * Tipo de origem: 'venda' | 'servico' | 'avulsa'
     *
     * @ORM\Column(type="string", length=20)
     */
    private string $origem = 'venda';

    /**
     * ID da venda que gerou esta nota (nullable para avulsas)
     *
     * @ORM\Column(type="integer", nullable=true, name="venda_id")
     */
    private ?int $vendaId = null;

    /**
     * ID do cliente local (tabela cliente)
     *
     * @ORM\Column(type="integer", nullable=true, name="cliente_id")
     */
    private ?int $clienteId = null;

    /**
     * Nome do cliente no momento da emissão (snapshot)
     *
     * @ORM\Column(type="string", length=255, name="cliente_nome")
     */
    private string $clienteNome;

    /**
     * CPF/CNPJ do tomador do serviço
     *
     * @ORM\Column(type="string", length=20, nullable=true, name="cliente_cpf_cnpj")
     */
    private ?string $clienteCpfCnpj = null;

    /**
     * ID do cliente no Asaas (cus_XXXXX) — sincronizado automaticamente
     *
     * @ORM\Column(type="string", length=100, nullable=true, name="asaas_customer_id")
     */
    private ?string $asaasCustomerId = null;

    /**
     * ID da nota fiscal no Asaas (inv_XXXXX)
     *
     * @ORM\Column(type="string", length=100, nullable=true, name="asaas_invoice_id")
     */
    private ?string $asaasInvoiceId = null;

    /**
     * Valor bruto da nota
     *
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private string $valor;

    /**
     * Deduções (descontos, etc.)
     *
     * @ORM\Column(type="decimal", precision=10, scale=2, options={"default":"0.00"})
     */
    private string $deducoes = '0.00';

    /**
     * Status da nota no Asaas
     *
     * @ORM\Column(type="string", length=30)
     */
    private string $status = self::STATUS_PENDENTE;

    /**
     * Descrição do serviço prestado
     *
     * @ORM\Column(type="text", name="descricao_servico")
     */
    private string $descricaoServico;

    /**
     * Data de emissão / emissão efetiva (effectiveDate no Asaas)
     *
     * @ORM\Column(type="date", name="data_emissao")
     */
    private \DateTimeInterface $dataEmissao;

    /**
     * Número da nota emitida pela prefeitura
     *
     * @ORM\Column(type="string", length=50, nullable=true, name="numero_nota")
     */
    private ?string $numeroNota = null;

    /**
     * Código RPS
     *
     * @ORM\Column(type="string", length=50, nullable=true, name="rps_numero")
     */
    private ?string $rpsNumero = null;

    /**
     * URL do PDF gerado pelo Asaas
     *
     * @ORM\Column(type="string", length=500, nullable=true, name="pdf_url")
     */
    private ?string $pdfUrl = null;

    /**
     * URL do XML da nota
     *
     * @ORM\Column(type="string", length=500, nullable=true, name="xml_url")
     */
    private ?string $xmlUrl = null;

    /**
     * ID do serviço municipal no Asaas
     *
     * @ORM\Column(type="string", length=100, nullable=true, name="municipal_service_id")
     */
    private ?string $municipalServiceId = null;

    /**
     * Código do serviço municipal (alternativo ao ID)
     *
     * @ORM\Column(type="string", length=50, nullable=true, name="municipal_service_code")
     */
    private ?string $municipalServiceCode = null;

    /**
     * Nome do serviço municipal
     *
     * @ORM\Column(type="string", length=255, nullable=true, name="municipal_service_name")
     */
    private ?string $municipalServiceName = null;

    /**
     * JSON com alíquotas de impostos { iss, cofins, csll, inss, ir, pis, retainIss }
     *
     * @ORM\Column(type="text", nullable=true, name="impostos_json")
     */
    private ?string $impostosJson = null;

    /**
     * Observações internas / mensagem de erro do Asaas
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $observacoes = null;

    /**
     * @ORM\Column(type="datetime", name="criado_em")
     */
    private \DateTimeInterface $criadoEm;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="atualizado_em")
     */
    private ?\DateTimeInterface $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm   = new \DateTime();
        $this->dataEmissao = new \DateTime();
    }

    // ── Getters & Setters ────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $v): self { $this->estabelecimentoId = $v; return $this; }

    public function getOrigem(): string { return $this->origem; }
    public function setOrigem(string $v): self { $this->origem = $v; return $this; }

    public function getVendaId(): ?int { return $this->vendaId; }
    public function setVendaId(?int $v): self { $this->vendaId = $v; return $this; }

    public function getClienteId(): ?int { return $this->clienteId; }
    public function setClienteId(?int $v): self { $this->clienteId = $v; return $this; }

    public function getClienteNome(): string { return $this->clienteNome; }
    public function setClienteNome(string $v): self { $this->clienteNome = $v; return $this; }

    public function getClienteCpfCnpj(): ?string { return $this->clienteCpfCnpj; }
    public function setClienteCpfCnpj(?string $v): self { $this->clienteCpfCnpj = $v; return $this; }

    public function getAsaasCustomerId(): ?string { return $this->asaasCustomerId; }
    public function setAsaasCustomerId(?string $v): self { $this->asaasCustomerId = $v; return $this; }

    public function getAsaasInvoiceId(): ?string { return $this->asaasInvoiceId; }
    public function setAsaasInvoiceId(?string $v): self { $this->asaasInvoiceId = $v; return $this; }

    public function getValor(): string { return $this->valor; }
    public function setValor(string $v): self { $this->valor = $v; return $this; }

    public function getDeducoes(): string { return $this->deducoes; }
    public function setDeducoes(string $v): self { $this->deducoes = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }

    public function getDescricaoServico(): string { return $this->descricaoServico; }
    public function setDescricaoServico(string $v): self { $this->descricaoServico = $v; return $this; }

    public function getDataEmissao(): \DateTimeInterface { return $this->dataEmissao; }
    public function setDataEmissao(\DateTimeInterface $v): self { $this->dataEmissao = $v; return $this; }

    public function getNumeroNota(): ?string { return $this->numeroNota; }
    public function setNumeroNota(?string $v): self { $this->numeroNota = $v; return $this; }

    public function getRpsNumero(): ?string { return $this->rpsNumero; }
    public function setRpsNumero(?string $v): self { $this->rpsNumero = $v; return $this; }

    public function getPdfUrl(): ?string { return $this->pdfUrl; }
    public function setPdfUrl(?string $v): self { $this->pdfUrl = $v; return $this; }

    public function getXmlUrl(): ?string { return $this->xmlUrl; }
    public function setXmlUrl(?string $v): self { $this->xmlUrl = $v; return $this; }

    public function getMunicipalServiceId(): ?string { return $this->municipalServiceId; }
    public function setMunicipalServiceId(?string $v): self { $this->municipalServiceId = $v; return $this; }

    public function getMunicipalServiceCode(): ?string { return $this->municipalServiceCode; }
    public function setMunicipalServiceCode(?string $v): self { $this->municipalServiceCode = $v; return $this; }

    public function getMunicipalServiceName(): ?string { return $this->municipalServiceName; }
    public function setMunicipalServiceName(?string $v): self { $this->municipalServiceName = $v; return $this; }

    public function getImpostosJson(): ?string { return $this->impostosJson; }
    public function setImpostosJson(?string $v): self { $this->impostosJson = $v; return $this; }

    public function getImpostos(): array
    {
        return $this->impostosJson ? (json_decode($this->impostosJson, true) ?? []) : [];
    }

    public function setImpostos(array $impostos): self
    {
        $this->impostosJson = json_encode($impostos);
        return $this;
    }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $v): self { $this->observacoes = $v; return $this; }

    public function getCriadoEm(): \DateTimeInterface { return $this->criadoEm; }

    public function getAtualizadoEm(): ?\DateTimeInterface { return $this->atualizadoEm; }
    public function setAtualizadoEm(?\DateTimeInterface $v): self { $this->atualizadoEm = $v; return $this; }

    public function isAutorizada(): bool { return $this->status === self::STATUS_AUTORIZADA; }
    public function isCancelada(): bool  { return $this->status === self::STATUS_CANCELADA; }
    public function isComErro(): bool    { return $this->status === self::STATUS_ERRO; }

    public function getStatusLabel(): string
    {
        $map = [
            self::STATUS_AGENDADA     => 'Agendada',
            self::STATUS_SINCRONIZADA => 'Enviada à prefeitura',
            self::STATUS_AUTORIZADA   => 'Autorizada',
            self::STATUS_CANCELADA    => 'Cancelada',
            self::STATUS_ERRO         => 'Erro',
        ];
        return $map[$this->status] ?? 'Pendente';
    }

    public function getStatusBadgeClass(): string
    {
        if ($this->status === self::STATUS_AUTORIZADA) {
            return 'success';
        }
        if ($this->status === self::STATUS_AGENDADA || $this->status === self::STATUS_SINCRONIZADA) {
            return 'info';
        }
        if ($this->status === self::STATUS_CANCELADA) {
            return 'secondary';
        }
        if ($this->status === self::STATUS_ERRO) {
            return 'danger';
        }
        return 'warning';
    }
}