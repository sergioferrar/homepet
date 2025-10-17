<?php

namespace App\Entity;

use App\Repository\DocumentoModeloRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DocumentoModeloRepository::class)
 */
class DocumentoModelo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $titulo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $tipo = null; // ✅ novo campo

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $conteudo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $cabecalho = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $rodape = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?\DateTime $criado_em = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getConteudo(): string
    {
        return $this->conteudo;
    }

    public function setConteudo(string $conteudo): self
    {
        $this->conteudo = $conteudo;
        return $this;
    }

    public function getCabecalho(): ?string
    {
        return $this->cabecalho;
    }

    public function setCabecalho(?string $cabecalho): self
    {
        $this->cabecalho = $cabecalho;
        return $this;
    }

    public function getRodape(): ?string
    {
        return $this->rodape;
    }

    public function setRodape(?string $rodape): self
    {
        $this->rodape = $rodape;
        return $this;
    }

    public function getCriadoEm(): ?\DateTime
    {
        return $this->criado_em;
    }

    public function setCriadoEm(\DateTime $criado_em): self
    {
        $this->criado_em = $criado_em;
        return $this;
    }
}
