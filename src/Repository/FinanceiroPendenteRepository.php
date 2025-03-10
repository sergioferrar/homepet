<?php

namespace App\Repository;

use App\Entity\FinanceiroPendente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FinanceiroPendenteRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceiroPendente::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT f.id, 
                       CONCAT('Serviço para ', p.nome, ' - Dono: ', c.nome) AS descricao, 
                       f.valor, f.data, f.pet_id, p.nome as pet_nome, c.nome as dono_nome, f.metodo_pagamento
                FROM homepet_{$baseId}.financeiropendente f
                LEFT JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
                LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
                WHERE DATE(f.data) = :data";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function confirmarPagamento($baseId, int $id): void
    {
        // Buscar o registro pendente
        $sql = "SELECT * FROM homepet_{$baseId}.financeiropendente WHERE id = :id";
        $registroPendente = $this->conn->executeQuery($sql, ['id' => $id])->fetchAssociative();

        if (!$registroPendente) {
            throw new \Exception('Registro financeiro pendente não encontrado.');
        }

        // Inserir no Financeiro Diário
        $sqlInsert = "INSERT INTO homepet_{$baseId}.financeiro (descricao, valor, data, pet_id) 
                      VALUES (:descricao, :valor, :data, :pet_id)";
        
        $this->conn->executeQuery($sqlInsert, [
            'descricao' => $registroPendente['descricao'],
            'valor' => $registroPendente['valor'],
            'data' => $registroPendente['data'],
            'pet_id' => $registroPendente['pet_id'],
        ]);

        // Remover do Financeiropendente
        $sqlDelete = "DELETE FROM homepet_{$baseId}.Financeiropendente WHERE id = :id";
        $this->conn->executeQuery($sqlDelete, ['id' => $id]);
    }

    public function findPendenteById($baseId, int $id)
    {
        $sql = "SELECT * FROM homepet_{$baseId}.financeiropendente WHERE id = :id";
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function deletePendente($baseId, int $id): void
    {
        $sql = "DELETE FROM homepet_{$baseId}.financeiropendente WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }


    public function verificaServicoExistente($baseId, $agendamentoId){
        $sql = "SELECT id FROM homepet_{$baseId}.financeiropendente WHERE agendamento_id = $agendamentoId";
        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function savePendente($baseId, FinanceiroPendente $financeiro): void
    {
        $sql = "INSERT INTO homepet_{$baseId}.financeiropendente (descricao, valor, data, pet_id, agendamento_id) 
                VALUES (:descricao, :valor, :data, :pet_id, :agendamento_id)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('descricao', $financeiro->getDescricao());
        $stmt->bindValue('valor', $financeiro->getValor());
        $stmt->bindValue('data', $financeiro->getData()->format('Y-m-d'));
        $stmt->bindValue('pet_id', $financeiro->getPetId() ?? null);
        $stmt->bindValue('agendamento_id', $financeiro->getAgendamentoId() ?? null);
        $stmt->execute();
    }

}
