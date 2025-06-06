<?php

namespace App\Entity;

class DocumentoModelo
{
    private ?int $id = null;
    private string $titulo;
    private string $conteudo;
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

    public function getConteudo(): string
    {
        return $this->conteudo;
    }

    public function setConteudo(string $conteudo): self
    {
        $this->conteudo = $conteudo;
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
