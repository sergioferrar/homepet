<?php

namespace App\Repository;

use App\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Config>
 *
 * @method Config|null find($id, $lockMode = null, $lockVersion = null)
 * @method Config|null findOneBy(array $criteria, array $orderBy = null)
 * @method Config[]    findAll()
 * @method Config[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function add(Config $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Config $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Config[] Returns an array of Config objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Config
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

private function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /** Lê configurações do estabelecimento global por tipo. */
    public function listarMapaGlobalPorTipo(string $tipo, int $estabelecimentoId = 0): array
    {
        $rows = $this->getConnection()->fetchAllAssociative(
            'SELECT chave, valor FROM config
             WHERE estabelecimento_id = :estab AND tipo = :tipo',
            ['estab' => $estabelecimentoId, 'tipo' => $tipo]
        );

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row['chave']] = $row['valor'];
        }

        return $mapa;
    }

    /** Cria a tabela global, se necessário, e grava as configurações recebidas. */
    public function salvarMapaGlobalPorTipo(string $tipo, array $valores, array $observacoes, int $estabelecimentoId = 0): void
    {
        $conn = $this->getConnection();
        $conn->executeStatement("
            CREATE TABLE IF NOT EXISTS `config` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `estabelecimento_id` INT NOT NULL DEFAULT 0,
                `chave` VARCHAR(255) NOT NULL,
                `valor` LONGTEXT NULL,
                `tipo` VARCHAR(255) NOT NULL,
                `observacao` TEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_config_estab_tipo_chave` (`estabelecimento_id`, `tipo`(100), `chave`(100))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        foreach ($valores as $chave => $valor) {
            $conn->executeStatement(
                "INSERT INTO `config` (`estabelecimento_id`, `tipo`, `chave`, `valor`, `observacao`)
                 VALUES (:estab, :tipo, :chave, :valor, :obs)
                 ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)",
                [
                    'estab' => $estabelecimentoId,
                    'tipo' => $tipo,
                    'chave' => (string) $chave,
                    'valor' => $valor,
                    'obs' => $observacoes[$chave] ?? null,
                ]
            );
        }
    }
}
