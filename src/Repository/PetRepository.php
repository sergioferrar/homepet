<?php
namespace App\Repository;

use App\Entity\Pet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pet>
 *
 * @method Pet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pet[]    findAll()
 * @method Pet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

class PetRepository extends ServiceEntityRepository
{
    private $conn;
    private $baseId;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pet::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findPetById($baseId, $petId): array
    {
        $sql = "SELECT p.id, p.nome, p.especie, p.sexo, p.raca, p.porte, p.idade, p.observacoes, c.nome as dono_nome, c.id AS dono_id
                FROM u199209817_{$baseId}.pet p
                JOIN u199209817_{$baseId}.cliente c ON (p.dono_id = c.id)
                WHERE p.id ={$petId}";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAssociative();
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' - ', c.nome) AS nome, p.especie, p.sexo, p.raca, p.porte, p.idade, p.observacoes, c.nome as dono_nome
                FROM u199209817_{$baseId}.pet p
                JOIN u199209817_{$baseId}.cliente c ON (p.dono_id = c.id)";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function save($baseId, Pet $pet): void
    {
        $sql = "INSERT INTO u199209817_{$baseId}.pet (nome, especie, sexo, raca, porte, idade, observacoes, dono_id) 
                VALUES (:nome, :especie, :sexo, :raca, :porte, :idade, :observacoes, :dono_id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $pet->getNome());
        $stmt->bindValue('especie', $pet->getEspecie());
        $stmt->bindValue('sexo', $pet->getSexo());
        $stmt->bindValue('raca', $pet->getRaca());
        $stmt->bindValue('porte', $pet->getPorte());
        $stmt->bindValue('idade', $pet->getIdade());
        $stmt->bindValue('observacoes', $pet->getObservacoes());
        $stmt->bindValue('dono_id', $pet->getDono_Id());
        $stmt->execute();
    }

    public function update($baseId, Pet $pet): void
    {
        $sql = "UPDATE u199209817_{$baseId}.pet SET nome = :nome, especie = :especie, sexo = :sexo, raca = :raca, porte = :porte, 
                idade = :idade, observacoes = :observacoes, dono_id = :dono_id WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $pet->getNome());
        $stmt->bindValue('especie', $pet->getEspecie());
        $stmt->bindValue('sexo', $pet->getSexo());
        $stmt->bindValue('raca', $pet->getRaca());
        $stmt->bindValue('porte', $pet->getPorte());
        $stmt->bindValue('idade', $pet->getIdade());
        $stmt->bindValue('observacoes', $pet->getObservacoes());
        $stmt->bindValue('dono_id', $pet->getDono_Id());
        $stmt->bindValue('id', $pet->getId());
        $stmt->execute();
    }


    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM u199209817_{$baseId}.pet WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->execute();
    }
}
