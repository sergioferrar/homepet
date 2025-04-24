<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="fila_espera")
 */
class FilaEspera
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="integer") */
    private $pet_id;

    /** @ORM\Column(type="string") */
    private $profissional;

    /** @ORM\Column(type="string") */
    private $sala;

    /** @ORM\Column(type="string") */
    private $status;

    public function getId(): ?int { return $this->id; }
    public function getPetId(): ?int { return $this->pet_id; }
    public function setPetId(int $pet_id): self { $this->pet_id = $pet_id; return $this; }
    public function getProfissional(): ?string { return $this->profissional; }
    public function setProfissional(string $profissional): self { $this->profissional = $profissional; return $this; }
    public function getSala(): ?string { return $this->sala; }
    public function setSala(string $sala): self { $this->sala = $sala; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
}
