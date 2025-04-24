<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="estoque")
 */
class Estoque
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="string") */
    private $produto;

    /** @ORM\Column(type="integer") */
    private $quantidade;

    public function getId(): ?int { return $this->id; }
    public function getProduto(): ?string { return $this->produto; }
    public function setProduto(string $produto): self { $this->produto = $produto; return $this; }
    public function getQuantidade(): ?int { return $this->quantidade; }
    public function setQuantidade(int $quantidade): self { $this->quantidade = $quantidade; return $this; }
}
