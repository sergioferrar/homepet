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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $petshop_id;

    /**
     * Vínculo com a tabela veterinario quando o nível de acesso é "Veterinário".
     * @ORM\Column(type="integer", nullable=true, name="veterinario_id")
     */
    private $veterinarioId;

    /**
     * Usuário ativo. Fica inativo até definir a senha pelo link enviado por e-mail.
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $ativo = true;

    /**
     * Token para o usuário definir a própria senha (cadastro sem senha).
     * @ORM\Column(type="string", length=64, nullable=true, name="token_senha")
     */
    private $tokenSenha;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="token_expira")
     */
    private $tokenExpira;

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
        $roles = $this->roles;

        // Garantir ROLE_SUPER_ADMIN baseado no accessLevel
        if ($this->accessLevel === 'Super Admin') {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        // Garantir pelo menos ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
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

    public function setPetshopId(?int $petshop_id): self
    {
        $this->petshop_id = $petshop_id;
        return $this;
    }

    public function getVeterinarioId(): ?int
    {
        return $this->veterinarioId;
    }

    public function setVeterinarioId(?int $veterinarioId): self
    {
        $this->veterinarioId = $veterinarioId;
        return $this;
    }

    public function getAtivo(): bool
    {
        return (bool) $this->ativo;
    }

    public function setAtivo(bool $ativo): self
    {
        $this->ativo = $ativo;
        return $this;
    }

    public function getTokenSenha(): ?string
    {
        return $this->tokenSenha;
    }

    public function setTokenSenha(?string $tokenSenha): self
    {
        $this->tokenSenha = $tokenSenha;
        return $this;
    }

    public function getTokenExpira(): ?\DateTimeInterface
    {
        return $this->tokenExpira;
    }

    public function setTokenExpira(?\DateTimeInterface $tokenExpira): self
    {
        $this->tokenExpira = $tokenExpira;
        return $this;
    }
}