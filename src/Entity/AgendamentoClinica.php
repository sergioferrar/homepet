<?php

namespace App\Entity;

use App\Repository\AgendamentoClinicaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AgendamentoClinicaRepository::class)
 */
class AgendamentoClinica
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
    private $dataHora;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDataHora(): ?\DateTimeInterface
    {
        return $this->dataHora;
    }

    public function setDataHora(\DateTimeInterface $dataHora): self
    {
        $this->dataHora = $dataHora;

        return $this;
    }
}
