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
    private $id;

    /** @ORM\Column(type="integer", name="estabelecimento_id") */
    private $estabelecimentoId;

    /** @ORM\Column(type="string", length=255) */
    private $nome;

    /** @ORM\Column(type="decimal", precision=10, scale=2, nullable=true) */
    private $precoCusto;

    /** @ORM\Column(type="decimal", precision=10, scale=2, nullable=true) */
    private $precoVenda;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $estoqueAtual = 0;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $unidade;

    /** @ORM\Column(type="datetime", nullable=true) */
    private $dataCadastro;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $codigo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $refrigerado;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $data_validade;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $codigo_fabrica;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $ncm;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cfop;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cest;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_icms;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_pis;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_cofins;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_ipi;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_iss;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_ibs;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $aliquota_cbs;

    // --- Getters e Setters ---

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): self { $this->nome = $nome; return $this; }

    public function getPrecoCusto(): ?float { return (float)$this->precoCusto; }
    public function setPrecoCusto(?float $preco): self { $this->precoCusto = $preco; return $this; }

    public function getPrecoVenda(): ?float { return (float)$this->precoVenda; }
    public function setPrecoVenda(?float $preco): self { $this->precoVenda = $preco; return $this; }

    public function getEstoqueAtual(): int { return $this->estoqueAtual; }
    public function setEstoqueAtual(int $qtd): self { $this->estoqueAtual = $qtd; return $this; }

    public function getUnidade(): ?string { return $this->unidade; }
    public function setUnidade(?string $u): self { $this->unidade = $u; return $this; }

    public function getDataCadastro(): ?\DateTimeInterface { return $this->dataCadastro; }
    public function setDataCadastro(?\DateTimeInterface $data): self { $this->dataCadastro = $data; return $this; }

    public function getCodigo(): ?int
    {
        return $this->codigo;
    }

    public function setCodigo(?int $codigo): self
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getRefrigerado(): ?string
    {
        return $this->refrigerado;
    }

    public function setRefrigerado(?string $refrigerado): self
    {
        $this->refrigerado = $refrigerado;

        return $this;
    }

    public function getDataValidade(): ?\DateTimeInterface
    {
        return $this->data_validade;
    }

    public function setDataValidade(?\DateTimeInterface $data_validade): self
    {
        $this->data_validade = $data_validade;

        return $this;
    }

    public function getCodigoFabrica(): ?string
    {
        return $this->codigo_fabrica;
    }

    public function setCodigoFabrica(?string $codigo_fabrica): self
    {
        $this->codigo_fabrica = $codigo_fabrica;

        return $this;
    }

    public function getNcm(): ?int
    {
        return $this->ncm;
    }

    public function setNcm(?int $ncm): self
    {
        $this->ncm = $ncm;

        return $this;
    }

    public function getCfop(): ?int
    {
        return $this->cfop;
    }

    public function setCfop(?int $cfop): self
    {
        $this->cfop = $cfop;

        return $this;
    }

    public function getCest(): ?int
    {
        return $this->cest;
    }

    public function setCest(?int $cest): self
    {
        $this->cest = $cest;

        return $this;
    }

    public function getAliquotaIcms(): ?float
    {
        return $this->aliquota_icms;
    }

    public function setAliquotaIcms(?float $aliquota_icms): self
    {
        $this->aliquota_icms = $aliquota_icms;

        return $this;
    }

    public function getAliquotaPis(): ?float
    {
        return $this->aliquota_pis;
    }

    public function setAliquotaPis(?float $aliquota_pis): self
    {
        $this->aliquota_pis = $aliquota_pis;

        return $this;
    }

    public function getAliquotaCofins(): ?float
    {
        return $this->aliquota_cofins;
    }

    public function setAliquotaCofins(?float $aliquota_cofins): self
    {
        $this->aliquota_cofins = $aliquota_cofins;

        return $this;
    }

    public function getAliquotaIpi(): ?float
    {
        return $this->aliquota_ipi;
    }

    public function setAliquotaIpi(?float $aliquota_ipi): self
    {
        $this->aliquota_ipi = $aliquota_ipi;

        return $this;
    }

    public function getAliquotaIss(): ?float
    {
        return $this->aliquota_iss;
    }

    public function setAliquotaIss(?float $aliquota_iss): self
    {
        $this->aliquota_iss = $aliquota_iss;

        return $this;
    }

    public function getAliquotaIbs(): ?float
    {
        return $this->aliquota_ibs;
    }

    public function setAliqotaIbs(?float $aliquota_ibs): self
    {
        $this->aliquota_ibs = $aliquota_ibs;

        return $this;
    }

    public function getAliquotaCbs(): ?float
    {
        return $this->aliquota_cbs;
    }

    public function setAliquotaCbs(?float $aliquota_cbs): self
    {
        $this->aliquota_cbs = $aliquota_cbs;

        return $this;
    }
}
