<?php
namespace App\Entity;

class Receita
{
    private $id;
    private $estabelecimento_id;
    private $pet_id;
    private $data;
    private $cabecalho;
    private $conteudo;
    private $rodape;
    private $criado_em;

    public function getId() { return $this->id; }

    public function getEstabelecimentoId() { return $this->estabelecimento_id; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimento_id = $id; return $this; }

    public function getPetId() { return $this->pet_id; }
    public function setPetId(int $id): self { $this->pet_id = $id; return $this; }

    public function getData(): ?\DateTimeInterface { return $this->data; }
    public function setData(\DateTimeInterface $data): self { $this->data = $data; return $this; }

    public function getCabecalho(): ?string { return $this->cabecalho; }
    public function setCabecalho(?string $c): self { $this->cabecalho = $c; return $this; }

    public function getConteudo(): ?string { return $this->conteudo; }
    public function setConteudo(?string $c): self { $this->conteudo = $c; return $this; }

    public function getRodape(): ?string { return $this->rodape; }
    public function setRodape(?string $r): self { $this->rodape = $r; return $this; }

    public function getCriadoEm(): ?\DateTimeInterface { return $this->criado_em; }
    public function setCriadoEm(\DateTimeInterface $dt): self { $this->criado_em = $dt; return $this; }
}
