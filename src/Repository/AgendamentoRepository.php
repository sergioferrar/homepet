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
        $sql = "SELECT a.id, data, concluido, pronto, horaChegada, metodo_pagamento, horaSaida, horaChegada, taxi_dog, taxa_taxi_dog
            FROM homepet_{$baseId}.agendamento a
            LEFT JOIN homepet_{$baseId}.agendamento_pet_servico aps ON a.id = aps.agendamentoId
            WHERE a.id = {$idAgendamento}";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function listaApsPorId($baseId, $idAgendamento)
    {
        $sql = "SELECT aps.id, agendamentoId, petId, servicoId, p.nome AS pet_nome, c.nome AS cliente_nome, s.nome AS servico_nome, s.valor
            FROM homepet_{$baseId}.agendamento_pet_servico aps
            JOIN homepet_{$baseId}.pet p ON (p.id = aps.petId)
            JOIN homepet_{$baseId}.cliente c ON (c.id = p.dono_id)
            JOIN homepet_{$baseId}.servico s ON (s.id = aps.servicoId)
            WHERE agendamentoId = {$idAgendamento}";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT a.id AS agendamento_id, a.data, c.id AS dono_id, c.nome AS dono_nome,
                GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ', ') AS pet_nome,
                GROUP_CONCAT(DISTINCT CONCAT('(', s.descricao, ' R$ ', s.valor, ')') ORDER BY s.descricao SEPARATOR ', ') AS servico_nome,
                 c.email, c.telefone, c.rua, c.numero, c.complemento, c.bairro, c.cidade, c.whatsapp, c.cep,
                 a.id, a.data, a.concluido, a.horaChegada, a.horaSaida, a.metodo_pagamento, a.taxi_dog, a.taxa_taxi_dog, s.valor
            FROM homepet_{$baseId}.agendamento a
            JOIN homepet_{$baseId}.agendamento_pet_servico aps ON a.id = aps.agendamentoId
            JOIN homepet_{$baseId}.pet p ON aps.petId = p.id
            JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
            JOIN homepet_{$baseId}.servico s ON aps.servicoId = s.id
            WHERE DATE(a.data) = :data
            GROUP BY a.id, c.id, c.nome, a.data
            ORDER BY a.data DESC";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function listagem($baseId, int $id)
    {
        $sql  = "SELECT * FROM homepet_{$baseId}.agendamento WHERE id = :id";
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
            'data'             => $agendamento->getData()->format('Y-m-d H:i:s'),
                                                                     //'pet_id' => $agendamento->getPetId(),
                                                                     //'servico_id' => $agendamento->getServicoId(),
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

        $sql = "INSERT INTO homepet_{$baseId}.agendamento_pet_servico (agendamentoId, petId, servicoId)
            VALUES ('{$agendamentoIdPetServico->getAgendamentoId()}', '{$agendamentoIdPetServico->getPetId()}', '{$agendamentoIdPetServico->getServicoId()}')";

        $this->conn->executeQuery($sql);

    }

    public function update($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento
                SET data = :data, 
                    concluido = :concluido,
                    metodo_pagamento = :metodo_pagamento, 
                    horaChegada = :horaChegada, 
                    horaSaida = :horaSaida,
                    taxi_dog = :taxi_dog, 
                    taxa_taxi_dog = :taxa_taxi_dog
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'data'             => $agendamento->getData()->format('Y-m-d H:i:s'),
            'concluido'        => (int) $agendamento->isConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'horaChegada'      => $agendamento->getHoraChegada() ? $agendamento->getHoraChegada()->format('Y-m-d H:i:s') : null,
            'horaSaida'        => $agendamento->getHoraSaida() ? $agendamento->getHoraSaida()->format('Y-m-d H:i:s') : null,
            'taxi_dog'         => (int) $agendamento->getTaxiDog(),
            'taxa_taxi_dog'    => $agendamento->getTaxaTaxiDog(),
            'id'               => $agendamento->getId(),
        ]);
    }


    public function updateConcluido($baseId, $idAgendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento
                SET concluido = 1
                WHERE id = $idAgendamento";

        $this->conn->executeQuery($sql);
    }   

    public function updatePagamento($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento
                SET metodo_pagamento = :metodo_pagamento
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'id' => $agendamento->getId(),
        ]);
    }   

    public function updateSaida($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento
                SET horaSaida = :horaSaida
                WHERE id = :id";
        $this->conn->executeQuery($sql, [
            'horaSaida' => $agendamento->getHoraSaida()->format('Y-m-d H:i:s'),
            'id' => $agendamento->getId(),
        ]);
    }    

    public function updateAgendamento($baseId, Agendamento $agendamento): void
    {
        $sql = "UPDATE homepet_{$baseId}.agendamento
                SET concluido = :concluido, metodo_pagamento = :metodo_pagamento, taxi_dog = :taxi_dog, taxa_taxi_dog = :taxa_taxi_dog
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'concluido'        => (int) $agendamento->isConcluido(),
            'metodo_pagamento' => $agendamento->getMetodoPagamento(),
            'taxi_dog'         => (int) $agendamento->getTaxiDog(),
            'taxa_taxi_dog'    => $agendamento->getTaxaTaxiDog(),
            'id'               => $agendamento->getId(),
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
        $sql  = "SELECT id, CONCAT(nome, ' - ', valor) as nome FROM homepet_{$baseId}.servico";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function contarAgendamentosPorData($baseId, \DateTime $data): int
    {
        $sql    = "SELECT COUNT(*) as total FROM homepet_{$baseId}.agendamento WHERE DATE(data) = :data";
        $stmt   = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        $result = $stmt->fetchAssociative();
        return (int) $result['total'];
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

    public function add(Agendamento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

}
