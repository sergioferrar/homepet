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
     * @ORM\Column(name="estabelecimento_id", type="integer")
     */
    private $estabelecimentoId;

    /**
     * @ORM\Column(name="cliente_id", type="integer")
     */
    private $clienteId;

    /**
     * @ORM\Column(name="pet_id", type="integer")
     */
    private $petId;

    /**
     * @ORM\Column(type="date")
     */
    private $data;

    /**
     * @ORM\Column(type="time")
     */
    private $hora;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $tipo;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $observacoes;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $anamnese;

    /**
     * @ORM\Column(type="string", length=20, options={"default": "aguardando"})
     */
    private $status = 'aguardando';

    /**
     * @ORM\Column(name="criado_em", type="datetime")
     */
    private $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $estabelecimentoId): self
    {
        $this->estabelecimentoId = $estabelecimentoId;
        return $this;
    }

    public function getClienteId(): ?int { return $this->clienteId; }
    public function setClienteId(int $clienteId): self
    {
        $this->clienteId = $clienteId;
        return $this;
    }

    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(int $petId): self
    {
        $this->petId = $petId;
        return $this;
    }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getHora(): ?\DateTimeInterface { return $this->hora; }
    public function setHora(\DateTimeInterface $hora): self
    {
        $this->hora = $hora;
        return $this;
    }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): self
    {
        $this->observacoes = $observacoes;
        return $this;
    }

    public function getAnamnese(): ?string { return $this->anamnese; }
    public function setAnamnese(?string $anamnese): self
    {
        $this->anamnese = $anamnese;
        return $this;
    }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCriadoEm(): ?\DateTimeInterface { return $this->criadoEm; }
    public function setCriadoEm(\DateTimeInterface $criadoEm): self
    {
        $this->criadoEm = $criadoEm;
        return $this;
    }
}
