<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PesoPetRepository")
 * @ORM\Table(name="peso")
 */
class PesoPet
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
    private $petId;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private $peso;

    /**
     * @ORM\Column(type="date")
     */
    private $data;

    /**
     * @ORM\Column(type="time", nullable=true)
     */
    private $hora;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $observacoes;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $veterinarioId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTime();
        $this->data = new \DateTime();
        $this->hora = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPetId(): ?int
    {
        return $this->petId;
    }

    public function setPetId(int $petId): self
    {
        $this->petId = $petId;
        return $this;
    }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimentoId;
    }

    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getPeso(): ?string
    {
        return $this->peso;
    }

    public function setPeso(string $peso): self
    {
        $this->peso = $peso;
        return $this;
    }

    public function getData(): ?\DateTimeInterface
    {
        return $this->data;
    }

    public function setData(\DateTimeInterface $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getHora(): ?\DateTimeInterface
    {
        return $this->hora;
    }

    public function setHora(?\DateTimeInterface $hora): self
    {
        $this->hora = $hora;
        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(?string $observacoes): self
    {
        $this->observacoes = $observacoes;
        return $this;
    }

    public function getVeterinarioId(): ?int
    {
        return $this->veterinarioId;
    }

    public function setVeterinarioId(?int $veterinarioId): self
    {
        $this->veterinarioId = $veterinarioId;
        return $this;
    }

    public function getCriadoEm(): ?\DateTimeInterface
    {
        return $this->criadoEm;
    }

    public function setCriadoEm(\DateTimeInterface $criadoEm): self
    {
        $this->criadoEm = $criadoEm;
        return $this;
    }
}