<?php
namespace App\Entity;

class HospedagemCaes
{
    private $id;
    private $clienteId;
    private $petId;
    private $dataEntrada;
    private $dataSaida;
    private $valor;
    private $observacoes;

    public function getId() { return $this->id; }
    public function getClienteId() { return $this->clienteId; }
    public function setClienteId($clienteId) { $this->clienteId = $clienteId; return $this; }
    public function getPetId() { return $this->petId; }
    public function setPetId($petId) { $this->petId = $petId; return $this; }
    public function getDataEntrada(): \DateTime { return $this->dataEntrada; }
    public function setDataEntrada(\DateTime $dataEntrada) { $this->dataEntrada = $dataEntrada; return $this; }
    public function getDataSaida(): \DateTime { return $this->dataSaida; }
    public function setDataSaida(\DateTime $dataSaida) { $this->dataSaida = $dataSaida; return $this; }
    public function getValor() { return $this->valor; }
    public function setValor($valor) { $this->valor = $valor; return $this; }
    public function getObservacoes() { return $this->observacoes; }
    public function setObservacoes($observacoes) { $this->observacoes = $observacoes; return $this; }
}
