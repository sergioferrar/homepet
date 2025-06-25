<?php

namespace App\Entity;

use App\Repository\InternacaoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InternacaoRepository::class)
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
     * @ORM\Column(type="date")
     */
    private $data_inicio;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $motivo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="integer")
     */
    private $pet_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $dono_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimento_id;

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

    public function setDonoId(int $dono_id): self
    {
        $this->dono_id = $dono_id;

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
