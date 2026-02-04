<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProdutoRepository;

/**
 * @ORM\Entity(repositoryClass=ProdutoRepository::class)
 * @ORM\Table(name="produto")
 */
class Produto
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer")
     */
    private int $estabelecimentoId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $nome;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private ?string $codigo = null;

    /**
     * @ORM\Column(type="string", length=3, options={"default": "Não"})
     */
    private string $refrigerado = 'Não';

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private ?string $precoCusto = null;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private ?string $precoVenda = null;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private int $estoqueAtual = 0;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private ?string $unidade = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $dataCadastro = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $dataValidade = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $codigoFabrica = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $ncm = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $cfop = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $cest = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaIcms = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaPis = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaCofins = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaIpi = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaIss = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaIbs = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $aliquotaCbs = null;

    /* =======================
     * Getters & Setters
     * ======================= */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstabelecimentoId(): int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): self
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function isRefrigerado(): bool
    {
        return $this->refrigerado === 'Sim';
    }

    public function setRefrigerado(string $refrigerado): self
    {
        $this->refrigerado = $refrigerado;
        return $this;
    }

    public function getPrecoCusto(): ?string
    {
        return $this->precoCusto;
    }

    public function setPrecoCusto(?string $precoCusto): self
    {
        $this->precoCusto = $precoCusto;
        return $this;
    }

    public function getPrecoVenda(): ?string
    {
        return $this->precoVenda;
    }

    public function setPrecoVenda(?string $precoVenda): self
    {
        $this->precoVenda = $precoVenda;
        return $this;
    }

    public function getEstoqueAtual(): int
    {
        return $this->estoqueAtual;
    }

    public function setEstoqueAtual(int $estoqueAtual): self
    {
        $this->estoqueAtual = $estoqueAtual;
        return $this;
    }

    public function getDataCadastro(): ?\DateTimeInterface
    {
        return $this->dataCadastro;
    }

    public function setDataCadastro(?\DateTimeInterface $dataCadastro): self
    {
        $this->dataCadastro = $dataCadastro;
        return $this;
    }

    public function getDataValidade(): ?\DateTimeInterface
    {
        return $this->dataValidade;
    }

    public function setDataValidade(?\DateTimeInterface $dataValidade): self
    {
        $this->dataValidade = $dataValidade;
        return $this;
    }
}
