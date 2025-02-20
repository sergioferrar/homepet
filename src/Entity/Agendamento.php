<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity(repositoryClass=AgendamentoRepository::class)
 * @ORM\Table(name="Agendamento") 
 */
class Agendamento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", length=255, nullable=true)
     */
    private $data;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pet_id;

    /**
     * @ORM\Column(type="integer", length=255, nullable=true)
     */
    private $servico_id;

    /**
     * @ORM\Column(type="integer", length=255, nullable=true)
     */
    private $concluido = 0;

    /**
     * @ORM\Column(type="datetime", length=255, nullable=true, name=horaChegada)
     */
    private $horaChegada;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getData(): ?\DateTime
    {
        return $this->data;
    }

    public function setData(\DateTime $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getPet_Id(): ?int
    {
        return $this->pet_id;
    }

    public function setPet_Id(int $pet_id): self
    {
        $this->pet_id = $pet_id;
        return $this;
    }

    public function getServico_Id(): ?int
    {
        return $this->servico_id;
    }

    public function setServico_Id(int $servico_id): self
    {
        $this->servico_id = $servico_id;
        return $this;
    }

    public function getConcluido(): ?bool
    {
        return $this->concluido;
    }

    public function setConcluido(bool $concluido): self
    {
        $this->concluido = $concluido;
        return $this;
    }

    public function isPronto(): bool
    {
        return $this->pronto;
    }

    public function setPronto(bool $pronto): self
    {
        $this->pronto = $pronto;
        return $this;
    }

    public function getHoraChegada(): ?DateTime
    {
        return $this->horaChegada;
    }

    public function setHoraChegada(?DateTime $horaChegada): self
    {
        $this->horaChegada = $horaChegada;
        return $this;
    }
}
