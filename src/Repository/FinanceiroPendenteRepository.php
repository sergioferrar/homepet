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
                FROM {$_ENV['DBNAMETENANT']}.financeiropendente f
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                WHERE f.estabelecimento_id = '{$baseId}' AND  DATE(f.data) = :data";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function confirmarPagamento($baseId, int $id): void
    {
        // Buscar o registro pendente
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $registroPendente = $this->conn->executeQuery($sql, ['id' => $id])->fetchAssociative();

        if (!$registroPendente) {
            throw new \Exception('Registro financeiro pendente não encontrado.');
        }

        // Inserir no Financeiro Diário
        $sqlInsert = "INSERT INTO {$_ENV['DBNAMETENANT']}.financeiro (estabelecimento_id, descricao, valor, data, pet_id) 
                      VALUES (:estabelecimento_id, :descricao, :valor, :data, :pet_id)";
        
        $this->conn->executeQuery($sqlInsert, [
            'estabelecimento_id' => $baseId,
            'descricao' => $registroPendente['descricao'],
            'valor' => $registroPendente['valor'],
            'data' => $registroPendente['data'],
            'pet_id' => $registroPendente['pet_id'],
        ]);

        // Remover do Financeiropendente
        $sqlDelete = "DELETE FROM {$_ENV['DBNAMETENANT']}.Financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $this->conn->executeQuery($sqlDelete, ['id' => $id]);
    }

    public function findPendenteById($baseId, int $id)
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function deletePendente($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $this->conn->executeQuery($sql, ['id' => $id]);
    }


    public function verificaServicoExistente($baseId, $agendamentoId){
        $sql = "SELECT id 
            FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = $agendamentoId";

        $query = $this->conn->query($sql);
        return $query->fetch();

    }

    public function savePendente($baseId, FinanceiroPendente $financeiro): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.financeiropendente (estabelecimento_id, descricao, valor, data, pet_id, agendamento_id) 
                VALUES (:estabelecimento_id, :descricao, :valor, :data, :pet_id, :agendamento_id)";

        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'descricao' => $financeiro->getDescricao(),
            'valor' => $financeiro->getValor(),
            'data' => $financeiro->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $financeiro->getPetId() ?? null,
            'metodo_pagamento' => $financeiro->getMetodoPagamento() ?? 'pendente',
            'agendamento_id' => $financeiro->getAgendamentoId() ?? null,
        ]);
    }

    public function findByBaseId($baseId, array $criteria): array
    {
        $sql = "SELECT id, descricao, valor, data, pet_id, metodo_pagamento, agendamento_id 
                FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
                WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = :agendamentoId";
        
        $stmt = $this->conn->executeQuery($sql, [
            'agendamentoId' => $criteria['agendamentoId']
        ]);

        return $stmt->fetchAllAssociative();
    }

    public function removeByBaseId($baseId, $agendamentoId): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.financeiropendente 
                WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = :agendamentoId";
        
        $this->conn->executeQuery($sql, [
            'agendamentoId' => $agendamentoId
        ]);
    }

    public function findByClienteId(int $baseId, int $clienteId): array
    {
        $sql = "SELECT f.descricao, f.valor, f.data
                FROM u199209817_{$baseId}.financeiropendente f
                JOIN u199209817_{$baseId}.pet p ON f.pet_id = p.id
                WHERE f.estabelecimento_id = :baseId AND p.dono_id = :clienteId
                ORDER BY f.data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'clienteId' => $clienteId
        ]);
    }

    public function findAllPendentes(int $baseId): array
    {
        $sql = "SELECT f.id, f.descricao, f.valor, f.data, p.nome AS pet_nome, c.nome AS dono_nome 
            FROM {$_ENV['DBNAMETENANT']}.financeiropendente f
            LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
            LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
            WHERE f.estabelecimento_id = :baseId
            ORDER BY f.data DESC";

    return $this->conn->fetchAllAssociative($sql, [
        'baseId' => $baseId
    ]);

    }

    public function somarDebitosPendentes($baseId): float
    {
        $sql = "SELECT SUM(valor) as total
                FROM {$_ENV['DBNAMETENANT']}.financeiropendente
                WHERE estabelecimento_id = :baseId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAssociative();

        return (float) ($result['total'] ?? 0);
    }

}