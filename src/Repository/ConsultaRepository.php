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

    /**
     * ATUALIZADO: Salva uma consulta/atendimento no banco de dados, agora incluindo o campo anamnese.
     */
    public function salvarConsulta(Consulta $consulta): void
    {
        // Adicionamos a coluna 'anamnese' no SQL
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.consulta 
                    (estabelecimento_id, cliente_id, pet_id, data, hora, observacoes, criado_em, status, anamnese, tipo)
                VALUES (:estabelecimento_id, :cliente_id, :pet_id, :data, :hora, :observacoes, :criado_em, :status, :anamnese, :tipo)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $consulta->getEstabelecimentoId());
        $stmt->bindValue('cliente_id', $consulta->getClienteId());
        $stmt->bindValue('pet_id', $consulta->getPetId());
        $stmt->bindValue('data', $consulta->getData()->format('Y-m-d'));
        $stmt->bindValue('hora', $consulta->getHora()->format('H:i:s'));
        $stmt->bindValue('observacoes', $consulta->getObservacoes());
        $stmt->bindValue('criado_em', $consulta->getCriadoEm()->format('Y-m-d H:i:s'));
        $stmt->bindValue('status', $consulta->getStatus() ?? 'atendido');
        
        // Adicionamos o bind para os novos valores
        $stmt->bindValue('anamnese', $consulta->getAnamnese()); 
        $stmt->bindValue('tipo', $consulta->getTipo()); 
        
        $stmt->executeStatement();
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

    public function listarConsultasDoDia($baseId, \DateTime $data, ?string $petNome = null): array
    {
        $sql = "SELECT c.id, c.data, c.hora, c.observacoes, c.status,
                       p.nome as pet_nome, cl.nome as cliente_nome
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId AND c.data = :data";
        
        if ($petNome) {
            $sql .= " AND p.nome LIKE :petNome";
        }
        
        $sql .= " ORDER BY c.hora";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('data', $data->format('Y-m-d'));
        if ($petNome) {
            $stmt->bindValue('petNome', '%' . $petNome . '%');
        }
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

    public function listarUltimosAtendimentos($baseId, $limite = 5): array
    {
        $sql = "SELECT c.id, c.data, c.hora, c.status,
                       cl.nome AS cliente, p.nome AS pet, p.id AS pet_id
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON cl.id = c.cliente_id
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.id = c.pet_id
                WHERE c.estabelecimento_id = :baseId
                ORDER BY c.data DESC, c.hora DESC
                LIMIT :limite";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function contarConsultasPorStatus($baseId): array
    {
        $sql = "SELECT status, COUNT(*) as total
                FROM {$_ENV['DBNAMETENANT']}.consulta
                WHERE estabelecimento_id = :baseId
                GROUP BY status";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAllAssociative();

        $dados = [
            'aguardando' => 0,
            'atendido' => 0,
            'cancelado' => 0
        ];

        foreach ($result as $row) {
            $status = $row['status'] ?? 'desconhecido';
            $dados[$status] = (int) $row['total'];
        }

        return $dados;
    }

    public function calcularMediaConsultas($baseId): float
    {
        $sql = "SELECT COUNT(*) as total, COUNT(DISTINCT DATE(data)) as dias
                FROM {$_ENV['DBNAMETENANT']}.consulta
                WHERE estabelecimento_id = :baseId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAssociative();

        $total = (int) ($result['total'] ?? 0);
        $dias = (int) ($result['dias'] ?? 1); 

        return $dias > 0 ? round($total / $dias, 2) : 0.0;
    }

    public function findConsultaCompletaById($baseId, int $id): ?array
    {
        $sql = "SELECT c.*, p.nome as pet_nome, p.raca, p.sexo, p.idade, cl.nome as dono_nome, cl.telefone as dono_telefone
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId AND c.id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $id);
        $result = $stmt->executeQuery();

        $data = $result->fetchAssociative();
        return $data ?: null;
    }

    public function findAllByPetId(int $baseId, int $petId): array
    {
        $sql = "SELECT c.*, p.nome as pet_nome, cl.nome as dono_nome
                FROM {$_ENV['DBNAMETENANT']}.consulta c
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON c.pet_id = p.id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente cl ON c.cliente_id = cl.id
                WHERE c.estabelecimento_id = :baseId AND c.pet_id = :petId
                ORDER BY c.data DESC, c.hora DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);
        
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }
}
