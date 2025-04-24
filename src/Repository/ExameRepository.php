<?php

namespace App\Repository;

use App\Entity\Exame;
use Doctrine\DBAL\Connection;

class ExameRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAll($baseId): array
    {
        $sql = "SELECT e.*, CONCAT(p.nome, ' (', c.nome, ')') AS pet_nome
                FROM u199209817_{$baseId}.exame e
                LEFT JOIN u199209817_{$baseId}.pet p ON p.id = e.pet_id
                LEFT JOIN u199209817_{$baseId}.cliente c ON c.id = p.dono_id
                ORDER BY e.criado_em DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function insert($baseId, Exame $e): void
    {
        $sql = "INSERT INTO u199209817_{$baseId}.exame (pet_id, agendamento_id, descricao, arquivo, criado_em)
                VALUES (:pet_id, :agendamento_id, :descricao, :arquivo, :criado_em)";
        $this->conn->executeQuery($sql, [
            'pet_id'         => $e->getPetId(),
            'agendamento_id' => $e->getAgendamentoId(),
            'descricao'      => $e->getDescricao(),
            'arquivo'        => $e->getArquivo(),
            'criado_em'      => $e->getCriadoEm()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM u199209817_{$baseId}.exame WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' (', c.nome, ')') AS nome
                FROM u199209817_{$baseId}.pet p
                LEFT JOIN u199209817_{$baseId}.cliente c ON c.id = p.dono_id";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function findAllAgendamentos($baseId): array
    {
        $sql = "SELECT id, data, procedimento
                FROM u199209817_{$baseId}.agendamento_clinica
                ORDER BY data DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

}
