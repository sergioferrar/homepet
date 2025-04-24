<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="exame")
 */
class Exame
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
    private $pet_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $agendamento_id;

    /**
     * @ORM\Column(type="text")
     */
    private $descricao;

    /**
     * @ORM\Column(type="text")
     */
    private $arquivo;

    /**
     * @ORM\Column(type="datetime")
     */
    private $criado_em;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAgendamentoId(): ?int
    {
        return $this->agendamento_id;
    }

    public function setAgendamentoId(int $agendamento_id): self
    {
        $this->agendamento_id = $agendamento_id;
        return $this;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }

    public function getArquivo(): ?string
    {
        return $this->arquivo;
    }

    public function setArquivo(string $arquivo): self
    {
        $this->arquivo = $arquivo;
        return $this;
    }

    public function getCriadoEm(): ?\DateTimeInterface
    {
        return $this->criado_em;
    }

    public function setCriadoEm(\DateTimeInterface $criado_em): self
    {
        $this->criado_em = $criado_em;
        return $this;
    }
}
