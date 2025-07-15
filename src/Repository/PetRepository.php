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
                FROM {$_ENV['DBNAMETENANT']}.pet p
                JOIN {$_ENV['DBNAMETENANT']}.cliente c ON (p.dono_id = c.id)
                WHERE p.estabelecimento_id = '{$baseId}' AND p.id = {$petId}";

        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAssociative();
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' - ', c.nome) AS nome, p.especie, p.sexo, p.raca, p.porte, p.idade, p.observacoes, c.nome as dono_nome
                FROM {$_ENV['DBNAMETENANT']}.pet p
                JOIN {$_ENV['DBNAMETENANT']}.cliente c ON (p.dono_id = c.id)
                WHERE p.estabelecimento_id = '{$baseId}'";

        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function save($baseId, Pet $pet): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.pet (estabelecimento_id, nome, especie, sexo, raca, porte, idade, observacoes, dono_id) 
                VALUES (:estabelecimento_id, :nome, :especie, :sexo, :raca, :porte, :idade, :observacoes, :dono_id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
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
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.pet SET nome = :nome, especie = :especie, sexo = :sexo, raca = :raca, porte = :porte, 
                idade = :idade, observacoes = :observacoes, dono_id = :dono_id WHERE estabelecimento_id = '{$baseId}' AND id = :id";
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
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.pet WHERE estabelecimento_id = '{$baseId}' AND id = :id";
        
        
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue('id', $id);
        $stmt->execute();
    }
    public function countTotalPets($baseId): int
    {
        $sql = "SELECT COUNT(*) FROM {$_ENV['DBNAMETENANT']}.pet WHERE estabelecimento_id = :baseId";
        return (int) $this->conn->fetchOne($sql, ['baseId' => $baseId]);
    }

    public function listarPetsRecentes($baseId, $limit = 5): array
    {
        $sql = "SELECT p.id, p.nome, p.especie, p.raca, c.nome as tutor
                FROM {$_ENV['DBNAMETENANT']}.pet p
                JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                WHERE p.estabelecimento_id = :baseId
                ORDER BY p.id DESC
                LIMIT :limit";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }
    
    public function contarPetsPorEspecie($baseId): array
    {
        $sql = "SELECT especie, COUNT(*) as total
                FROM {$_ENV['DBNAMETENANT']}.pet
                WHERE estabelecimento_id = :baseId
                GROUP BY especie";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAllAssociative();

        $dados = [];
        foreach ($result as $row) {
            $dados[$row['especie'] ?? 'Não informado'] = (int)$row['total'];
        }

        return $dados;
    }

    public function buscarPetsPorCliente($baseId, $clienteId): array
    {
        $sql = "SELECT id, nome 
                FROM {$_ENV['DBNAMETENANT']}.pet
                WHERE estabelecimento_id = :baseId AND dono_id = :clienteId
                ORDER BY nome";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('clienteId', $clienteId);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function listarVacinasPendentes($baseId): array
    {
        $sql = "SELECT v.id, v.pet_id, v.tipo, v.data_aplicacao, v.data_validade,
                       p.nome as pet_nome, c.nome as tutor
                FROM {$_ENV['DBNAMETENANT']}.vacina v
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON v.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                WHERE v.data_validade < CURDATE()
                  AND p.estabelecimento_id = :baseId
                ORDER BY v.data_validade ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }


    public function listarPetsInternados($baseId): array
    {
        $sql = "SELECT p.id, p.nome, p.raca, p.especie, c.nome as tutor
                FROM {$_ENV['DBNAMETENANT']}.pet p
                JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.cliente_id = c.id
                WHERE p.internado = 1
                  AND p.estabelecimento_id = :baseId
                ORDER BY p.nome";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

}
