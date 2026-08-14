<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HospedagemRepository")
 * @ORM\Table(name="hospedagem")
 */
class Hospedagem
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;
    
    /** @ORM\Column(name="estabelecimento_id", type="integer") */
    private $estabelecimentoId;
    
    /** @ORM\Column(name="pet_id", type="integer") */
    private $petId;
    
    /** @ORM\Column(name="cliente_id", type="integer") */
    private $clienteId;
    
    /** @ORM\Column(name="box_id", type="integer") */
    private $boxId;
    
    /** @ORM\Column(name="reserva_id", type="integer", nullable=true) */
    private $reservaId;
    
    /** @ORM\Column(name="data_entrada", type="datetime") */
    private $dataEntrada;
    
    /** @ORM\Column(name="data_saida_prevista", type="date") */
    private $dataSaidaPrevista;
    
    /** @ORM\Column(name="data_saida_real", type="datetime", nullable=true) */
    private $dataSaidaReal;
    
    /** @ORM\Column(name="valor_diaria", type="decimal", precision=10, scale=2) */
    private $valorDiaria;
    
    /** @ORM\Column(name="valor_servicos", type="decimal", precision=10, scale=2) */
    private $valorServicos = 0;
    
    /** @ORM\Column(name="valor_total", type="decimal", precision=10, scale=2, nullable=true) */
    private $valorTotal;
    
    /** @ORM\Column(type="string", columnDefinition="ENUM('ativa', 'concluida', 'cancelada')") */
    private $status = 'ativa';
    
    /** @ORM\Column(name="observacoes_entrada", type="text", nullable=true) */
    private $observacoesEntrada;
    
    /** @ORM\Column(name="instrucoes_especiais", type="text", nullable=true) */
    private $instrucoesEspeciais;

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }
    public function getPetId(): ?int { return $this->petId; }
    public function setPetId(int $id): self { $this->petId = $id; return $this; }
    public function getClienteId(): ?int { return $this->clienteId; }
    public function setClienteId(int $id): self { $this->clienteId = $id; return $this; }
    public function getBoxId(): ?int { return $this->boxId; }
    public function setBoxId(int $id): self { $this->boxId = $id; return $this; }
    public function getReservaId(): ?int { return $this->reservaId; }
    public function setReservaId(?int $id): self { $this->reservaId = $id; return $this; }
    public function getDataEntrada(): ?\DateTimeInterface { return $this->dataEntrada; }
    public function setDataEntrada(\DateTimeInterface $data): self { $this->dataEntrada = $data; return $this; }
    public function getDataSaidaPrevista(): ?\DateTimeInterface { return $this->dataSaidaPrevista; }
    public function setDataSaidaPrevista(\DateTimeInterface $data): self { $this->dataSaidaPrevista = $data; return $this; }
    public function getDataSaidaReal(): ?\DateTimeInterface { return $this->dataSaidaReal; }
    public function setDataSaidaReal(?\DateTimeInterface $data): self { $this->dataSaidaReal = $data; return $this; }
    public function getValorDiaria(): ?float { return $this->valorDiaria; }
    public function setValorDiaria(float $valor): self { $this->valorDiaria = $valor; return $this; }
    public function getValorServicos(): ?float { return $this->valorServicos; }
    public function setValorServicos(float $valor): self { $this->valorServicos = $valor; return $this; }
    public function getValorTotal(): ?float { return $this->valorTotal; }
    public function setValorTotal(?float $valor): self { $this->valorTotal = $valor; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getObservacoesEntrada(): ?string { return $this->observacoesEntrada; }
    public function setObservacoesEntrada(?string $obs): self { $this->observacoesEntrada = $obs; return $this; }
    public function getInstrucoesEspeciais(): ?string { return $this->instrucoesEspeciais; }
    public function setInstrucoesEspeciais(?string $inst): self { $this->instrucoesEspeciais = $inst; return $this; }
}
