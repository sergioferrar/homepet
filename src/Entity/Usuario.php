<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @ORM\Entity(repositoryClass=UsuarioRepository::class)
 * @ORM\Table(name="usuario")
 */
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nomeUsuario;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $senha;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string", length=255, name="access_level")
     */
    private $accessLevel;

    /**
     * @ORM\Column(type="integer")
     */
    private $petshop_id;

    public function getId(): ?int
    {
        return $this->id;
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
//        $this->roles;
        return $this->roles;//['ROLE_USER'];
    }

    public function eraseCredentials()
    {

    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->senha = $password;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAccessLevel(): ?string
    {
        return $this->accessLevel;
    }

    public function setAccessLevel(?string $accessLevel): self
    {
        $this->accessLevel = $accessLevel;

        return $this;
    }

    public function getPetshopId(): ?int
    {
        return $this->petshop_id;
    }

    public function setPetshopId(int $petshop_id): self
    {
        $this->petshop_id = $petshop_id;

        return $this;
    }
}
