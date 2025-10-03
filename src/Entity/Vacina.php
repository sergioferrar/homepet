<?php

namespace App\Entity;

use App\Repository\VacinaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VacinaRepository::class)
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

    /** @ORM\Column(type="integer") */
    private $estabelecimento_id;

    /** @ORM\Column(type="integer") */
    private $pet_id;

    /** @ORM\Column(type="string", length=100) */
    private $tipo;

    /** 
     * @ORM\Column(name="data_aplicacao", type="date") 
     */
    private $dataAplicacao;

    /** 
     * @ORM\Column(name="data_validade", type="date", nullable=true) 
     */
    private $dataValidade;

    /** @ORM\Column(type="string", length=100, nullable=true) */
    private $lote;

    // === Getters/Setters ===
    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimento_id; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimento_id = $id; return $this; }

    public function getPetId(): ?int { return $this->pet_id; }
    public function setPetId(int $id): self { $this->pet_id = $id; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getDataAplicacao(): ?\DateTimeInterface { return $this->dataAplicacao; }
    public function setDataAplicacao(\DateTimeInterface $data): self { $this->dataAplicacao = $data; return $this; }

    public function getDataValidade(): ?\DateTimeInterface { return $this->dataValidade; }
    public function setDataValidade(?\DateTimeInterface $data): self { $this->dataValidade = $data; return $this; }

    public function getLote(): ?string { return $this->lote; }
    public function setLote(?string $lote): self { $this->lote = $lote; return $this; }
}
