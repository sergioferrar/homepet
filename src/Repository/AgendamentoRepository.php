<?php

namespace App\Repository;

use App\Entity\Agendamento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
    private $baseId;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agendamento::class);
        $this->conn = $registry->getManager()->getConnection();
    }

    public function listaAgendamentoPorId($baseId, $idAgendamento)
    {
        $sql = "SELECT id, data, pet_id, servico_id, concluido, pronto, horaChegada, metodo_pagamento, horaSaida, taxi_dog, taxa_taxi_dog 
            FROM homepet_{$baseId}.agendamento
            WHERE id = {$idAgendamento}";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }


    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT a.id, a.data, a.concluido, a.horaChegada, a.horaSaida, 
                       a.metodo_pagamento, a.taxi_dog, a.taxa_taxi_dog, 
                       p.id AS pet_id, p.nome AS pet_nome, 
                       c.id AS dono_id, c.nome AS dono_nome, c.email, c.telefone, 
                       c.rua, c.numero, c.complemento, c.bairro, c.cidade, c.whatsapp, c.cep,
                       CONCAT(s.nome, ' - ', s.valor) as servico_nome
                FROM homepet_{$baseId}.agendamento a
                LEFT JOIN homepet_{$baseId}.agendamento_pet_servico j ON j.agendamentoId = a.id
                LEFT JOIN homepet_{$baseId}.pet p ON p.id = j.petId
                LEFT JOIN homepet_{$baseId}.cliente c ON c.id = p.dono_id
                LEFT JOIN homepet_{$baseId}.servico s ON s.id = j.servicoId
                WHERE DATE(a.data) = :data
                GROUP BY a.id
                ORDER BY a.horaChegada ASC";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }




    public function listagem($baseId, int $id)
    {
        $sql = "SELECT * FROM homepet_{$baseId}.agendamento WHERE id = :id";
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function save($baseId, Agendamento $agendamento)
    {

        $sql = "INSERT INTO homepet_{$baseId}.agendamento 
                (data, concluido, metodo_pagamento, horaChegada, horaSaida, taxi_dog, taxa_taxi_dog) 
                VALUES 
                (:data, :concluido, :metodo_pagamento, :horaChegada, :horaSaida, :taxi_dog, :taxa_taxi_dog)";

        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            //'pet_id' => $agendamento->getPetId(),
            //'servico_id' => $agendamento->getServicoId(),
            'concluido' => (int)$agendamento->isConcluido(), // CORREÇÃO
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada' => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida' => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
            'taxi_dog' => (int)$agendamento->getTaxiDog(),
            'taxa_taxi_dog' => $agendamento->getTaxaTaxiDog(),
        ]);

        return $this->conn->lastInsertId();
    }

    public function saveAgendamentoServico($baseId, $agendamentoId, $pet, $servico)
    {
        $countData = count($pet) -1;
        for($int = 0; $int <= $countData; $int++){
            $sql = "INSERT INTO homepet_{$baseId}.agendamento_pet_servico (agendamentoId, petId, servicoId) 
            VALUES ('{$agendamentoId}', '{$pet[$int]['pet_id']}', '{$servico[$int]}')";

            $this->conn->executeQuery($sql);
        }
    }

    public function update($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento 
                SET data = :data, pet_id = :pet_id, servico_id = :servico_id, concluido = :concluido, 
                    metodo_pagamento = :metodo_pagamento, horaChegada = :horaChegada, horaSaida = :horaSaida,
                    taxi_dog = :taxi_dog, taxa_taxi_dog = :taxa_taxi_dog
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'data' => $agendamento->getData()->format('Y-m-d H:i:s'),
            'pet_id' => $agendamento->getPetId(),
            'servico_id' => $agendamento->getServicoId(),
            'concluido' => (int)$agendamento->isConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada' => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida' => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
            'taxi_dog' => (int)$agendamento->getTaxiDog(),
            'taxa_taxi_dog' => $agendamento->getTaxaTaxiDog(),
            'id' => $agendamento->getId(),
        ]);
    }




    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM homepet_{$baseId}.agendamento WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' - ', c.nome) AS nome, p.especie, p.idade
                FROM homepet_{$baseId}.pet p
                LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findAllServicos($baseId): array
    {
        $sql = "SELECT id, CONCAT(nome, ' - ', valor) as nome FROM homepet_{$baseId}.servico";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function contarAgendamentosPorData($baseId, \DateTime $data): int
    {
        $sql = "SELECT COUNT(*) as total FROM homepet_{$baseId}.agendamento WHERE DATE(data) = :data";
        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        $result = $stmt->fetchAssociative();
        return (int)$result['total'];
    }

    public function findAllDonos($baseId): array
    {
        $sql = "SELECT DISTINCT c.id, c.nome 
                FROM homepet_{$baseId}.cliente c
                JOIN homepet_{$baseId}.pet p ON p.dono_id = c.id
                ORDER BY c.nome ASC";

        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }


}
