<?php

namespace App\Repository;

use App\Entity\Estabelecimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Estabelecimento>
 *
 * @method Estabelecimento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Estabelecimento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Estabelecimento[]    findAll()
 * @method Estabelecimento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstabelecimentoRepository extends ServiceEntityRepository
{
    private $conn;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estabelecimento::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function add(Estabelecimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Estabelecimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function listaEstabelecimentos($baseId)
    {
        $sql = "SELECT estabelecimento.id, razaoSocial, cnpj, rua, numero, complemento, bairro, cidade, pais, cep, estabelecimento.status, dataCadastro, dataAtualizacao, planoId, dataPlanoInicio, dataPlanoFim, titulo
            FROM estabelecimento
            LEFT JOIN planos ON (planos.id = estabelecimento.planoId)";

        $query = $this->conn->executeQuery($sql);

        return $query->fetchAllAssociative();
    }

    public function verificaDatabase()
    {
        $sql = "SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME ='{$_ENV['DBNAMETENANT']}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }


    public function renovacao($baseId, $eid, $dataInicio, $dataFim)
    {
        $sql = "UPDATE estabelecimento 
            SET dataPlanoInicio = '{$dataInicio}', dataPlanoFim = '{$dataFim}'
            WHERE id='$eid'";

        $this->conn->executeQuery($sql);
    }

    public function aprovacao($baseId, $eid)
    {
        $sql = "UPDATE estabelecimento 
            SET status = 'Ativo'
            WHERE id='$eid'";

        $this->conn->executeQuery($sql);
    }

//    /**
//     * @return Estabelecimento[] Returns an array of Estabelecimento objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Estabelecimento
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
