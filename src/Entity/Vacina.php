<?php

namespace App\Entity;

use App\Repository\VacinaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VacinaRepository::class)
 * @ORM\Table(name="vacina")
 */
class Vacina
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /** 
     * @ORM\Column(name="estabelecimento_id", type="integer") 
     */
    private $estabelecimentoId;

    /** 
     * @ORM\Column(name="pet_id", type="integer") 
     */
    private $petId;

    /** 
     * @ORM\Column(name="tipo", type="string", length=100) 
     */
    private $tipo;

    /** 
     * @ORM\Column(name="data_aplicacao", type="date") 
     */
    private $dataAplicacao;

    /** 
     * @ORM\Column(name="data_validade", type="date", nullable=true) 
     */
    private $dataValidade;

    /** 
     * @ORM\Column(name="lote", type="string", length=100, nullable=true) 
     */
    private $lote;

    /** 
     * @ORM\Column(name="fabricante", type="string", length=150, nullable=true)
     */
    private $fabricante;

    /** 
     * @ORM\Column(name="observacoes", type="text", nullable=true)
     */
    private $observacoes;

    /** 
     * @ORM\Column(name="veterinario_id", type="integer", nullable=true)
     */
    private $veterinarioId;


    // ===================== Getters & Setters =====================

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $estabelecimentoId): self {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(int $petId): self {
        $this->petId = $petId;
        return $this;
    }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self {
        $this->tipo = $tipo;
        return $this;
    }

    public function getDataAplicacao(): ?\DateTimeInterface { return $this->dataAplicacao; }
    public function setDataAplicacao(\DateTimeInterface $dataAplicacao): self {
        $this->dataAplicacao = $dataAplicacao;
        return $this;
    }

    public function getDataValidade(): ?\DateTimeInterface { return $this->dataValidade; }
    public function setDataValidade(?\DateTimeInterface $dataValidade): self {
        $this->dataValidade = $dataValidade;
        return $this;
    }

    public function getLote(): ?string { return $this->lote; }
    public function setLote(?string $lote): self {
        $this->lote = $lote;
        return $this;
    }
    public function getFabricante(): ?string { return $this->fabricante; }
    public function setFabricante(?string $fabricante): self {
        $this->fabricante = $fabricante;
        return $this;
    }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): self {
        $this->observacoes = $observacoes;
        return $this;
    }

    public function getVeterinarioId(): ?int { return $this->veterinarioId; }
    public function setVeterinarioId(?int $veterinarioId): self {
        $this->veterinarioId = $veterinarioId;
        return $this;
    }

}
