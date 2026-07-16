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

    public function salvarConsulta($baseId, Consulta $consulta): void // Removido $baseId, pois já está no objeto
    {
       
        $sql = "INSERT INTO homepet_{$baseId}.consulta
                (estabelecimento_id, cliente_id, pet_id, data, hora, observacoes, criado_em, status, anamnese, tipo, veterinario_id, attachment, attachment_original)
                VALUES (:estabelecimento_id, :cliente_id, :pet_id, :data, :hora, :observacoes, :criado_em, :status, :anamnese, :tipo, :veterinario_id, :attachment, :attachment_original)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $consulta->getEstabelecimentoId());
        $stmt->bindValue('cliente_id', $consulta->getClienteId());
        $stmt->bindValue('pet_id', $consulta->getPetId());
        $stmt->bindValue('data', $consulta->getData()->format('Y-m-d'));
        $stmt->bindValue('hora', $consulta->getHora()->format('H:i:s'));
        $stmt->bindValue('observacoes', $consulta->getObservacoes());
        $stmt->bindValue('criado_em', $consulta->getCriadoEm()->format('Y-m-d H:i:s'));
        $stmt->bindValue('status', $consulta->getStatus() ?? 'atendido');

        $stmt->bindValue('anamnese', $consulta->getAnamnese());
        $stmt->bindValue('tipo', $consulta->getTipo());
        $stmt->bindValue('veterinario_id', $consulta->getVeterinarioId());
        $stmt->bindValue('attachment', $consulta->getAttachment());
        $stmt->bindValue('attachment_original', $consulta->getAttachmentOriginal());

        $stmt->executeStatement();
    }


    public function listarConsultasPorCliente($baseId, int $clienteId): array
    {
        $sql = "SELECT c.id, c.data, c.hora, p.nome as pet_nome, cl.nome as cliente_nome, c.observacoes
                FROM homepet_{$baseId}.consulta c
                JOIN homepet_{$baseId}.pet p ON c.pet_id = p.id
                JOIN homepet_{$baseId}.cliente cl ON c.cliente_id = cl.id
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
                FROM homepet_{$baseId}.consulta c
                JOIN homepet_{$baseId}.pet p ON c.pet_id = p.id
                JOIN homepet_{$baseId}.cliente cl ON c.cliente_id = cl.id
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
                FROM homepet_{$baseId}.consulta
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
        $sql = "UPDATE homepet_{$baseId}.consulta
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
                FROM homepet_{$baseId}.consulta c
                JOIN homepet_{$baseId}.pet p ON c.pet_id = p.id
                JOIN homepet_{$baseId}.cliente cl ON c.cliente_id = cl.id
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
                FROM homepet_{$baseId}.consulta c
                JOIN homepet_{$baseId}.cliente cl ON cl.id = c.cliente_id
                JOIN homepet_{$baseId}.pet p ON p.id = c.pet_id
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
                FROM homepet_{$baseId}.consulta
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
                FROM homepet_{$baseId}.consulta
                WHERE estabelecimento_id = :baseId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAssociative();

        $total = (int) ($result['total'] ?? 0);
        $dias = (int) ($result['dias'] ?? 1); // Evita divisão por zero

        return $dias > 0 ? round($total / $dias, 2) : 0.0;
    }

    public function findAllByPetId($baseId, int $petId): array
        {
        
            $sql = "SELECT c.id, c.data, c.hora, c.observacoes, c.status, c.anamnese, c.tipo,
                           c.attachment, c.attachment_original,
                           c.veterinario_id,
                           v.nome AS veterinario_nome, v.crmv AS veterinario_crmv,
                           cl.nome AS cliente, p.nome AS pet, p.id AS pet_id
                    FROM homepet_{$baseId}.consulta c
                    JOIN homepet_{$baseId}.cliente cl ON cl.id = c.cliente_id
                    JOIN homepet_{$baseId}.pet p ON p.id = c.pet_id
                    LEFT JOIN homepet_{$baseId}.veterinario v ON v.id = c.veterinario_id
                    WHERE c.estabelecimento_id = :baseId AND c.pet_id = :petId
                    ORDER BY c.data DESC, c.hora DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('baseId', $baseId);
            $stmt->bindValue('petId', $petId);
            return $stmt->executeQuery()->fetchAllAssociative();
        }

    /**
     * Retorna os dados do anexo (encaminhamento) de uma consulta para download.
     */
    public function findAnexoConsulta($baseId, int $consultaId): ?array
    {
        $sql = "SELECT c.attachment, c.attachment_original
                FROM homepet_{$baseId}.consulta c
                WHERE c.estabelecimento_id = :baseId AND c.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $consultaId);
        $row = $stmt->executeQuery()->fetchAssociative();

        return ($row && !empty($row['attachment'])) ? $row : null;
    }

    /**
     * Lista as consultas de um período para o relatório de comissões, agrupáveis
     * por veterinário. O valor de cada consulta é pré-carregado a partir do
     * serviço da clínica cujo nome corresponde ao tipo do atendimento
     * (ex.: serviço "Consulta" para atendimentos do tipo Consulta).
     */
    public function listarConsultasComissao($baseId, \DateTime $inicio, \DateTime $fim, ?int $vetId = null): array
    {
        $sql = "SELECT c.id, c.data, c.hora, c.tipo, c.veterinario_id,
                       v.nome AS veterinario_nome, v.crmv AS veterinario_crmv,
                       p.nome AS pet_nome, cl.nome AS cliente_nome,
                       (SELECT s.valor
                          FROM homepet_{$baseId}.servico s
                         WHERE s.estabelecimento_id = :baseId
                           AND s.tipo = 'clinica'
                           AND LOWER(s.nome) = LOWER(c.tipo)
                         LIMIT 1) AS valor_servico
                FROM homepet_{$baseId}.consulta c
                JOIN homepet_{$baseId}.pet p ON p.id = c.pet_id
                JOIN homepet_{$baseId}.cliente cl ON cl.id = c.cliente_id
                JOIN homepet_{$baseId}.veterinario v ON v.id = c.veterinario_id
                WHERE c.estabelecimento_id = :baseId
                  AND c.data BETWEEN :inicio AND :fim
                  AND c.status <> 'cancelado'";

        if ($vetId) {
            $sql .= " AND c.veterinario_id = :vetId";
        }

        $sql .= " ORDER BY v.nome ASC, c.data ASC, c.hora ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('inicio', $inicio->format('Y-m-d'));
        $stmt->bindValue('fim', $fim->format('Y-m-d'));
        if ($vetId) {
            $stmt->bindValue('vetId', $vetId);
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Conta consultas do período sem veterinário vinculado (ficam fora do
     * relatório de comissões — exibido como alerta na tela).
     */
    public function contarConsultasSemVeterinario($baseId, \DateTime $inicio, \DateTime $fim): int
    {
        $sql = "SELECT COUNT(*)
                FROM homepet_{$baseId}.consulta c
                WHERE c.estabelecimento_id = :baseId
                  AND c.data BETWEEN :inicio AND :fim
                  AND c.status <> 'cancelado'
                  AND c.veterinario_id IS NULL";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('inicio', $inicio->format('Y-m-d'));
        $stmt->bindValue('fim', $fim->format('Y-m-d'));

        return (int) $stmt->executeQuery()->fetchOne();
    }

    /**
     * Retorna o veterinário do atendimento mais recente do pet (consulta ativa/último atendimento).
     * Usado para pré-preencher a receita com o profissional que fez o atendimento.
     */
    public function findVetIdUltimaConsulta($baseId, int $petId): ?int
    {
        $sql = "SELECT c.veterinario_id
                FROM homepet_{$baseId}.consulta c
                WHERE c.estabelecimento_id = :baseId
                  AND c.pet_id = :petId
                  AND c.veterinario_id IS NOT NULL
                ORDER BY c.data DESC, c.hora DESC, c.id DESC
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);
        $vetId = $stmt->executeQuery()->fetchOne();

        return $vetId !== false && $vetId !== null ? (int) $vetId : null;
    }


}
