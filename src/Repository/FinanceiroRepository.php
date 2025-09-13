<?php

namespace App\Repository;

use App\Entity\Financeiro;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Financeiro>
 *
 * @method Financeiro|null find($id, $lockMode = null, $lockVersion = null)
 * @method Financeiro|null findOneBy(array $criteria, array $orderBy = null)
 * @method Financeiro[]    findAll()
 * @method Financeiro[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FinanceiroRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Financeiro::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findFinanceiro($baseId, int $financeiroId): ?Financeiro
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.financeiro 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $financeiroId]);
        $dados = $stmt->fetchAssociative();

        if (!$dados) {
            return null; // Retorna null se não encontrar o registro
        }

        // Criando e preenchendo um objeto da entidade `Financeiro`
        $financeiro = new Financeiro();
        $financeiro->setId($dados['id']);
        $financeiro->setDescricao($dados['descricao']);
        $financeiro->setValor((float) $dados['valor']);
        $financeiro->setData(new \DateTime($dados['data']));
        $financeiro->setPetId($dados['pet_id']);

        return $financeiro; // Retorna um objeto válido
    }


    public function findAllFinanceiro($baseId, $financeiroId): array
    {
        $sql = "SELECT id, descricao, valor, data, pet_id, pet_nome
                FROM {$_ENV['DBNAMETENANT']}.financeiro
                WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $financeiroId]);
        return $stmt->fetchAllAssociative();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT 
                    MIN(f.id) as id, 
                    c.nome AS dono_nome,
                    GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') AS pets,
                    SUM(f.valor) AS total_valor,
                    DATE(f.data) AS data
                FROM {$_ENV['DBNAMETENANT']}.financeiro f
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                WHERE f.estabelecimento_id = :baseId 
                  AND DATE(f.data) = :data
                  AND (f.status IS NULL OR f.status != 'inativo')
                GROUP BY c.id, DATE(f.data)
                ORDER BY c.nome";

        $stmt = $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'data' => $data->format('Y-m-d')
        ]);
        return $stmt->fetchAllAssociative();
    }


   public function getRelatorioPorPeriodo($baseId, \DateTime $dataInicio, \DateTime $dataFim): array
    {
        $sql = "SELECT DATE(f.data) as data, SUM(f.valor) as total
                FROM {$_ENV['DBNAMETENANT']}.financeiro f
                WHERE f.estabelecimento_id = :baseId 
                  AND f.data BETWEEN :dataInicio AND :dataFim
                  AND (f.status IS NULL OR f.status != 'inativo')
                GROUP BY DATE(f.data)
                ORDER BY DATE(f.data) DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId'     => $baseId,
            'dataInicio' => $dataInicio->format('Y-m-d'),
            'dataFim'    => $dataFim->format('Y-m-d'),
        ]);
    }

    public function save($baseId, Financeiro $financeiro, bool $inativo = false): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.financeiro 
                (estabelecimento_id, descricao, valor, data, pet_id, status, inativar) 
                VALUES (:estabelecimento_id, :descricao, :valor, :data, :pet_id, :status, :inativar)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
        $stmt->bindValue('descricao', $financeiro->getDescricao());
        $stmt->bindValue('valor', $financeiro->getValor());
        $stmt->bindValue('data', $financeiro->getData()->format('Y-m-d'));
        $stmt->bindValue('pet_id', $financeiro->getPetId() ?? null);
        $stmt->bindValue('status', $inativo ? 'inativo' : ($financeiro->getStatus() ?? null));
        $stmt->bindValue('inativar', $inativo ? 1 : 0);
        $stmt->execute();
    }

    public function update($baseId, Financeiro $financeiro): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.financeiro 
                SET descricao = :descricao, valor = :valor, data = :data, pet_id = :pet_id 
                WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('descricao', $financeiro->getDescricao());
        $stmt->bindValue('valor', $financeiro->getValor());
        $stmt->bindValue('data', $financeiro->getData()->format('Y-m-d'));
        $stmt->bindValue('pet_id', $financeiro->getPetId() ?? null);
        $stmt->bindValue('id', $financeiro->getId());
        $stmt->execute();
    }

    /**
     * TODO
     * NÃO SE DEVE DELETAR DADOS DA BASE DE DADOS
     * MUDA APENAS O STATUS PARA INATIVO
     */
    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.financeiro WHERE estabelecimento_id = '{$baseId}' AND id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function totalAgendamento($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM {$_ENV['DBNAMETENANT']}.agendamento
        WHERE estabelecimento_id = '{$baseId}' AND concluido = 1";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAgendamentoDia($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM {$_ENV['DBNAMETENANT']}.agendamento
        WHERE estabelecimento_id = '{$baseId}' AND concluido = 1 AND DATE(data) = DATE(NOW())";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAnimais($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAnimal
        FROM {$_ENV['DBNAMETENANT']}.pet
        WHERE estabelecimento_id = '{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function totalLucro($baseId)
    {
        $sql = "SELECT sum(valor) AS lucroTotal
        FROM {$_ENV['DBNAMETENANT']}.financeiro
        WHERE estabelecimento_id = '{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function lucroDiario($baseId)
    {
        $sql = "SELECT SUM(valor) as valor, data
        FROM {$_ENV['DBNAMETENANT']}.financeiro 
        WHERE estabelecimento_id = '{$baseId}'
        GROUP BY data";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function verificaPagamentoExistente($baseId, $petId, $valor, $dataReferencia): bool
    {
        $sql = "SELECT COUNT(*) FROM {$_ENV['DBNAMETENANT']}.financeiro
                WHERE estabelecimento_id = '{$baseId}' 
                    AND pet_id = :pet_id
                    AND valor = :valor
                    AND DATE(data) = :data_referencia
                    AND descricao = 'Hospedagem do Pet'";

        return (bool) $this->conn->fetchOne($sql, [
            'pet_id' => $petId,
            'valor' => $valor,
            'data_referencia' => (new \DateTime($dataReferencia))->format('Y-m-d'),
        ]);
    }

    public function somarPorDescricao($baseId, $descricaoParcial, \DateTime $inicio, \DateTime $fim): float
    {
        $sql = "SELECT SUM(valor) FROM {$_ENV['DBNAMETENANT']}.financeiro
                WHERE estabelecimento_id = :base_id
                  AND descricao LIKE :descricao
                  AND data BETWEEN :inicio AND :fim
                  AND (status IS NULL OR status != 'inativo')";

        return (float) $this->conn->fetchOne($sql, [
            'base_id'   => $baseId,
            'descricao' => '%' . $descricaoParcial . '%',
            'inicio'    => $inicio->format('Y-m-d'),
            'fim'       => $fim->format('Y-m-d'),
        ]);
    }
    
    public function buscarPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM {$_ENV['DBNAMETENANT']}.financeiro 
                WHERE estabelecimento_id = :baseId AND pet_id = :petId
                ORDER BY data DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function findTotalByDate(int $baseId, \DateTime $data): array
    {
        $sql = "
            SELECT
                f.id,
                f.data,
                SUM(f.valor) AS total_valor,
                c.nome AS dono_nome,
                GROUP_CONCAT(p.nome SEPARATOR ', ') AS pets
            FROM {$_ENV['DBNAMETENANT']}.financeiro f
            LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
            LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
            WHERE f.estabelecimento_id = :baseId
            AND DATE(f.data) = :data
            AND (f.status IS NULL OR f.status != 'inativo')
            GROUP BY f.pet_id, f.data
            ORDER BY f.data DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('data', $data->format('Y-m-d'));
        
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }


public function inativar($baseId, int $id): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.financeiro 
                SET status = 'inativo', inativar = 1
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id' => $id
        ]);
    }


    public function findInativos(int $baseId, ?int $petId = null): array
    {
        $sql = "SELECT f.id, f.descricao, f.valor, f.data, f.pet_id, 
                       p.nome AS pet_nome, c.nome AS dono_nome
                  FROM {$_ENV['DBNAMETENANT']}.financeiro f
             LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
             LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                 WHERE f.estabelecimento_id = :baseId
                   AND (f.status = 'inativo' OR f.inativar = 1)"; // 🔥 aceita os dois

        $params = ['baseId' => $baseId];

        if ($petId) {
            $sql .= " AND f.pet_id = :petId";
            $params['petId'] = $petId;
        }

        $sql .= " ORDER BY f.data DESC";

        return $this->conn->fetchAllAssociative($sql, $params);
    }


    public function buscarAtivosPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM {$_ENV['DBNAMETENANT']}.financeiro 
                WHERE estabelecimento_id = :baseId 
                  AND pet_id = :petId
                  AND (status IS NULL OR status != 'inativo')
                ORDER BY data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId' => $petId
        ]);
    }



}
