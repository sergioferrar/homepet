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
                FROM {$_ENV['DBNAMETENANT']}.exame e
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.id = e.pet_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE e.estabelecimento_id = '{$baseId}'
                ORDER BY e.criado_em DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function insert($baseId, Exame $e): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.exame (estabelecimento_id, pet_id, agendamento_id, descricao, arquivo, criado_em)
                VALUES (:estabelecimento_id, :pet_id, :agendamento_id, :descricao, :arquivo, :criado_em)";
        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'pet_id' => $e->getPetId(),
            'agendamento_id' => $e->getAgendamentoId(),
            'descricao' => $e->getDescricao(),
            'arquivo' => $e->getArquivo(),
            'criado_em' => $e->getCriadoEm()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}{$baseId}.exame 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' (', c.nome, ')') AS nome
                FROM {$_ENV['DBNAMETENANT']}.pet p
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE p.estabelecimento_id = '{$baseId}'";

        return $this->conn->fetchAllAssociative($sql);
    }

    public function findAllAgendamentos($baseId): array
    {
        $sql = "SELECT id, data, procedimento
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_clinica
                WHERE estabelecimento_id = '{$baseId}'
                ORDER BY data DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

}
