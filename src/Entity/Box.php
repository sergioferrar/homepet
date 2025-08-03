<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoxRepository::class)
 * @ORM\Table(name="box")
 */
class Box
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="Pet")
     * @ORM\JoinColumn(name="pet_id", referencedColumnName="id", nullable=true)
     */
    private $pet;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $ocupado;

    // Getters e Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): self
    {
        if ($numero < 1 || $numero > 10) {
            throw new \InvalidArgumentException('O número do box deve estar entre 1 e 10.');
        }
        $this->numero = $numero;

        return $this;
    }

    public function getPet(): ?Pet
    {
        return $this->pet;
    }

    public function setPet(?Pet $pet): self
    {
        $this->pet = $pet;

        return $this;
    }

    public function getOcupado(): ?bool
    {
        return $this->ocupado;
    }

    public function setOcupado(bool $ocupado): self
    {
        $this->ocupado = $ocupado;

        return $this;
    }
}