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

    public function save(Produto $produto, bool $flush = true): void
    {
        $this->_em->persist($produto);

        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Produto $produto, bool $flush = true): void
    {
        $this->_em->remove($produto);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Exemplo de busca por estabelecimento
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.estabelecimentoId = :id')
            ->setParameter('id', $estabelecimentoId)
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
