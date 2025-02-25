<?php

namespace App\Repository;

use App\Entity\Agendamento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends ServiceEntityRepository<Agendamento>
 *
 * @method Agendamento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Agendamento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Agendamento[]    findAll()
 * @method Agendamento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgendamentoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agendamento::class);
        $this->conn = $registry->getManager()->getConnection();
    }

    public function findByDate(\DateTime $data): array
    {
        $sql = 'SELECT a.id, a.data, a.concluido, a.horaChegada, a.horaSaida, a.metodo_pagamento, 
                       p.nome as pet_nome, 
                       c.nome as dono_nome, 
                       CONCAT(s.nome, " - ", s.valor) as servico_nome
                FROM Agendamento a
                JOIN Pet p ON a.pet_id = p.id
                JOIN Cliente c ON p.dono_id = c.id
                JOIN servico s ON a.servico_id = s.id
                WHERE DATE(a.data) = :data';

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }


    public function listagem(int $id)
    {
        $sql = 'SELECT * FROM Agendamento WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function save(Agendamento $agendamento): void
    {
        $sql = 'INSERT INTO Agendamento (data, pet_id, servico_id, concluido, metodo_pagamento, horaChegada, horaSaida) 
                VALUES (:data, :pet_id, :servico_id, :concluido, :metodo_pagamento, :horaChegada, :horaSaida)';
        
        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $agendamento->getPet_Id(),
            'servico_id' => $agendamento->getServico_Id(),
            'concluido' => (int)$agendamento->getConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada' => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida' => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
        ]);
    }

    public function update(Agendamento $agendamento): void
    {
        $sql = 'UPDATE Agendamento 
                SET data = :data, pet_id = :pet_id, servico_id = :servico_id, concluido = :concluido, 
                    metodo_pagamento = :metodo_pagamento, horaChegada = :horaChegada, horaSaida = :horaSaida
                WHERE id = :id';

        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $agendamento->getPet_Id(),
            'servico_id' => $agendamento->getServico_Id(),
            'concluido' => (int)$agendamento->getConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada' => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida' => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
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
        $sql = "SELECT p.id, CONCAT(p.nome, ' - ', c.nome) AS nome, p.especie, p.idade
                FROM Pet p
                LEFT JOIN Cliente c ON p.dono_id = c.id";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findAllServicos(): array
    {
        $sql = 'SELECT id, CONCAT(nome, " - ", valor) as nome FROM servico';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function contarAgendamentosPorData(\DateTime $data): int
    {
        $sql = 'SELECT COUNT(*) as total FROM Agendamento WHERE DATE(data) = :data';
        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        $result = $stmt->fetchAssociative();
        return (int)$result['total'];
    }
}
