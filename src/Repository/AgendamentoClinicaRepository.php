<?php

namespace App\Repository;

use App\Entity\AgendamentoClinica;
use Doctrine\DBAL\Connection;

class AgendamentoClinicaRepository
{
    private $conn;
    public function __construct(Connection $conn) { $this->conn = $conn; }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_clinica WHERE DATE(data) = :data ORDER BY hora ASC";
        return $this->conn->fetchAllAssociative($sql, ['data' => $data->format('Y-m-d')]);
    }

    public function save($baseId, AgendamentoClinica $a): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_clinica (data, hora, procedimento, status, pet_id, dono_id)
                VALUES (:data, :hora, :procedimento, :status, :pet_id, :dono_id)";
        $this->conn->executeQuery($sql, [
            'data' => $a->getData()->format('Y-m-d'),
            'hora' => $a->getHora()->format('H:i:s'),
            'procedimento' => $a->getProcedimento(),
            'status' => $a->getStatus(),
            'pet_id' => $a->getPetId(),
            'dono_id' => $a->getDonoId(),
        ]);
    }

    public function findAllClientes($baseId): array
    {
        return $this->conn->fetchAllAssociative("SELECT id, nome FROM {$_ENV['DBNAMETENANT']}{$baseId}.cliente");
    }

    public function findAllPets($baseId): array
    {
        return $this->conn->fetchAllAssociative("SELECT id, nome FROM {$_ENV['DBNAMETENANT']}{$baseId}.pet");
    }
}
