<?php

namespace App\Entity;

use App\Repository\AgendamentoPetServicoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AgendamentoPetServicoRepository::class)
 */
class AgendamentoPetServico
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="agendamentoId")
     */
    private $agendamentoId;

    /**
     * @ORM\Column(type="integer", name="petId")
     */
    private $petId;

    /**
     * @ORM\Column(type="integer", name="servicoId")
     */
    private $servicoId;

    /**
     * @ORM\Column(type="integer")
     */
    private $estabelecimentoId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgendamentoId(): ?int
    {
        return $this->agendamentoId;
    }

    public function setAgendamentoId(int $agendamentoId): self
    {
        $this->agendamentoId = $agendamentoId;

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

    public function getServicoId(): ?int
    {
        return $this->servicoId;
    }

    public function setServicoId(int $servicoId): self
    {
        $this->servicoId = $servicoId;

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
