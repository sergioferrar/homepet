<?php

namespace App\Repository;

use App\Entity\Consulta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsultaRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consulta::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function salvarConsulta($baseId, Consulta $consulta): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.consulta (estabelecimento_id, cliente_id, pet_id, data, hora, observacoes, criado_em)
                VALUES (:estabelecimento_id, :cliente_id, :pet_id, :data, :hora, :observacoes, :criado_em)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
        $stmt->bindValue('cliente_id', $consulta->getClienteId());
        $stmt->bindValue('pet_id', $consulta->getPetId());
        $stmt->bindValue('data', $consulta->getData()->format('Y-m-d'));
        $stmt->bindValue('hora', $consulta->getHora()->format('H:i:s'));
        $stmt->bindValue('observacoes', $consulta->getObservacoes());
        $stmt->bindValue('criado_em', $consulta->getCriadoEm()->format('Y-m-d H:i:s'));
        $stmt->execute();
    }

    public function listarConsultasPorCliente($baseId, int $clienteId): array
    {
        $sql = "SELECT c.id, c.data, c.hora, p.nome as pet_nome, cl.nome as cliente_nome, c.observacoes
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId AND c.cliente_id = :clienteId
                ORDER BY c.data DESC, c.hora DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('clienteId', $clienteId);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function listarConsultasDoDia($baseId, \DateTime $data): array
    {
        $sql = "SELECT c.id, c.data, c.hora, p.nome as pet_nome, cl.nome as cliente_nome
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId AND c.data = :data
                ORDER BY c.hora";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('data', $data->format('Y-m-d'));
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }
}
