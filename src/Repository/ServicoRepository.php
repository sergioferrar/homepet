<?php
namespace App\Repository;

use App\Entity\Servico;
use Doctrine\DBAL\Connection;

class ServicoRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM servico'; // Tabela com 's' minúsculo
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function find(int $id): ?Servico
    {
        $sql = 'SELECT * FROM servico WHERE id = :id'; // Tabela com 's' minúsculo
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        $servicoData = $stmt->fetchAssociative();

        if (!$servicoData) {
            return null;
        }

        $servico = new Servico();
        $servico->setId($servicoData['id']);
        $servico->setNome($servicoData['nome']);
        $servico->setDescricao($servicoData['descricao']);
        $servico->setValor($servicoData['valor']);

        return $servico;
    }

    public function save(Servico $servico): void
    {
        $sql = 'INSERT INTO servico (nome, descricao, valor) VALUES (:nome, :descricao, :valor)'; // Tabela com 's' minúsculo
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $servico->getNome());
        $stmt->bindValue('descricao', $servico->getDescricao());
        $stmt->bindValue('valor', $servico->getValor());
        $stmt->execute();
    }

    public function update(Servico $servico): void
    {
        $sql = 'UPDATE servico SET nome = :nome, descricao = :descricao, valor = :valor WHERE id = :id'; // Tabela com 's' minúsculo
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $servico->getNome());
        $stmt->bindValue('descricao', $servico->getDescricao());
        $stmt->bindValue('valor', $servico->getValor());
        $stmt->bindValue('id', $servico->getId());
        $stmt->execute();
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM servico WHERE id = :id'; // Tabela com 's' minúsculo
        $this->conn->executeQuery($sql, ['id' => $id]);
    }
}
