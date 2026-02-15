<?php

namespace App\Entity;

use App\Repository\EstoqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=EstoqueRepository::class)
 * @ORM\Table(name="estoque")
 * @ORM\HasLifecycleCallbacks()
 */
class Estoque
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer", nullable=true, name="produtoId")
     */
    private ?int $produtoId = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $estabelecimentoId = null;

    /**
     * @ORM\Column(type="string", length=45, nullable=true)
     */
    private ?string $localEstoqueId = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\PositiveOrZero(message="A quantidade atual não pode ser negativa")
     */
    private ?int $quantidadeAtual = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\PositiveOrZero(message="A quantidade reservada não pode ser negativa")
     */
    private ?int $quantidadeReserva = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\PositiveOrZero(message="A quantidade disponível não pode ser negativa")
     */
    private ?int $quantidadeDisponivel = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\PositiveOrZero(message="O estoque mínimo não pode ser negativo")
     */
    private ?int $estoqueMinimo = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\PositiveOrZero(message="O estoque máximo não pode ser negativo")
     */
    private ?int $etoqueMaximo = 0;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Assert\PositiveOrZero(message="O custo médio não pode ser negativo")
     */
    private ?float $custoMedio = 0.0;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Assert\PositiveOrZero(message="O custo da última compra não pode ser negativo")
     */
    private ?float $custoUltimaCompra = 0.0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $refrigerado = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $controlaLote = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $controlaValidade = 0;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     * @Assert\Choice(choices={"ativo", "inativo", "suspenso"}, message="Status inválido")
     */
    private ?string $status = 'ativo';

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $updatedBy = null;

    // ==================== LIFECYCLE CALLBACKS ====================

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // ==================== GETTERS AND SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProdutoId(): ?int
    {
        return $this->produtoId;
    }

    public function setProdutoId(?int $produtoId): self
    {
        $this->produtoId = $produtoId;
        return $this;
    }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(?int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getLocalEstoqueId(): ?string
    {
        return $this->localEstoqueId;
    }

    public function setLocalEstoqueId(?string $localEstoqueId): self
    {
        $this->localEstoqueId = $localEstoqueId;
        return $this;
    }

    public function getQuantidadeAtual(): ?int
    {
        return $this->quantidadeAtual;
    }

    public function setQuantidadeAtual(?int $quantidadeAtual): self
    {
        $this->quantidadeAtual = $quantidadeAtual;
        $this->recalcularDisponivel();
        return $this;
    }

    public function getQuantidadeReserva(): ?int
    {
        return $this->quantidadeReserva;
    }

    public function setQuantidadeReserva(?int $quantidadeReserva): self
    {
        $this->quantidadeReserva = $quantidadeReserva;
        $this->recalcularDisponivel();
        return $this;
    }

    public function getQuantidadeDisponivel(): ?int
    {
        return $this->quantidadeDisponivel;
    }

    public function setQuantidadeDisponivel(?int $quantidadeDisponivel): self
    {
        $this->quantidadeDisponivel = $quantidadeDisponivel;
        return $this;
    }

    public function getEstoqueMinimo(): ?int
    {
        return $this->estoqueMinimo;
    }

    public function setEstoqueMinimo(?int $estoqueMinimo): self
    {
        $this->estoqueMinimo = $estoqueMinimo;
        return $this;
    }

    public function getEtoqueMaximo(): ?int
    {
        return $this->etoqueMaximo;
    }

    public function setEtoqueMaximo(?int $etoqueMaximo): self
    {
        $this->etoqueMaximo = $etoqueMaximo;
        return $this;
    }

    public function getCustoMedio(): ?float
    {
        return $this->custoMedio;
    }

    public function setCustoMedio(?float $custoMedio): self
    {
        $this->custoMedio = $custoMedio;
        return $this;
    }

    public function getCustoUltimaCompra(): ?float
    {
        return $this->custoUltimaCompra;
    }

    public function setCustoUltimaCompra(?float $custoUltimaCompra): self
    {
        $this->custoUltimaCompra = $custoUltimaCompra;
        return $this;
    }

    public function getRefrigerado(): ?int
    {
        return $this->refrigerado;
    }

    public function setRefrigerado(?int $refrigerado): self
    {
        $this->refrigerado = $refrigerado;
        return $this;
    }

    public function isRefrigerado(): bool
    {
        return $this->refrigerado === 1;
    }

    public function getControlaLote(): ?int
    {
        return $this->controlaLote;
    }

    public function setControlaLote(?int $controlaLote): self
    {
        $this->controlaLote = $controlaLote;
        return $this;
    }

    public function isControlaLote(): bool
    {
        return $this->controlaLote === 1;
    }

    public function getControlaValidade(): ?int
    {
        return $this->controlaValidade;
    }

    public function setControlaValidade(?int $controlaValidade): self
    {
        $this->controlaValidade = $controlaValidade;
        return $this;
    }

    public function isControlaValidade(): bool
    {
        return $this->controlaValidade === 1;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->status === 'ativo';
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
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

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    // ==================== MÉTODOS AUXILIARES ====================

    /**
     * Recalcula automaticamente a quantidade disponível
     */
    private function recalcularDisponivel(): void
    {
        $this->quantidadeDisponivel = ($this->quantidadeAtual ?? 0) - ($this->quantidadeReserva ?? 0);
    }

    /**
     * Verifica se está com estoque baixo
     */
    public function isEstoqueBaixo(): bool
    {
        return ($this->quantidadeDisponivel ?? 0) < ($this->estoqueMinimo ?? 0);
    }

    /**
     * Verifica se está com estoque crítico (menos de 50% do mínimo)
     */
    public function isEstoqueCritico(): bool
    {
        $limite = ($this->estoqueMinimo ?? 0) * 0.5;
        return ($this->quantidadeDisponivel ?? 0) < $limite;
    }

    /**
     * Verifica se excedeu o estoque máximo
     */
    public function isEstoqueExcedido(): bool
    {
        if (!$this->etoqueMaximo) {
            return false;
        }
        return ($this->quantidadeAtual ?? 0) > $this->etoqueMaximo;
    }

    /**
     * Calcula o percentual de estoque disponível em relação ao máximo
     */
    public function getPercentualEstoque(): float
    {
        if (!$this->etoqueMaximo || $this->etoqueMaximo == 0) {
            return 0;
        }
        return (($this->quantidadeAtual ?? 0) / $this->etoqueMaximo) * 100;
    }

    /**
     * Retorna o valor total do estoque (quantidade × custo médio)
     */
    public function getValorTotalEstoque(): float
    {
        return ($this->quantidadeAtual ?? 0) * ($this->custoMedio ?? 0);
    }

    /**
     * Retorna representação em string
     */
    public function __toString(): string
    {
        return sprintf(
            'Estoque #%d - Produto: %d - Atual: %d - Disponível: %d',
            $this->id,
            $this->produtoId,
            $this->quantidadeAtual,
            $this->quantidadeDisponivel
        );
    }
}