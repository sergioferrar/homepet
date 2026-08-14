<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="orcamento")
 */
class Orcamento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $clienteId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $clienteNome;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $petId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $petNome;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valorTotal;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private $desconto;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valorFinal;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $status = 'pendente'; // pendente, aprovado, recusado, convertido

    /**
     * @ORM\Column(type="datetime")
     */
    private $dataCriacao;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dataValidade;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $observacoes;

    // Getters e Setters

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getClienteId(): ?int { return $this->clienteId; }
    public function setClienteId(?int $id): self { $this->clienteId = $id; return $this; }

    public function getClienteNome(): ?string { return $this->clienteNome; }
    public function setClienteNome(string $nome): self { $this->clienteNome = $nome; return $this; }

    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(?int $id): self { $this->petId = $id; return $this; }

    public function getPetNome(): ?string { return $this->petNome; }
    public function setPetNome(?string $nome): self { $this->petNome = $nome; return $this; }

    public function getValorTotal(): ?float { return (float)$this->valorTotal; }
    public function setValorTotal(float $valor): self { $this->valorTotal = $valor; return $this; }

    public function getDesconto(): ?float { return (float)$this->desconto; }
    public function setDesconto(?float $desconto): self { $this->desconto = $desconto; return $this; }

    public function getValorFinal(): ?float { return (float)$this->valorFinal; }
    public function setValorFinal(float $valor): self { $this->valorFinal = $valor; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getDataCriacao(): ?\DateTimeInterface { return $this->dataCriacao; }
    public function setDataCriacao(\DateTimeInterface $data): self { $this->dataCriacao = $data; return $this; }

    public function getDataValidade(): ?\DateTimeInterface { return $this->dataValidade; }
    public function setDataValidade(?\DateTimeInterface $data): self { $this->dataValidade = $data; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $obs): self { $this->observacoes = $obs; return $this; }
}
