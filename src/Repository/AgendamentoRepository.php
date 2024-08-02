<?php
namespace App\Repository;

use App\Entity\Agendamento;
use Doctrine\DBAL\Connection;

class AgendamentoRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAll(): array
    {
        $sql = 'SELECT a.id, a.data, a.concluido, a.hora_chegada, p.nome as pet_nome, c.nome as dono_nome, s.nome as servico_nome
                FROM Agendamento a
                JOIN Pet p ON a.pet_id = p.id
                JOIN Cliente c ON p.dono_id = c.id
                JOIN servico s ON a.servico_id = s.id';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findByDate(\DateTime $data): array
    {
        $sql = 'SELECT a.id, a.data, a.concluido, p.nome as pet_nome, c.nome as dono_nome, s.nome as servico_nome
                FROM Agendamento a
                JOIN Pet p ON a.pet_id = p.id
                JOIN Cliente c ON p.dono_id = c.id
                JOIN servico s ON a.servico_id = s.id
                WHERE DATE(a.data) = :data';
        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function find(int $id): ?Agendamento
    {
        $sql = 'SELECT * FROM Agendamento WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        $agendamentoData = $stmt->fetchAssociative();

        if (!$agendamentoData) {
            return null;
        }

        $agendamento = new Agendamento();
        $agendamento->setId($agendamentoData['id']);
        $agendamento->setData(new \DateTime($agendamentoData['data']));
        $agendamento->setPet_Id($agendamentoData['pet_id']);
        $agendamento->setServico_Id($agendamentoData['servico_id']);
        $agendamento->setConcluido((bool)$agendamentoData['concluido']);

        return $agendamento;
    }

    public function save(Agendamento $agendamento): void
    {
        $sql = 'INSERT INTO Agendamento (data, pet_id, servico_id, concluido) VALUES (:data, :pet_id, :servico_id, :concluido)';
        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $agendamento->getPet_Id(),
            'servico_id' => $agendamento->getServico_Id(),
            'concluido' => (int)$agendamento->getConcluido(),
        ]);
    }

    public function update(Agendamento $agendamento): void
    {
        $sql = 'UPDATE Agendamento SET data = :data, pet_id = :pet_id, servico_id = :servico_id, concluido = :concluido WHERE id = :id';
        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $agendamento->getPet_Id(),
            'servico_id' => $agendamento->getServico_Id(),
            'concluido' => (int)$agendamento->getConcluido(),
            'id' => $agendamento->getId(),
        ]);
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM Agendamento WHERE id = :id';
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function findAllPets(): array
    {
        $sql = 'SELECT * FROM Pet';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findAllServicos(): array
    {
        $sql = 'SELECT * FROM servico';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function marcarComoConcluido(int $id): void
    {
        $sql = 'UPDATE Agendamento SET concluido = 1 WHERE id = :id';
        $this->conn->executeQuery($sql, ['id' => $id]);
    }


    public function marcarComoPronto($id)
    {
        $agendamento = $this->find($id);
        if ($agendamento) {
            $agendamento->setPronto(true);
            $this->_em->persist($agendamento);
            $this->_em->flush();
        }
    }

    public function contarAgendamentosPorData(\DateTime $data): int
    {
        $sql = 'SELECT COUNT(*) as total FROM Agendamento WHERE DATE(data) = :data';
        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        $result = $stmt->fetchAssociative();
        return (int)$result['total'];
    }
}
