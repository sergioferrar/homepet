<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\FinanceiroPendenteRepository;

/**
 * @ORM\Entity(repositoryClass=FinanceiroPendenteRepository::class)
 * @ORM\Table(name="financeiropendente")
 */
class FinanceiroPendente
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $descricao;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valor;

    /**
     * @ORM\Column(type="datetime")
     */
    private $data;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $petId;

    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('dinheiro', 'pix', 'credito', 'debito', 'pendente')")
     */
    private $metodoPagamento;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $agendamentoId;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }

    public function getValor(): ?float
    {
        return $this->valor;
    }

    public function setValor(float $valor): self
    {
        $this->valor = $valor;
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

    public function getPetId(): ?int
    {
        return $this->petId;
    }

    public function setPetId(?int $petId): self
    {
        $this->petId = $petId;
        return $this;
    }

    public function getMetodoPagamento(): ?string
    {
        return $this->metodoPagamento;
    }

    public function setMetodoPagamento(string $metodoPagamento): self
    {
        $this->metodoPagamento = $metodoPagamento;
        return $this;
    }

    public function getAgendamentoId(): ?int
    {
        return $this->agendamentoId;
    }

    public function setAgendamentoId(?int $agendamentoId): self
    {
        $this->agendamentoId = $agendamentoId;
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