<?php
namespace App\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

class Usuario implements UserInterface
{
    private $id;
    private $nomeUsuario;
    private $senha;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getNomeUsuario(): ?string
    {
        return $this->nomeUsuario;
    }

    public function setNomeUsuario(string $nomeUsuario): self
    {
        $this->nomeUsuario = $nomeUsuario;
        return $this;
    }

    public function getSenha(): ?string
    {
        return $this->senha;
    }

    public function setSenha(string $senha): self
    {
        $this->senha = $senha;
        return $this;
    }

    
    public function getUsername(): string
    {
        return $this->nomeUsuario;
    }

    public function getPassword(): string
    {
        return $this->senha;
    }

    public function getSalt(): ?string
    {
        
        return null;
    }

    public function getRoles(): array
    {
        
        return ['ROLE_USER'];
    }

    public function eraseCredentials()
    {
        
    }
}
