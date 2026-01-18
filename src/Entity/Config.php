<?php

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConfigRepository::class)
 */
class Config
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
    private $estabelecimento_id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $chave;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $valor;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $tipo;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $observacao;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstabelecimentoId(): ?int
    {
        return $this->estabelecimento_id;
    }

    public function setEstabelecimentoId(int $estabelecimento_id): self
    {
        $this->estabelecimento_id = $estabelecimento_id;

        return $this;
    }

    public function getChave(): ?string
    {
        return $this->chave;
    }

    public function setChave(string $chave): self
    {
        $this->chave = $chave;

        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(?string $valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getObservacao(): ?string
    {
        return $this->observacao;
    }

    public function setObservacao(?string $observacao): self
    {
        $this->observacao = $observacao;

        return $this;
    }
}
