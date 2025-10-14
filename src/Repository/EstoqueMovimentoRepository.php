<?php

namespace App\Repository;

use App\Entity\EstoqueMovimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EstoqueMovimentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EstoqueMovimento::class);
    }

    public function registrarMovimento($produto, int $quantidade, string $tipo, string $origem, int $estabelecimentoId): EstoqueMovimento
    {
        $mov = new EstoqueMovimento();
        $mov->setProduto($produto);
        $mov->setQuantidade($quantidade);
        $mov->setTipo($tipo);
        $mov->setOrigem($origem);
        $mov->setEstabelecimentoId($estabelecimentoId);
        $mov->setData(new \DateTime());

        $this->_em->persist($mov);
        return $mov;
    }
}
