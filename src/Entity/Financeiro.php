<?php
namespace App\Entity;

class Financeiro
{
    private $id;
    private $descricao;
    private $valor;
    private $data;
    private $pet_id;
    private $pet_nome;

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

    public function getpet_id(): ?int
    {
        return $this->pet_id;
    }

    public function setpet_id(?int $pet_id): self
    {
        $this->pet_id = $pet_id;
        return $this;
    }

    public function getpet_nome(): ?string
    {
        return $this->pet_nome;
    }

    public function setpet_nome(?string $pet_nome): self
    {
        $this->pet_nome = $pet_nome;
        return $this;
    }
}
