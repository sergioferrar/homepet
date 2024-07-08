<?php
namespace App\Entity;

class Pet
{
    private $id;
    private $nome;
    private $tipo;
    private $idade;
    private $dono_id;

    public function getId(): ?int
    {
        return $this->id;
    }

     public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }


    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getIdade(): ?int
    {
        return $this->idade;
    }

    public function setIdade(int $idade): self
    {
        $this->idade = $idade;
        return $this;
    }

    public function getdono_id(): ?int
    {
        return $this->dono_id;
    }

    public function setdono_id(int $dono_id): self
    {
        $this->dono_id = $dono_id;
        return $this;
    }
}
