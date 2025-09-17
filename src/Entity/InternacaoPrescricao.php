<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="internacao_prescricao")
 */
class InternacaoPrescricao
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="integer", name="internacao_id") */
    private $internacaoId;

    /**
     * @ORM\ManyToOne(targetEntity=Medicamento::class)
     * @ORM\JoinColumn(name="medicamento_id", referencedColumnName="id", nullable=false)
     */
    private $medicamento;

    /** @ORM\Column(type="text") */
    private $descricao;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $dose;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $frequencia;

    /**
     * @ORM\Column(type="integer", name="frequencia_horas")
     */
    private $frequenciaHoras = 6;

    /**
     * @ORM\Column(type="integer", name="duracao_dias")
     */
    private $duracaoDias = 1;

    /** @ORM\Column(type="datetime", name="data_hora") */
    private $dataHora;

    /** @ORM\Column(type="datetime", name="criado_em") */
    private $criadoEm;

    // --- GETTERS & SETTERS ---
    public function getId(): ?int { return $this->id; }

    public function getInternacaoId(): ?int { return $this->internacaoId; }
    public function setInternacaoId(int $id): self { $this->internacaoId = $id; return $this; }

    public function getMedicamento(): ?Medicamento { return $this->medicamento; }
    public function setMedicamento(?Medicamento $medicamento): self { $this->medicamento = $medicamento; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(string $descricao): self { $this->descricao = $descricao; return $this; }

    public function getDose(): ?string { return $this->dose; }
    public function setDose(?string $dose): self { $this->dose = $dose; return $this; }

    public function getFrequencia(): ?string { return $this->frequencia; }
    public function setFrequencia(?string $frequencia): self { $this->frequencia = $frequencia; return $this; }

    public function getFrequenciaHoras(): int { return $this->frequenciaHoras; }
    public function setFrequenciaHoras(int $horas): self { $this->frequenciaHoras = $horas; return $this; }

    public function getDuracaoDias(): int { return $this->duracaoDias; }
    public function setDuracaoDias(int $dias): self { $this->duracaoDias = $dias; return $this; }

    public function getDataHora(): ?\DateTimeInterface { return $this->dataHora; }
    public function setDataHora(\DateTimeInterface $dataHora): self { $this->dataHora = $dataHora; return $this; }

    public function getCriadoEm(): ?\DateTimeInterface { return $this->criadoEm; }
    public function setCriadoEm(\DateTimeInterface $criadoEm): self { $this->criadoEm = $criadoEm; return $this; }
}
