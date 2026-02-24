<?php

namespace App\Entity;

use App\Repository\VendaItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VendaItemRepository::class)
 * @ORM\Table(name="venda_item")
 */
class VendaItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * ID da venda à qual este item pertence.
     *
     * @ORM\Column(type="integer", name="venda_id")
     */
    private $vendaId;

    /**
     * ID numérico do produto ou serviço.
     * Nulo para itens avulsos sem cadastro.
     *
     * @ORM\Column(type="integer", name="produto_id", nullable=true)
     */
    private $produtoId;

    /**
     * Snapshot do nome do produto/serviço no momento da venda.
     * Preserva o histórico mesmo que o cadastro mude depois.
     *
     * @ORM\Column(type="string", length=255, name="produto")
     */
    private $produtoNome;

    /** @ORM\Column(type="integer") */
    private $quantidade;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, name="preco_unitario")
     */
    private $precoUnitario;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $subtotal;

    /**
     * 'produto' | 'servico'
     *
     * @ORM\Column(type="string", length=50)
     */
    private $tipo;

    // ── Getters & Setters ────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * Atalho: aceita int (ID) ou objeto Venda.
     */
    public function setVenda($venda): self
    {
        $this->vendaId = ($venda instanceof Venda) ? $venda->getId() : (int)$venda;
        return $this;
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

    /**
     * Snapshot do nome — coluna `produto` no banco.
     */
    public function getProduto(): ?string
    {
        return $this->produtoNome;
    }

    /**
     * @deprecated Use setProdutoNome() + setProdutoId() juntos.
     *             Mantido por compatibilidade com código legado.
     */
    public function setProduto(string $nome): self
    {
        $this->produtoNome = $nome;
        return $this;
    }

    public function getProdutoNome(): ?string
    {
        return $this->produtoNome;
    }

    public function setProdutoNome(string $nome): self
    {
        $this->produtoNome = $nome;
        return $this;
    }

    public function getQuantidade(): ?int
    {
        return $this->quantidade;
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }

    public function getValorUnitario(): float
    {
        return (float)$this->precoUnitario;
    }

    /** @deprecated Use setPrecoUnitario(). Mantido para compatibilidade. */
    public function setValorUnitario(float $valor): self
    {
        $this->precoUnitario = $valor;
        return $this;
    }

    public function getPrecoUnitario(): float
    {
        return (float)$this->precoUnitario;
    }

    public function setPrecoUnitario(float $valor): self
    {
        $this->precoUnitario = $valor;
        return $this;
    }

    public function getSubtotal(): float
    {
        return (float)$this->subtotal;
    }

    public function setSubtotal(float $subtotal): self
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = strtolower($tipo);
        return $this;
    }
}