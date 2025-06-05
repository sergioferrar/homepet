<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ConsultaRepository;

/**
 * @ORM\Entity(repositoryClass=ConsultaRepository::class)
 * @ORM\Table(name="consulta")
 */
class Consulta
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
    private $estabelecimento_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $cliente_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $pet_id;

    /**
     * @ORM\Column(type="date")
     */
    private $data;

    /**
     * @ORM\Column(type="time")
     */
    private $hora;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $observacoes;

    /**
     * @ORM\Column(type="datetime")
     */
    private $criado_em;

    public function __construct()
    {
        $this->criado_em = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimento_id; }
    public function setEstabelecimentoId(int $estabelecimento_id): self { $this->estabelecimento_id = $estabelecimento_id; return $this; }

    public function getClienteId(): ?int { return $this->cliente_id; }
    public function setClienteId(int $cliente_id): self { $this->cliente_id = $cliente_id; return $this; }

    public function getPetId(): ?int { return $this->pet_id; }
    public function setPetId(int $pet_id): self { $this->pet_id = $pet_id; return $this; }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self { $this->data = $data; return $this; }

    public function getHora(): ?\DateTimeInterface { return $this->hora; }
    public function setHora(\DateTimeInterface $hora): self { $this->hora = $hora; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): self { $this->observacoes = $observacoes; return $this; }

    public function getCriadoEm(): ?\DateTimeInterface { return $this->criado_em; }
    public function setCriadoEm(\DateTimeInterface $criado_em): self { $this->criado_em = $criado_em; return $this; }
}
