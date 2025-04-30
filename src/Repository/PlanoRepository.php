<?php

namespace App\Repository;

use App\Entity\Plano;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plano>
 *
 * @method Plano|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plano|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plano[]    findAll()
 * @method Plano[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plano::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function listaPlanos()
    {
        $sql = "SELECT id, titulo, valor, status, trial, dataTrial, dataPlano,
        (SELECT COUNT(*) FROM estabelecimento WHERE planoId = p.id) AS totalLojas
            FROM planos AS p
            #WHERE p.status = 'Ativo'";

        $query = $this->conn->executeQuery($sql);

        return $query->fetchAllAssociative();
    }

    public function listaPlanosHome()
    {
        $sql = "SELECT id, titulo, valor, status, trial, dataTrial, dataPlano, descricao
            FROM planos AS p
            WHERE p.status = 'Ativo'";

        $query = $this->conn->executeQuery($sql);

        return $query->fetchAllAssociative();
    }

    public function verPlano($planoId)
    {
        $sql = "SELECT id, titulo, valor, status, trial, dataTrial, dataPlano,descricao,
        (SELECT COUNT(*) FROM estabelecimento WHERE planoId = p.id) AS totalLojas
            FROM planos AS p
            WHERE p.id = $planoId";

        $query = $this->conn->executeQuery($sql);

        return $query->fetchAssociative();
    }

    public function add(Plano $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(Plano $entity, $planoId): void
    {

        $sql = "UPDATE planos SET titulo = '{$entity->getTitulo()}', descricao = '{$entity->getDescricao()}', 
        valor = '{$entity->getValor()}', status = '{$entity->getStatus()}', 
        trial = '{$entity->getTrial()}', dataPlano = '{$entity->getDataPlano()->format('Y-m-d H:i:s')}'
            WHERE id = $planoId";

        $this->conn->executeQuery($sql);
    }


    public function remove(Plano $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createPlan($data){
        $sql = "";
    }

    public function updatePlan($data){
        $sql = "";
    }

    public function deletePlan($data){
        $sql = "";
    }

//    /**
//     * @return Planos[] Returns an array of Planos objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Planos
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
