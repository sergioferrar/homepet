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
                FROM u199209817_{$baseId}.financeiropendente f
                LEFT JOIN u199209817_{$baseId}.pet p ON f.pet_id = p.id
                LEFT JOIN u199209817_{$baseId}.cliente c ON p.dono_id = c.id
                WHERE DATE(f.data) = :data";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function confirmarPagamento($baseId, int $id): void
    {
        // Buscar o registro pendente
        $sql = "SELECT * FROM u199209817_{$baseId}.financeiropendente WHERE id = :id";
        $registroPendente = $this->conn->executeQuery($sql, ['id' => $id])->fetchAssociative();

        if (!$registroPendente) {
            throw new \Exception('Registro financeiro pendente não encontrado.');
        }

        // Inserir no Financeiro Diário
        $sqlInsert = "INSERT INTO u199209817_{$baseId}.financeiro (descricao, valor, data, pet_id) 
                      VALUES (:descricao, :valor, :data, :pet_id)";
        
        $this->conn->executeQuery($sqlInsert, [
            'descricao' => $registroPendente['descricao'],
            'valor' => $registroPendente['valor'],
            'data' => $registroPendente['data'],
            'pet_id' => $registroPendente['pet_id'],
        ]);

        // Remover do Financeiropendente
<<<<<<< HEAD
        $sqlDelete = "DELETE FROM u199209817_{$baseId}.Financeiropendente WHERE id = :id";
=======
        $sqlDelete = "DELETE FROM homepet_{$baseId}.financeiropendente WHERE id = :id";
>>>>>>> ea91d6e (ajustes)
        $this->conn->executeQuery($sqlDelete, ['id' => $id]);
    }

    public function findPendenteById($baseId, int $id)
    {
        $sql = "SELECT * FROM u199209817_{$baseId}.financeiropendente WHERE id = :id";
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function deletePendente($baseId, int $id): void
    {
        $sql = "DELETE FROM u199209817_{$baseId}.financeiropendente WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

<<<<<<< HEAD

    public function verificaServicoExistente($baseId, $agendamentoId){
        $sql = "SELECT id FROM u199209817_{$baseId}.financeiropendente WHERE agendamento_id = $agendamentoId";
        $query = $this->conn->query($sql);
        return $query->fetch();
=======
    public function verificaServicoExistente($baseId, $agendamentoId)
    {
        $sql = "SELECT id FROM homepet_{$baseId}.financeiropendente WHERE agendamento_id = :agendamentoId";
        $stmt = $this->conn->executeQuery($sql, ['agendamentoId' => $agendamentoId]);
        return $stmt->fetchAssociative() !== false;
>>>>>>> ea91d6e (ajustes)
    }

    public function savePendente($baseId, FinanceiroPendente $financeiro): void
    {
<<<<<<< HEAD
        $sql = "INSERT INTO u199209817_{$baseId}.financeiropendente (descricao, valor, data, pet_id, agendamento_id) 
                VALUES (:descricao, :valor, :data, :pet_id, :agendamento_id)";
=======
        $sql = "INSERT INTO homepet_{$baseId}.financeiropendente (descricao, valor, data, pet_id, metodo_pagamento, agendamento_id) 
                VALUES (:descricao, :valor, :data, :pet_id, :metodo_pagamento, :agendamento_id)";
>>>>>>> ea91d6e (ajustes)

        $this->conn->executeQuery($sql, [
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
                FROM homepet_{$baseId}.financeiropendente 
                WHERE agendamento_id = :agendamentoId";
        
        $stmt = $this->conn->executeQuery($sql, [
            'agendamentoId' => $criteria['agendamentoId']
        ]);

        return $stmt->fetchAllAssociative();
    }

    public function removeByBaseId($baseId, $agendamentoId): void
    {
        $sql = "DELETE FROM homepet_{$baseId}.financeiropendente 
                WHERE agendamento_id = :agendamentoId";
        
        $this->conn->executeQuery($sql, [
            'agendamentoId' => $agendamentoId
        ]);
    }
}