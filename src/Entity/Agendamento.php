<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity(repositoryClass=AgendamentoRepository::class)
 * @ORM\Table(name="Agendamento") 
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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pet_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $servico_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $concluido = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pronto = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="horaChegada")
     */
    private $horaChegada;

    /**
     * @ORM\Column(type="string", length=20, nullable=false, options={"default": "pendente"})
     */
    private $metodo_pagamento = 'pendente';

    /**
     * @ORM\Column(type="datetime", nullable=true, name="horaSaida")
     */
    private $horaSaida;
    /**
     * @ORM\Column(type="boolean", name="taxi_dog")
     */
    private bool $taxi_dog = false;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true, name="taxa_taxi_dog")
     */
    private ?float $taxa_taxi_dog = null;
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getData(): ?\DateTime
    {
        return $this->data;
    }

    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getPet_Id(): ?int
    {
        return $this->pet_id;
    }

    public function setPet_Id(int $pet_id): self
    {
        $this->pet_id = $pet_id;
        return $this;
    }

    public function getServico_Id(): ?int
    {
        return $this->servico_id;
    }

    public function setServico_Id(int $servico_id): self
    {
        $this->servico_id = $servico_id;
        return $this;
    }

    public function getConcluido(): ?bool
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

    public function setTaxiDog(bool $taxi_dog): void
    {
        $this->taxi_dog = $taxi_dog;
    }

    public function getTaxaTaxiDog(): ?float
    {
        return $this->taxa_taxi_dog;
    }

    public function setTaxaTaxiDog(?float $taxa_taxi_dog): void
    {
        $this->taxa_taxi_dog = $taxa_taxi_dog;
    }


}
