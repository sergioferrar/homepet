<?php

namespace App\Repository;

use App\Entity\Produto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProdutoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produto::class);
    }

    public function atualizarEstoque(Produto $produto, int $quantidade, string $tipo): void
    {
        $estoqueAtual = $produto->getEstoqueAtual();

        if ($tipo === 'SAIDA') {
            $novo = max(0, $estoqueAtual - $quantidade);
        } else {
            $novo = $estoqueAtual + $quantidade;
        }

        $produto->setEstoqueAtual($novo);
    }
}
