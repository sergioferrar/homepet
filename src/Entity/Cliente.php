<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClienteRepository;

/**
 * @ORM\Entity(repositoryClass=ClienteRepository::class)
 * @ORM\Table(name="Cliente") 
 */
class Cliente
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
    private $nome;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $telefone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $endereco; // Nome correto da propriedade

    public function getId(): ?int
    {
        return $this->id;
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

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(?string $telefone): self
    {
        $this->telefone = $telefone;
        return $this;
    }

    public function getEndereco(): ?string
    {
        return $this->endereco; // Nome correto da propriedade
    }

    public function setEndereco(?string $endereco): self
    {
        $this->endereco = $endereco;
        return $this;
    }
}
