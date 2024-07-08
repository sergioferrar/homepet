<?php
namespace App\Entity;

class Cliente
{
    private $id;
    private $nome;
    private $email;
    private $telefone;
    private $Endereco; // Propriedade com "E" maiúsculo

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(string $telefone): self
    {
        $this->telefone = $telefone;
        return $this;
    }

    public function getEndereco(): ?string
    {
        return $this->Endereco; // Método com "E" maiúsculo
    }

    public function setEndereco(string $Endereco): self
    {
        $this->Endereco = $Endereco; // Propriedade com "E" maiúsculo
        return $this;
    }
}
