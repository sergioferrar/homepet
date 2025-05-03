<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="prontuario")
 */
class Prontuario
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="integer") */
    private $agendamento_id;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacoes;

    /** @ORM\Column(type="text", nullable=true) */
    private $arquivos;

    /** @ORM\Column(type="datetime") */
    private $criado_em;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    public function getId(): ?int { return $this->id; }
    public function getAgendamentoId(): int { return $this->agendamento_id; }
    public function setAgendamentoId(int $id): self { $this->agendamento_id = $id; return $this; }
    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $obs): self { $this->observacoes = $obs; return $this; }
    public function getArquivos(): ?string { return $this->arquivos; }
    public function setArquivos(?string $arq): self { $this->arquivos = $arq; return $this; }
    public function getCriadoEm(): \DateTimeInterface { return $this->criado_em; }
    public function setCriadoEm(\DateTimeInterface $data): self { $this->criado_em = $data; return $this; }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;

        return $this;
    }
}
