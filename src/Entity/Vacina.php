<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="vacina")
 */
class Vacina
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
     * @ORM\Column(type="string", length=100)
     */
    private $tipo;

    /**
     * @ORM\Column(type="date")
     */
    private $data_aplicacao;

    /**
     * @ORM\Column(type="date")
     */
    private $data_validade;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $lote;

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

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getDataAplicacao(): ?\DateTimeInterface
    {
        return $this->data_aplicacao;
    }

    public function setDataAplicacao(\DateTimeInterface $data): self
    {
        $this->data_aplicacao = $data;
        return $this;
    }

    public function getDataValidade(): ?\DateTimeInterface
    {
        return $this->data_validade;
    }

    public function setDataValidade(\DateTimeInterface $data): self
    {
        $this->data_validade = $data;
        return $this;
    }

    public function getLote(): ?string
    {
        return $this->lote;
    }

    public function setLote(string $lote): self
    {
        $this->lote = $lote;
        return $this;
    }
}
