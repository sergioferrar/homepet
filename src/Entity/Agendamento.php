<?php
namespace App\Entity;

class Agendamento
{
    private $id;
    private $data;
    private $pet_id; 
    private $servico_id;
    private $concluido = 0;

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
}
