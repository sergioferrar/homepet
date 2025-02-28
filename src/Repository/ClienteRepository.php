<?php
namespace App\Repository;

use App\Entity\Cliente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Clientes>
 *
 * @method Cliente|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cliente|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cliente[]    findAll()
 * @method Cliente[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

class ClienteRepository extends ServiceEntityRepository
{
    private $conn;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cliente::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findAgendamentosByCliente(int $clienteId): array
    {
        $sql = 'SELECT a.id, a.data, p.nome as pet_nome, s.nome as servico_nome
                FROM Agendamento a
                JOIN Pet p ON a.pet_id = p.id
                JOIN servico s ON a.servico_id = s.id
                WHERE p.dono_id = :clienteId';
        $stmt = $this->conn->executeQuery($sql, ['clienteId' => $clienteId]);
        return $stmt->fetchAllAssociative();
    }

    public function save(array $clienteData): void
    {
        $sql = 'INSERT INTO Cliente (nome, email, telefone, rua, numero, complemento, bairro, cidade) VALUES (:nome, :email, :telefone, :rua, :numero, :complemento, :bairro, :cidade)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($clienteData);
    }

    public function update(array $clienteData): void
    {
        $sql = "UPDATE Cliente 
                SET nome = :nome, email = :email, telefone = :telefone, 
                    rua = :rua, 
                    numero = :numero, 
                    complemento = :complemento, 
                    bairro = :bairro, 
                    cidade = :cidade
               WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($clienteData);
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM Cliente WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function search(string $term): array
    {
        $sql = 'SELECT * FROM Cliente WHERE nome LIKE :term OR email LIKE :term OR telefone LIKE :term OR Endereco LIKE :term';
        $stmt = $this->conn->executeQuery($sql, ['term' => '%' . $term . '%']);
        return $stmt->fetchAllAssociative();
    }

    public function getLastInsertedId(): int
    {
        return $this->conn->lastInsertId();
    }

}
