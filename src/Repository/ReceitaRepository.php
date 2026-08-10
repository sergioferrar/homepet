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
                FROM homepet_{$baseId}.receita
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

    /**
     * Busca uma receita pelo ID, com o HTML de cabeçalho/rodapé e o Delta do
     * conteúdo já gravados na emissão. Usado para reimprimir a receita em PDF
     * sem precisar remontar o papel timbrado.
     */
    public function findById($baseId, int $receitaId): ?array
    {
        $sql = "SELECT r.id, r.pet_id, r.data, r.resumo, r.cabecalho, r.conteudo, r.rodape,
                       p.nome AS pet_nome
                FROM homepet_{$baseId}.receita r
                LEFT JOIN homepet_{$baseId}.pet p ON p.id = r.pet_id
                WHERE r.estabelecimento_id = :baseId AND r.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $receitaId);

        $row = $stmt->executeQuery()->fetchAssociative();

        return $row ?: null;
    }

}
