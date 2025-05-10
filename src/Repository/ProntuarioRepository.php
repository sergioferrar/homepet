<?php

namespace App\Repository;

use App\Entity\Prontuario;
use Doctrine\DBAL\Connection;

class ProntuarioRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function save($baseId, Prontuario $p): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.prontuario (agendamento_id, observacoes, arquivos, criado_em)
                VALUES (:agendamento_id, :observacoes, :arquivos, :criado_em)";
        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'agendamento_id' => $p->getAgendamentoId(),
            'observacoes' => $p->getObservacoes(),
            'arquivos' => $p->getArquivos(),
            'criado_em' => $p->getCriadoEm()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByAgendamento($baseId, int $agendamentoId): array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.prontuario 
            WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = :id 
            ORDER BY criado_em DESC";
            
        return $this->conn->fetchAllAssociative($sql, ['id' => $agendamentoId]);
    }
}
