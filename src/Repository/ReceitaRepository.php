<?php

namespace App\Repository;

use App\Entity\Receita;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReceitaRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Receita::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function listarPorPet($baseId, int $petId): array
    {
        $sql = "SELECT id, data, resumo, cabecalho, conteudo, rodape
                FROM {$_ENV['DBNAMETENANT']}.receita
                WHERE estabelecimento_id = :baseId AND pet_id = :petId
                ORDER BY data DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function salvar(Receita $receita): void
    {
        $em = $this->getEntityManager();
        $em->persist($receita);
        $em->flush();
    }
}
