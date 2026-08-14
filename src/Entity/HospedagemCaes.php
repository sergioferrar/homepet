<?php

namespace App\Entity;

use App\Repository\HospedagemCaesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HospedagemCaesRepository::class)
 */
class HospedagemCaes
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
    private $clienteId;

    /**
     * @ORM\Column(type="integer")
     */
    private $petId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dataEntrada;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dataSaida;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $valor;

    /**
     * @ORM\Column(type="text")
     */
    private $observacoes;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClienteId(): ?int
    {
        return $this->clienteId;
    }

    public function setClienteId(int $clienteId): self
    {
        $this->clienteId = $clienteId;

        return $this;
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

    public function getDataEntrada(): ?\DateTimeInterface
    {
        return $this->dataEntrada;
    }

    public function setDataEntrada(\DateTimeInterface $dataEntrada): self
    {
        $this->dataEntrada = $dataEntrada;

        return $this;
    }

    public function getDataSaida(): ?\DateTimeInterface
    {
        return $this->dataSaida;
    }

    public function setDataSaida(\DateTimeInterface $dataSaida): self
    {
        $this->dataSaida = $dataSaida;

        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(string $valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(string $observacoes): self
    {
        $this->observacoes = $observacoes;

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
}
