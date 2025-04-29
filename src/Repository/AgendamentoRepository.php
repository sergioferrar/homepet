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

    public function listaAgendamentoPorId($baseId, $id)
    {
        $sql = "SELECT a.id, data, concluido, pronto, horaChegada, metodo_pagamento, horaSaida, horaChegada, taxi_dog, taxa_taxi_dog
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento a
                LEFT JOIN {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico aps ON a.id = aps.agendamentoId
                WHERE a.id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        $result = $stmt->fetchAssociative();

        if ($result) {
            $result['taxa_taxi_dog'] = $result['taxa_taxi_dog'] !== null ? (float) $result['taxa_taxi_dog'] : 0.0;
            $result['concluido'] = (bool) $result['concluido'];
            $result['pronto'] = (bool) $result['pronto'];
            $result['taxi_dog'] = (bool) $result['taxi_dog'];
        }

        return $result;
    }


    public function listaApsPorId($baseId, $idAgendamento)
    {
        $sql = "SELECT aps.id, agendamentoId, petId, servicoId, p.nome AS pet_nome, c.nome AS cliente_nome, s.nome AS servico_nome, s.valor
            FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico aps
            JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON (p.id = aps.petId)
            JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON (c.id = p.dono_id)
            JOIN {$_ENV['DBNAMETENANT']}{$baseId}.servico s ON (s.id = aps.servicoId)
            WHERE agendamentoId = {$idAgendamento}";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT 
                    a.id AS id,
                    a.data,
                    a.horaChegada,
                    a.horaSaida,
                    a.status,
                    a.concluido,
                    a.metodo_pagamento,
                    a.taxi_dog,
                    a.taxa_taxi_dog,
                    p.nome AS pet_nome,
                    p.id AS pet_id,
                    c.id AS dono_id,
                    c.nome AS dono_nome,
                    c.email, c.telefone, c.rua, c.numero, c.complemento, 
                    c.bairro, c.cidade, c.whatsapp, c.cep,
                    GROUP_CONCAT(CONCAT(s.nome, ' (R$ ', s.valor, ')') ORDER BY s.nome SEPARATOR ', ') AS servico_nome

                FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento a
                INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico aps ON aps.agendamentoId = a.id
                INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON aps.petId = p.id
                INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON p.dono_id = c.id
                INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.servico s ON aps.servicoId = s.id
                WHERE DATE(a.data) = :data
                GROUP BY a.id, p.id
                ORDER BY a.horaChegada ASC, c.nome, p.nome";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);

        return $stmt->fetchAllAssociative();
    }




    public function listagem($baseId, int $id)
    {
        $sql  = "SELECT * FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento WHERE id = :id";
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function save($baseId, Agendamento $agendamento)
    {

        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                (data, concluido, metodo_pagamento, horaChegada, horaSaida, taxi_dog, taxa_taxi_dog, status)
                VALUES
                (:data, :concluido, :metodo_pagamento, :horaChegada, :horaSaida, :taxi_dog, :taxa_taxi_dog, :status)";



        $this->conn->executeQuery($sql, [
            'data'             => $agendamento->getData()->format('Y-m-d H:i:s'),
            'status'          => $agendamento->getStatus(),
            'concluido'        => (int) $agendamento->isConcluido(), // CORREÇÃO
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada'      => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida'        => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
            'taxi_dog'         => (int) $agendamento->getTaxiDog(),
            'taxa_taxi_dog'    => $agendamento->getTaxaTaxiDog(),
        ]);

        return $this->conn->lastInsertId();
    }

    public function saveAgendamentoServico($baseId, \App\Entity\AgendamentoPetServico $agendamentoIdPetServico)
    {

        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico (agendamentoId, petId, servicoId)
            VALUES ('{$agendamentoIdPetServico->getAgendamentoId()}', '{$agendamentoIdPetServico->getPetId()}', '{$agendamentoIdPetServico->getServicoId()}')";

        $this->conn->executeQuery($sql);

    }

    public function update($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                SET data = :data, 
                    concluido = :concluido,
                    metodo_pagamento = :metodo_pagamento, 
                    horaChegada = :horaChegada, 
                    horaSaida = :horaSaida,
                    taxi_dog = :taxi_dog, 
                    taxa_taxi_dog = :taxa_taxi_dog,
                    status = :status
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'data'             => $agendamento->getData()->format('Y-m-d H:i:s'),
            'concluido'        => (int) $agendamento->isConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada'      => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida'        => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
            'taxi_dog'         => (int) $agendamento->getTaxiDog(),
            'taxa_taxi_dog'    => $agendamento->getTaxaTaxiDog(),
            'status'           => $agendamento->getStatus(),
            'id'               => $agendamento->getId(),
        ]);
    }


    public function updateConcluido($baseId, $idAgendamento): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                SET concluido = 1
                WHERE id = $idAgendamento";

        $this->conn->executeQuery($sql);
    }   

    public function updatePagamento($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                SET metodo_pagamento = :metodo_pagamento
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'id' => $agendamento->getId(),
        ]);
    }   

    public function updateSaida($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                SET horaSaida = :horaSaida
                WHERE id = :id";
        $this->conn->executeQuery($sql, [
            'horaSaida' => $agendamento->getHoraSaida()->format('Y-m-d H:i:s'),
            'id' => $agendamento->getId(),
        ]);
    }    

    public function updateAgendamento($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento
                SET 
                    data = :data,
                    horaChegada = :horaChegada,
                    concluido = :concluido,
                    metodo_pagamento = :metodo_pagamento,
                    taxi_dog = :taxi_dog,
                    taxa_taxi_dog = :taxa_taxi_dog,
                    status = :status
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'data'             => $agendamento->getData()->format('Y-m-d H:i:s'),
            'horaChegada'      => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'concluido'        => (int) $agendamento->isConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'taxi_dog'         => (int) $agendamento->getTaxiDog(),
            'taxa_taxi_dog'    => $agendamento->getTaxaTaxiDog(),
            'status'           => $agendamento->getStatus(),
            'id'               => $agendamento->getId(),
        ]);
    }


    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        $sql = "SELECT p.id, CONCAT(p.nome, ' - ', c.nome) AS nome, p.especie, p.idade
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.pet p
                LEFT JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON p.dono_id = c.id";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findAllServicos($baseId): array
    {
        $sql  = "SELECT id, CONCAT(nome, ' - ', valor) as nome FROM {$_ENV['DBNAMETENANT']}{$baseId}.servico";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function contarAgendamentosPorData($baseId, \DateTime $data): int
    {
        $sql    = "SELECT COUNT(*) as total FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento WHERE DATE(data) = :data";
        $stmt   = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        $result = $stmt->fetchAssociative();
        return (int) $result['total'];
    }

    public function findAllDonos($baseId): array
    {
        $sql = "SELECT DISTINCT c.id, c.nome
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.cliente c
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON p.dono_id = c.id
                ORDER BY c.nome ASC";

        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function add(Agendamento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByStatus(string $baseId, string $status): array
    {
        $sql = "SELECT 
                    a.id,
                    a.data,
                    a.horaChegada,
                    a.horaSaida,
                    a.status,
                    p.nome AS pet_nome,
                    c.nome AS dono_nome,
                    s.nome AS servico_nome
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento a
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico aps ON a.id = aps.agendamentoId
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON aps.petId = p.id
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON p.dono_id = c.id
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.servico s ON aps.servicoId = s.id
                WHERE a.status = :status
                ORDER BY a.horaChegada ASC";

        $stmt = $this->conn->executeQuery($sql, ['status' => $status]);
        return $stmt->fetchAllAssociative();
    }

    public function atualizarStatusPetServico(string $baseId, int $id, string $status): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico 
                SET status = :status 
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'status' => $status,
            'id'     => $id,
        ]);
    }

    public function listarQuadroPorPet($baseId, \DateTime $data): array
    {
        $sql = "SELECT 
                    aps.id AS aps_id, 
                    a.id AS agendamento_id,
                    a.data,
                    a.taxi_dog, 
                    aps.status,
                    p.id AS pet_id,
                    p.nome AS pet_nome,
                    c.nome AS dono_nome,
                    GROUP_CONCAT(s.nome SEPARATOR ', ') AS servico_nome
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.agendamento_pet_servico aps
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.agendamento a ON aps.agendamentoId = a.id
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON aps.petId = p.id
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON p.dono_id = c.id
                JOIN {$_ENV['DBNAMETENANT']}{$baseId}.servico s ON aps.servicoId = s.id
                WHERE DATE(a.data) = :data
                GROUP BY a.id, p.id, aps.status, p.nome, c.nome
                ORDER BY a.horaChegada ASC";

        $stmt = $this->conn->executeQuery($sql, [
            'data' => $data->format('Y-m-d'),
        ]);

        return $stmt->fetchAllAssociative();
    }

}
