<?php

namespace App\Repository;

use App\Entity\InternacaoExecucao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InternacaoExecucaoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InternacaoExecucao::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

}