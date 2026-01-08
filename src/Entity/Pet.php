<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PetRepository;

/**
 * @ORM\Entity(repositoryClass=App\Repository\PetRepository::class)
 * @ORM\Table(name="pet") 
 */
class Pet
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $nome;

    /** @ORM\Column(type="integer", nullable=true) */
    private $idade;

    /** @ORM\Column(type="date", nullable=true) */
    private $dataNascimento;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $sexo;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $raca;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $porte;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacoes;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $dono_id;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $especie;

    /** @ORM\Column(type="integer") */
    private $estabelecimentoId;

    /** @ORM\Column(type="decimal", precision=5, scale=2, nullable=true) */
    private $peso;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $castrado = false;

    // ===================== Getters & Setters =====================

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getNome(): ?string { return $this->nome; }
    public function setNome(?string $nome): self { $this->nome = $nome; return $this; }

    public function getIdade(): ?int { return $this->idade; }
    public function setIdade(?int $idade): self { $this->idade = $idade; return $this; }

    public function getDataNascimento(): ?\DateTimeInterface { return $this->dataNascimento; }
    public function setDataNascimento(?\DateTimeInterface $dataNascimento): self { $this->dataNascimento = $dataNascimento; return $this; }

    /**
     * Calcula a idade formatada baseada na data de nascimento
     * Retorna string como "2 anos", "6 meses", "2 anos e 3 meses"
     */
    public function getIdadeFormatada(): ?string
    {
        if (!$this->dataNascimento) {
            return $this->idade ? $this->idade . ' ano(s)' : null;
        }
        
        $hoje = new \DateTime();
        $diff = $hoje->diff($this->dataNascimento);
        
        $anos = $diff->y;
        $meses = $diff->m;
        
        if ($anos === 0 && $meses === 0) {
            $dias = $diff->d;
            return $dias . ' dia(s)';
        }
        
        if ($anos === 0) {
            return $meses . ' mês(es)';
        }
        
        if ($meses === 0) {
            return $anos . ' ano(s)';
        }
        
        return $anos . ' ano(s) e ' . $meses . ' mês(es)';
    }

    public function getSexo(): ?string { return $this->sexo; }
    public function setSexo(?string $sexo): self { $this->sexo = $sexo; return $this; }

    public function getRaca(): ?string { return $this->raca; }
    public function setRaca(?string $raca): self { $this->raca = $raca; return $this; }

    public function getPorte(): ?string { return $this->porte; }
    public function setPorte(?string $porte): self { $this->porte = $porte; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): self { $this->observacoes = $observacoes; return $this; }

    public function getDono_Id(): ?string { return $this->dono_id; }
    public function setDono_Id(?string $dono_id): self { $this->dono_id = $dono_id; return $this; }

    public function getEspecie(): ?string { return $this->especie; }
    public function setEspecie(?string $especie): self { $this->especie = $especie; return $this; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $estabelecimentoId): self { $this->estabelecimentoId = $estabelecimentoId; return $this; }

    public function getPeso(): ?float { return $this->peso; }
    public function setPeso(?float $peso): self { $this->peso = $peso; return $this; }

    public function getCastrado(): bool { return $this->castrado; }
    public function setCastrado(bool $castrado): self { $this->castrado = $castrado; return $this; }
}
