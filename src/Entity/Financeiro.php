<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\FinanceiroRepository;

/**
 * @ORM\Entity(repositoryClass=App\Repository\FinanceiroRepository::class)
 */
class Financeiro
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $descricao;

    /**
     * @ORM\Column(type="decimal", nullable=true)
     */
    private $valor;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $data;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $petId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $petNome;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $origem;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $status;
    
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $metodoPagamento; // Nova propriedade

    private $especie;
    private $sexo;
    private $porte;
    private $observacoes;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $inativar = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }

    public function getValor(): ?float
    {
        return $this->valor;
    }

    public function setValor(float $valor): self
    {
        $this->valor = $valor;
        return $this;
    }

    public function getData(): ?\DateTime
    {
        return $this->data;
    }

    public function setData(\DateTime $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getPetId(): ?int
    {
        return $this->petId;
    }

    public function setPetId(?int $petId): self
    {
        $this->petId = $petId;
        return $this;
    }

    public function getPetNome(): ?string
    {
        return $this->petNome;
    }

    public function setPetNome(?string $petNome): self
    {
        $this->petNome = $petNome;
        return $this;
    }

    public function getOrigem(): ?string
    {
        return $this->origem;
    }

    public function setOrigem(?string $origem): self
    {
        $this->origem = $origem;
        return $this;
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
    
    public function getMetodoPagamento(): ?string
    {
        return $this->metodoPagamento;
    }

    public function setMetodoPagamento(?string $metodoPagamento): self
    {
        $this->metodoPagamento = $metodoPagamento;
        return $this;
    }

    public function getEspecie(): ?string
    {
        return $this->especie;
    }

    public function setEspecie(string $especie): self
    {
        $this->especie = $especie;
        return $this;
    }

    public function getSexo(): ?string
    {
        return $this->sexo;
    }

    public function setSexo(string $sexo): self
    {
        $this->sexo = $sexo;
        return $this;
    }

    public function getPorte(): ?string
    {
        return $this->porte;
    }

    public function setPorte(?string $porte): self
    {
        $this->porte = $porte;
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

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function isInativar(): bool
    {
        return $this->inativar;
    }

    public function setInativar(bool $inativar): self
    {
        $this->inativar = $inativar;
        return $this;
    }
}