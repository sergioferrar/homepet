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
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.consulta 
                (estabelecimento_id, cliente_id, pet_id, data, hora, observacoes, criado_em, status)
                VALUES (:estabelecimento_id, :cliente_id, :pet_id, :data, :hora, :observacoes, :criado_em, :status)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
        $stmt->bindValue('cliente_id', $consulta->getClienteId());
        $stmt->bindValue('pet_id', $consulta->getPetId());
        $stmt->bindValue('data', $consulta->getData()->format('Y-m-d'));
        $stmt->bindValue('hora', $consulta->getHora()->format('H:i:s'));
        $stmt->bindValue('observacoes', $consulta->getObservacoes());
        $stmt->bindValue('criado_em', $consulta->getCriadoEm()->format('Y-m-d H:i:s'));
        $stmt->bindValue('status', $consulta->getStatus() ?? 'aguardando');
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
        $sql = "SELECT c.id, c.data, c.hora, c.observacoes, c.status,
                       p.nome as pet_nome, cl.nome as cliente_nome
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



    public function contarConsultasPorMes($baseId): array
    {
        $sql = "SELECT MONTH(data) as mes, COUNT(*) as total
                FROM {$_ENV['DBNAMETENANT']}.consulta
                WHERE YEAR(data) = YEAR(NOW()) AND estabelecimento_id = :baseId
                GROUP BY MONTH(data)
                ORDER BY mes";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAllAssociative();

        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];

        $dados = [];
        foreach ($meses as $num => $nome) {
            $dados[$nome] = 0;
        }
        foreach ($result as $row) {
            $dados[$meses[(int)$row['mes']]] = (int)$row['total'];
        }

        return $dados;
    }

    public function atualizarStatusConsulta($baseId, int $consultaId, string $novoStatus): bool
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.consulta
                SET status = :status
                WHERE id = :id AND estabelecimento_id = :baseId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('status', $novoStatus);
        $stmt->bindValue('id', $consultaId);
        $stmt->bindValue('baseId', $baseId);
        return $stmt->executeStatement() > 0;
    }
    
    public function listarConsultasDoDiaEProximas($baseId): array
    {
        $hoje = (new \DateTime())->format('Y-m-d');

        $sql = "SELECT c.id, c.data, c.hora, c.observacoes, c.status,
                       p.nome as pet_nome, cl.nome as cliente_nome,
                       'Consulta' AS tipo
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId
                  AND c.data >= :hoje
                ORDER BY c.data ASC, c.hora ASC
                LIMIT 20";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('hoje', $hoje);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }



}
