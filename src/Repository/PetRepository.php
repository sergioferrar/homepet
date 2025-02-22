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

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pet::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findAllPets(): array
    {
        $sql = 'SELECT p.id, p.nome, p.especie, p.sexo, p.raca, p.porte, p.idade, p.observacoes, c.nome as dono_nome
                FROM Pet p
                JOIN Cliente c ON p.dono_id = c.id';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function save(Pet $pet): void
    {
        $sql = 'INSERT INTO Pet (nome, especie, sexo, raca, porte, idade, observacoes, dono_id) 
                VALUES (:nome, :especie, :sexo, :raca, :porte, :idade, :observacoes, :dono_id)';
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

    public function update(Pet $pet): void
    {
        $sql = 'UPDATE Pet SET nome = :nome, especie = :especie, sexo = :sexo, raca = :raca, porte = :porte, 
                idade = :idade, observacoes = :observacoes, dono_id = :dono_id WHERE id = :id';
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


    public function delete(int $id): void
    {
        $sql = 'DELETE FROM Pet WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->execute();
    }
}
