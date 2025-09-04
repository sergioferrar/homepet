<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="medicamentos")
 */
class Medicamento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=150)
     */
    private string $nome;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private ?string $via = null;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private ?string $concentracao = null;

    // --- getters/setters ---
    public function getId(): ?int { return $this->id; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): self { $this->nome = $nome; return $this; }

    public function getVia(): ?string { return $this->via; }
    public function setVia(?string $via): self { $this->via = $via; return $this; }

    public function getConcentracao(): ?string { return $this->concentracao; }
    public function setConcentracao(?string $concentracao): self { $this->concentracao = $concentracao; return $this; }
}
