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
        $sql = "SELECT * FROM homepet_{$baseId}.financeiro WHERE id = :id";
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
                FROM homepet_{$baseId}.financeiro
                WHERE id = :id";

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
                FROM homepet_{$baseId}.financeiro f
                LEFT JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
                LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
                WHERE DATE(f.data) = :data
                GROUP BY c.id, DATE(f.data)
                ORDER BY c.nome";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }




    public function getRelatorioPorPeriodo($baseId, \DateTime $dataInicio, \DateTime $dataFim): array
    {
        $sql = "SELECT DATE(f.data) as data, SUM(f.valor) as total
                FROM homepet_{$baseId}.financeiro f
                WHERE f.data BETWEEN :dataInicio AND :dataFim
                GROUP BY DATE(f.data)
                ORDER BY DATE(f.data) DESC";

        $stmt = $this->conn->executeQuery($sql, [
            'dataInicio' => $dataInicio->format('Y-m-d'),
            'dataFim' => $dataFim->format('Y-m-d')
        ]);

        return $stmt->fetchAllAssociative();
    }


    public function save($baseId, Financeiro $financeiro): void
    {
        $sql = "INSERT INTO homepet_{$baseId}.financeiro (descricao, valor, data, pet_id) 
                VALUES (:descricao, :valor, :data, :pet_id)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('descricao', $financeiro->getDescricao());
        $stmt->bindValue('valor', $financeiro->getValor());
        $stmt->bindValue('data', $financeiro->getData()->format('Y-m-d'));
        $stmt->bindValue('pet_id', $financeiro->getPetId() ?? null);
        $stmt->execute();
    }

    public function update($baseId, Financeiro $financeiro): void
    {
        $sql = "UPDATE homepet_{$baseId}.financeiro 
                SET descricao = :descricao, valor = :valor, data = :data, pet_id = :pet_id 
                WHERE id = :id";

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
        $sql = "DELETE FROM homepet_{$baseId}.financeiro WHERE id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function totalAgendamento($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM homepet_{$baseId}.agendamento
        WHERE concluido = 1";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAgendamentoDia($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM homepet_{$baseId}.agendamento
        WHERE concluido = 1 AND DATE(data) = DATE(NOW())";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAnimais($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAnimal
        FROM homepet_{$baseId}.pet";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function totalLucro($baseId)
    {
        $sql = "SELECT sum(valor) AS lucroTotal
        FROM homepet_{$baseId}.financeiro";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function lucroDiario($baseId)
    {
        $sql = "SELECT SUM(valor) as valor, data
        FROM homepet_{$baseId}.financeiro GROUP BY data";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

}
