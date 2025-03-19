<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AgendamentoRepository;

/**
 * @ORM\Entity(repositoryClass=AgendamentoRepository::class)
 */
class Agendamento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $data;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $concluido = false;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $pronto = false;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="horaChegada")
     */
    private $horaChegada;

    /**
     * @ORM\Column(type="string", length=30, nullable=false, options={"default": "pendente"})
     */
    private $metodo_pagamento = 'pendente';

    /**
     * @ORM\Column(type="datetime", nullable=true, name="horaSaida")
     */
    private $horaSaida;

    /**
     * @ORM\Column(type="boolean", name="taxi_dog", options={"default": false})
     */
    private bool $taxi_dog = false;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true, name="taxa_taxi_dog")
     */
    private ?float $taxa_taxi_dog = null;

    /**
     * @ORM\Column(type="boolean", name="pacote_semanal", options={"default": false})
     */
    private bool $pacote_semanal = false;

    /**
     * @ORM\Column(type="boolean", name="pacote_quinzenal", options={"default": false})
     */
    private bool $pacote_quinzenal = false;

    /**
     * @ORM\Column(type="integer", nullable=true, name="donoId")
     */
    private $donoId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getData(): ?\DateTime
    {
        return $this->data;
    }

    public function setData(\DateTime $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function isConcluido(): bool
    {
        return $this->concluido;
    }

    public function setConcluido(bool $concluido): self
    {
        $this->concluido = $concluido;
        return $this;
    }

    public function isPronto(): bool
    {
        return $this->pronto;
    }

    public function setPronto(bool $pronto): self
    {
        $this->pronto = $pronto;
        return $this;
    }

    public function getHoraChegada(): ?\DateTime
    {
        return $this->horaChegada;
    }

    public function setHoraChegada(?\DateTime $horaChegada): self
    {
        $this->horaChegada = $horaChegada;
        return $this;
    }

    public function getMetodoPagamento(): ?string
    {
        return $this->metodo_pagamento;
    }

    public function setMetodoPagamento(string $metodo_pagamento): self
    {
        $this->metodo_pagamento = $metodo_pagamento;
        return $this;
    }

    public function getHoraSaida(): ?\DateTime
    {
        return $this->horaSaida;
    }

    public function setHoraSaida(?\DateTime $horaSaida): self
    {
        $this->horaSaida = $horaSaida;
        return $this;
    }

    public function getTaxiDog(): bool
    {
        return $this->taxi_dog;
    }

    public function setTaxiDog(bool $taxi_dog): self
    {
        $this->taxi_dog = $taxi_dog;
        return $this;
    }

    public function getTaxaTaxiDog(): ?float
    {
        return $this->taxa_taxi_dog;
    }

    public function setTaxaTaxiDog(?float $taxa_taxi_dog): self
    {
        $this->taxa_taxi_dog = $taxa_taxi_dog;
        return $this;
    }

    public function getPacoteSemanal(): bool
    {
        return $this->pacote_semanal;
    }

    public function setPacoteSemanal(bool $pacote_semanal): self
    {
        $this->pacote_semanal = $pacote_semanal;
        return $this;
    }

    public function getPacoteQuinzenal(): bool
    {
        return $this->pacote_quinzenal;
    }

    public function setPacoteQuinzenal(bool $pacote_quinzenal): self
    {
        $this->pacote_quinzenal = $pacote_quinzenal;
        return $this;
    }

    public function getDonoId(): ?int
    {
        return $this->donoId;
    }

    public function setDonoId(?int $donoId): self
    {
        $this->donoId = $donoId;

        return $this;
    }
}
