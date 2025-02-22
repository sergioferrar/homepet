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

    public function findByDate(\DateTime $data): array
    {
        $sql = 'SELECT f.id, f.descricao, f.valor, f.data, p.nome as pet_nome, f.pet_id
                FROM Financeiro f
                LEFT JOIN Pet p ON f.pet_id = p.id
                WHERE DATE(f.data) = :data';
        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function getRelatorio(): array
    {
        $sql = 'SELECT DATE(f.data) as data, SUM(f.valor) as total
                FROM Financeiro f
                GROUP BY DATE(f.data)
                ORDER BY DATE(f.data) DESC';
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function save(Financeiro $financeiro): void
    {
        $sql = 'INSERT INTO Financeiro (descricao, valor, data, pet_id) 
                VALUES (:descricao, :valor, :data, :pet_id)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('descricao', $financeiro->getDescricao());
        $stmt->bindValue('valor', $financeiro->getValor());
        $stmt->bindValue('data', $financeiro->getData()->format('Y-m-d'));
        $stmt->bindValue('pet_id', $financeiro->getPetId() ?? null);
        $stmt->execute();
    }

    public function update(Financeiro $financeiro): void
    {
        $sql = 'UPDATE Financeiro 
                SET descricao = :descricao, valor = :valor, data = :data, pet_id = :pet_id 
                WHERE id = :id';

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
    public function delete(int $id): void
    {
        $sql = 'DELETE FROM Financeiro WHERE id = :id';
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function totalAgendamento()
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM agendamento
        WHERE concluido = 1";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAgendamentoDia()
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM agendamento
        WHERE concluido = 1 AND DATE(data) = DATE(NOW())";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAnimais()
    {
        $sql = "SELECT COUNT(*) AS totalAnimal
        FROM pet";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function totalLucro()
    {
        $sql = "SELECT sum(valor) AS lucroTotal
        FROM financeiro";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }
}
