<?php

namespace App\Entity;

use App\Repository\PlanoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PlanoRepository::class)
 * @ORM\Table(name="planos")
 */
class Plano
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
    private $titulo;

    /**
     * @ORM\Column(type="text")
     */
    private $descricao;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $valor;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="integer")
     */
    private $trial;

    /**
     * @ORM\Column(type="datetime", name="dataTrial")
     */
    private $dataTrial;

    /**
     * @ORM\Column(type="datetime", name="dataPlano")
     */
    private $dataPlano;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $modulos;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;

        return $this;
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

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(string $valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTrial(): ?int
    {
        return $this->trial;
    }

    public function setTrial(int $trial): self
    {
        $this->trial = $trial;

        return $this;
    }

    public function getDataTrial(): ?\DateTimeInterface
    {
        return $this->dataTrial;
    }

    public function setDataTrial(\DateTimeInterface $dataTrial): self
    {
        $this->dataTrial = $dataTrial;

        return $this;
    }

    public function getDataPlano(): ?\DateTimeInterface
    {
        return $this->dataPlano;
    }

    public function setDataPlano(\DateTimeInterface $dataPlano): self
    {
        $this->dataPlano = $dataPlano;

        return $this;
    }

    public function getModulos(): ?string
    {
        return $this->modulos;
    }

    public function setModulos(?string $modulos): self
    {
        $this->modulos = $modulos;

        return $this;
    }
}
