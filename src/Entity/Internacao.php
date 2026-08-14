<?php

namespace App\Entity;

use App\Repository\InternacaoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InternacaoRepository::class)
 * @ORM\Table(name="internacao")
 */
class Internacao
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $data_inicio;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $motivo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $situacao;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $risco;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $box;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $alta_prevista;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $diagnostico;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $prognostico;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $anotacoes;

    /**
     * @ORM\Column(type="integer")
     */
    private $pet_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dono_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $veterinario_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimento_id;

    // =======================
    // GETTERS & SETTERS
    // =======================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDataInicio(): ?\DateTimeInterface
    {
        return $this->data_inicio;
    }

    public function setDataInicio(\DateTimeInterface $data_inicio): self
    {
        $this->data_inicio = $data_inicio;
        return $this;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function setMotivo(?string $motivo): self
    {
        $this->motivo = $motivo;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSituacao(): ?string
    {
        return $this->situacao;
    }

    public function setSituacao(?string $situacao): self
    {
        $this->situacao = $situacao;
        return $this;
    }

    public function getRisco(): ?string
    {
        return $this->risco;
    }

    public function setRisco(?string $risco): self
    {
        $this->risco = $risco;
        return $this;
    }

    public function getBox(): ?string
    {
        return $this->box;
    }

    public function setBox(?string $box): self
    {
        $this->box = $box;
        return $this;
    }

    public function getAltaPrevista(): ?\DateTimeInterface
    {
        return $this->alta_prevista;
    }

    public function setAltaPrevista(?\DateTimeInterface $alta_prevista): self
    {
        $this->alta_prevista = $alta_prevista;
        return $this;
    }

    public function getDiagnostico(): ?string
    {
        return $this->diagnostico;
    }

    public function setDiagnostico(?string $diagnostico): self
    {
        $this->diagnostico = $diagnostico;
        return $this;
    }

    public function getPrognostico(): ?string
    {
        return $this->prognostico;
    }

    public function setPrognostico(?string $prognostico): self
    {
        $this->prognostico = $prognostico;
        return $this;
    }

    public function getAnotacoes(): ?string
    {
        return $this->anotacoes;
    }

    public function setAnotacoes(?string $anotacoes): self
    {
        $this->anotacoes = $anotacoes;
        return $this;
    }

    public function getPetId(): ?int
    {
        return $this->pet_id;
    }

    public function setPetId(int $pet_id): self
    {
        $this->pet_id = $pet_id;
        return $this;
    }

    public function getDonoId(): ?int
    {
        return $this->dono_id;
    }

    public function setDonoId(?int $dono_id): self
    {
        $this->dono_id = $dono_id;
        return $this;
    }

    public function getVeterinarioId(): ?int
    {
        return $this->veterinario_id;
    }

    public function setVeterinarioId(?int $veterinario_id): self
    {
        $this->veterinario_id = $veterinario_id;
        return $this;
    }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimento_id;
    }

    public function setEstabelecimentoId(int $estabelecimento_id): self
    {
        $this->estabelecimento_id = $estabelecimento_id;
        return $this;
    }
}
