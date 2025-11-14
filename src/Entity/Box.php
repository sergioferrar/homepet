<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BoxRepository")
 * @ORM\Table(name="box")
 */
class Box
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(name="estabelecimento_id", type="integer") */
    private $estabelecimentoId;

    /** @ORM\Column(type="string", length=20) */
    private $numero;

    /** @ORM\Column(type="string", columnDefinition="ENUM('pequeno', 'medio', 'grande')") */
    private $tipo;

    /** @ORM\Column(type="string", length=100, nullable=true) */
    private $localizacao;

    /** @ORM\Column(type="string", columnDefinition="ENUM('disponivel', 'ocupado', 'manutencao', 'reservado')") */
    private $status = 'disponivel';

    /** @ORM\Column(type="integer") */
    private $capacidade = 1;

    /** @ORM\Column(name="valor_diaria", type="decimal", precision=10, scale=2) */
    private $valorDiaria;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacoes;

    /** @ORM\Column(name="created_at", type="datetime") */
    private $createdAt;

    /** @ORM\Column(name="updated_at", type="datetime") */
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): self { $this->numero = $numero; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getLocalizacao(): ?string { return $this->localizacao; }
    public function setLocalizacao(?string $localizacao): self { $this->localizacao = $localizacao; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCapacidade(): ?int { return $this->capacidade; }
    public function setCapacidade(int $capacidade): self { $this->capacidade = $capacidade; return $this; }

    public function getValorDiaria(): ?float { return $this->valorDiaria; }
    public function setValorDiaria(float $valor): self { $this->valorDiaria = $valor; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $obs): self { $this->observacoes = $obs; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
