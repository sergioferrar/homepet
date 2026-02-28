<?php

namespace App\Entity;

use App\Repository\AssinaturaModuloRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Relacionamento entre um estabelecimento e os módulos adicionais
 * que ele contratou além do plano base.
 *
 * @ORM\Entity(repositoryClass=AssinaturaModuloRepository::class)
 * @ORM\Table(name="assinatura_modulo")
 */
class AssinaturaModulo
{
    // Status possíveis
    public const STATUS_ATIVO      = 'ativo';
    public const STATUS_PENDENTE   = 'pendente';
    public const STATUS_CANCELADO  = 'cancelado';
    public const STATUS_SUSPENSO   = 'suspenso';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * ID do estabelecimento (tabela central, fora do tenant)
     *
     * @ORM\Column(type="integer", name="estabelecimento_id")
     */
    private int $estabelecimentoId;

    /**
     * ID do módulo (referência à tabela modulo)
     *
     * @ORM\Column(type="integer", name="modulo_id")
     */
    private int $moduloId;

    /**
     * Título do módulo ao momento da contratação (snapshot)
     *
     * @ORM\Column(type="string", length=255, name="modulo_titulo")
     */
    private string $moduloTitulo;

    /**
     * Valor mensal adicional cobrado por este módulo
     *
     * @ORM\Column(type="decimal", precision=10, scale=2, name="valor_mensal")
     */
    private string $valorMensal;

    /**
     * Status da contratação do módulo
     *
     * @ORM\Column(type="string", length=20)
     */
    private string $status = self::STATUS_PENDENTE;

    /**
     * ID da assinatura no Mercado Pago (preapproval_id).
     * Quando null, está pendente de aprovação do gateway.
     *
     * @ORM\Column(type="string", length=255, nullable=true, name="subscription_id")
     */
    private ?string $subscriptionId = null;

    /**
     * Link de pagamento gerado pelo gateway para o cliente aprovar.
     *
     * @ORM\Column(type="string", length=500, nullable=true, name="init_point")
     */
    private ?string $initPoint = null;

    /**
     * Data em que o módulo foi contratado
     *
     * @ORM\Column(type="datetime", name="contratado_em")
     */
    private \DateTimeInterface $contratadoEm;

    /**
     * Data em que o módulo foi cancelado (se aplicável)
     *
     * @ORM\Column(type="datetime", nullable=true, name="cancelado_em")
     */
    private ?\DateTimeInterface $canceladoEm = null;

    /**
     * Próxima data de cobrança (sincronizado com MP)
     *
     * @ORM\Column(type="datetime", nullable=true, name="proxima_cobranca")
     */
    private ?\DateTimeInterface $proximaCobranca = null;

    /**
     * Observações internas / log
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $observacoes = null;

    public function __construct()
    {
        $this->contratadoEm = new \DateTime();
    }

    // ── Getters & Setters ────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $v): self { $this->estabelecimentoId = $v; return $this; }

    public function getModuloId(): int { return $this->moduloId; }
    public function setModuloId(int $v): self { $this->moduloId = $v; return $this; }

    public function getModuloTitulo(): string { return $this->moduloTitulo; }
    public function setModuloTitulo(string $v): self { $this->moduloTitulo = $v; return $this; }

    public function getValorMensal(): string { return $this->valorMensal; }
    public function setValorMensal(string $v): self { $this->valorMensal = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }

    public function getSubscriptionId(): ?string { return $this->subscriptionId; }
    public function setSubscriptionId(?string $v): self { $this->subscriptionId = $v; return $this; }

    public function getInitPoint(): ?string { return $this->initPoint; }
    public function setInitPoint(?string $v): self { $this->initPoint = $v; return $this; }

    public function getContratadoEm(): \DateTimeInterface { return $this->contratadoEm; }
    public function setContratadoEm(\DateTimeInterface $v): self { $this->contratadoEm = $v; return $this; }

    public function getCanceladoEm(): ?\DateTimeInterface { return $this->canceladoEm; }
    public function setCanceladoEm(?\DateTimeInterface $v): self { $this->canceladoEm = $v; return $this; }

    public function getProximaCobranca(): ?\DateTimeInterface { return $this->proximaCobranca; }
    public function setProximaCobranca(?\DateTimeInterface $v): self { $this->proximaCobranca = $v; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $v): self { $this->observacoes = $v; return $this; }

    public function isAtivo(): bool { return $this->status === self::STATUS_ATIVO; }
}
