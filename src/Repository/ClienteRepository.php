<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

class ClienteRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM Cliente';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function find(int $id): ?array
    {
        $sql = 'SELECT * FROM Cliente WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
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
        $sql = 'INSERT INTO Cliente (nome, email, telefone, Endereco) VALUES (:nome, :email, :telefone, :Endereco)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($clienteData);
    }

    public function update(array $clienteData): void
    {
        $sql = 'UPDATE Cliente SET nome = :nome, email = :email, telefone = :telefone, Endereco = :Endereco WHERE id = :id';
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
}
