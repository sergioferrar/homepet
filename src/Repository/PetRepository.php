<?php
namespace App\Repository;

use App\Entity\Pet;
use Doctrine\DBAL\Connection;

class PetRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAllPets(): array
    {
        // Certifique-se de especificar 'p.id' ao invés de apenas 'id' para evitar ambiguidade
        $sql = 'SELECT p.id, p.nome, p.tipo, p.idade, c.nome as dono_nome
                FROM Pet p
                JOIN Cliente c ON p.dono_id = c.id';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function find(int $id): ?Pet
    {
        $sql = 'SELECT p.id, p.nome, p.tipo, p.idade, p.dono_id, 
            c.nome AS cliente
            FROM Pet p
            LEFT JOIN Cliente c ON c.id = p.dono_id
            WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        $petData = $stmt->fetchAssociative();

        if (!$petData) {
            return null;
        }

        $pet = new Pet();
        $pet->setId($petData['id'])
            ->setNome($petData['nome'])
            ->setTipo($petData['tipo'])
            ->setIdade($petData['idade'])
            ->setDono_Id($petData['dono_id']);

        return $pet;
    }

    public function save(Pet $pet): void
    {
        $sql = 'INSERT INTO Pet (nome, tipo, idade, dono_id) VALUES (:nome, :tipo, :idade, :dono_id)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $pet->getNome());
        $stmt->bindValue('tipo', $pet->getTipo());
        $stmt->bindValue('idade', $pet->getIdade());
        $stmt->bindValue('dono_id', $pet->getDono_Id());
        $stmt->execute();
    }

    public function update(Pet $pet): void
    {
        $sql = 'UPDATE Pet SET nome = :nome, tipo = :tipo, idade = :idade, dono_id = :dono_id WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $pet->getNome());
        $stmt->bindValue('tipo', $pet->getTipo());
        $stmt->bindValue('idade', $pet->getIdade());
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
