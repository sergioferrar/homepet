<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceitaRepository")
 * @ORM\Table(name="receita")
 */
class Receita
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="integer") */
    private $estabelecimento_id;

    /** @ORM\Column(type="integer") */
    private $pet_id;

    /** @ORM\Column(type="date") */
    private $data;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $resumo;

    /** @ORM\Column(type="text", nullable=true) */
    private $cabecalho;

    /** @ORM\Column(type="text", nullable=true) */
    private $conteudo;

    /** @ORM\Column(type="text", nullable=true) */
    private $rodape;

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimento_id; }
    public function setEstabelecimentoId(int $estabelecimento_id): self
    {
        $this->estabelecimento_id = $estabelecimento_id;
        return $this;
    }

    public function getPetId(): ?int { return $this->pet_id; }
    public function setPetId(int $pet_id): self
    {
        $this->pet_id = $pet_id;
        return $this;
    }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getResumo(): ?string { return $this->resumo; }
    public function setResumo(?string $resumo): self
    {
        $this->resumo = $resumo;
        return $this;
    }

    public function getCabecalho(): ?string { return $this->cabecalho; }
    public function setCabecalho(?string $cabecalho): self
    {
        $this->cabecalho = $cabecalho;
        return $this;
    }

    public function getConteudo(): ?string { return $this->conteudo; }
    public function setConteudo(?string $conteudo): self
    {
        $this->conteudo = $conteudo;
        return $this;
    }

    public function getRodape(): ?string { return $this->rodape; }
    public function setRodape(?string $rodape): self
    {
        $this->rodape = $rodape;
        return $this;
    }
}
