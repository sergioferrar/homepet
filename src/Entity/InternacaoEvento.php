<?php

namespace App\Entity;

use App\Repository\InternacaoEventoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InternacaoEventoRepository::class)
 * @ORM\Table(name="internacao_evento")
 */
class InternacaoEvento
{
    /** 
     * @ORM\Id 
     * @ORM\GeneratedValue 
     * @ORM\Column(type="integer") 
     */
    private $id;

    /** @ORM\Column(name="estabelecimento_id", type="integer") */
    private $estabelecimentoId;

    /** @ORM\Column(name="internacao_id", type="integer") */
    private $internacaoId;

    /** @ORM\Column(name="pet_id", type="integer") */
    private $petId;

    /** 
     * @ORM\Column(type="string", columnDefinition="ENUM('internacao','alta','ocorrencia','peso','prescricao','medicacao_exec')") 
     */
    private $tipo;

    /** @ORM\Column(type="string", length=255) */
    private $titulo;

    /** @ORM\Column(type="text", nullable=true) */
    private $descricao;

    /** @ORM\Column(name="data_hora", type="datetime") */
    private $dataHora;

    /** @ORM\Column(name="criado_em", type="datetime") */
    private $criadoEm;

    // --- GETTERS & SETTERS ---
    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getInternacaoId(): ?int { return $this->internacaoId; }
    public function setInternacaoId(int $id): self { $this->internacaoId = $id; return $this; }

    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(int $id): self { $this->petId = $id; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getTitulo(): ?string { return $this->titulo; }
    public function setTitulo(string $titulo): self { $this->titulo = $titulo; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): self { $this->descricao = $descricao; return $this; }

    public function getDataHora(): ?\DateTimeInterface { return $this->dataHora; }
    public function setDataHora(\DateTimeInterface $dataHora): self { $this->dataHora = $dataHora; return $this; }

    public function getCriadoEm(): ?\DateTimeInterface { return $this->criadoEm; }
    public function setCriadoEm(\DateTimeInterface $criado): self { $this->criadoEm = $criado; return $this; }
}
