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

//    public function findAll(): array
//    {
//        $sql = 'SELECT f.id, f.descricao, f.valor, f.data, p.nome as pet_nome, f.pet_id
//                FROM Financeiro f
//                LEFT JOIN Pet p ON f.pet_id = p.id';
//        $stmt = $this->conn->executeQuery($sql);
//        return $stmt->fetchAllAssociative();
//    }

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

//    public function find(int $id): ?Financeiro
//    {
//        $sql = 'SELECT f.*, p.nome as pet_nome
//                FROM Financeiro f
//                LEFT JOIN Pet p ON f.pet_id = p.id
//                WHERE f.id = :id';
//        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
//        $financeiroData = $stmt->fetchAssociative();
//
//        if (!$financeiroData) {
//            return null;
//        }
//
//        $financeiro = new Financeiro();
//        $financeiro->setId($financeiroData['id']);
//        $financeiro->setDescricao($financeiroData['descricao']);
//        $financeiro->setValor($financeiroData['valor']);
//        $financeiro->setData(new \DateTime($financeiroData['data']));
//        $financeiro->setpet_nome($financeiroData['pet_nome'] ?? null);
//        $financeiro->setpet_id($financeiroData['pet_id'] ?? null);
//
//        return $financeiro;
//    }

    public function save(Financeiro $financeiro): void
    {
        $sql = 'INSERT INTO Financeiro (descricao, valor, data, pet_id) VALUES (:descricao, :valor, :data, :pet_id)';
        $this->conn->executeQuery($sql, [
            'descricao' => $financeiro->getDescricao(),
            'valor' => $financeiro->getValor(),
            'data' => $financeiro->getData()->format('Y-m-d'),
            'pet_id' => $financeiro->getpet_id(),
        ]);
    }

    public function update(Financeiro $financeiro): void
    {
        $sql = 'UPDATE Financeiro SET descricao = :descricao, valor = :valor, data = :data, pet_id = :pet_id WHERE id = :id';
        $this->conn->executeQuery($sql, [
            'descricao' => $financeiro->getDescricao(),
            'valor' => $financeiro->getValor(),
            'data' => $financeiro->getData()->format('Y-m-d'),
            'pet_id' => $financeiro->getpet_id(),
            'id' => $financeiro->getId(),
        ]);
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM Financeiro WHERE id = :id';
        $this->conn->executeQuery($sql, ['id' => $id]);
    }
}
