<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\VeterinarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=VeterinarioRepository::class)
 * @ORM\Table(name="veterinario")
 */
class Veterinario
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
    private $nome;

    /** 
     * @ORM\Column(type="string", length=255) 
     */
    private $email;

    /** 
     * @ORM\Column(type="string", length=20) 
     */
    private $telefone;

    /** 
     * @ORM\Column(type="string", length=255, nullable=true) 
     */
    private $especialidade;

    /** 
     * @ORM\Column(type="integer") 
     */
    private $estabelecimentoId;

    /** 
     * @ORM\Column(type="string", length=45, nullable=true) 
     */
    private $crmv;

    /**
     * @ORM\OneToMany(targetEntity=InternacaoExecucao::class, mappedBy="veterinario")
     */
    private $execucoes;

    public function __construct()
    {
        $this->execucoes = new ArrayCollection();
    }

    // 🔹 Getters e Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(string $telefone): self
    {
        $this->telefone = $telefone;
        return $this;
    }

    public function getEspecialidade(): ?string
    {
        return $this->especialidade;
    }

    public function setEspecialidade(?string $especialidade): self
    {
        $this->especialidade = $especialidade;
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

    public function getCrmv(): ?string
    {
        return $this->crmv;
    }

    public function setCrmv(?string $crmv): self
    {
        $this->crmv = $crmv;
        return $this;
    }

    /**
     * @return Collection<int, InternacaoExecucao>
     */
    public function getExecucoes(): Collection
    {
        return $this->execucoes;
    }

    public function addExecucao(InternacaoExecucao $execucao): self
    {
        if (!$this->execucoes->contains($execucao)) {
            $this->execucoes[] = $execucao;
            $execucao->setVeterinario($this);
        }
        return $this;
    }

    public function removeExecucao(InternacaoExecucao $execucao): self
    {
        if ($this->execucoes->removeElement($execucao)) {
            if ($execucao->getVeterinario() === $this) {
                $execucao->setVeterinario(null);
            }
        }
        return $this;
    }
}
