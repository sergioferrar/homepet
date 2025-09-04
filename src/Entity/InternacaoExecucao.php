<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\InternacaoExecucaoRepository;

/**
 * @ORM\Entity(repositoryClass=InternacaoExecucaoRepository::class)
 * @ORM\Table(name="internacao_execucao")
 */
class InternacaoExecucao
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $internacaoId;

    /**
     * @ORM\Column(type="integer")
     */
    private $prescricaoId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dataExecucao;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $anotacoes;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInternacaoId(): ?int
    {
        return $this->internacaoId;
    }

    public function setInternacaoId(int $internacaoId): self
    {
        $this->internacaoId = $internacaoId;

        return $this;
    }

    public function getPrescricaoId(): ?int
    {
        return $this->prescricaoId;
    }

    public function setPrescricaoId(int $prescricaoId): self
    {
        $this->prescricaoId = $prescricaoId;

        return $this;
    }

    public function getDataExecucao(): ?\DateTimeInterface
    {
        return $this->dataExecucao;
    }

    public function setDataExecucao(\DateTimeInterface $dataExecucao): self
    {
        $this->dataExecucao = $dataExecucao;

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
}