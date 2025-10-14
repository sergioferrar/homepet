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
}
