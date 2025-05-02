<?php

namespace App\Repository;

use App\Entity\HospedagemCaes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HospedagemCaes>
 *
 * @method HospedagemCaes|null find($id, $lockMode = null, $lockVersion = null)
 * @method HospedagemCaes|null findOneBy(array $criteria, array $orderBy = null)
 * @method HospedagemCaes[]    findAll()
 * @method HospedagemCaes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HospedagemCaesRepository extends ServiceEntityRepository
{
    private $conn;
    private $baseId;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HospedagemCaes::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }


    public function insert($baseId, HospedagemCaes $h): void

    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes (cliente_id, pet_id, data_entrada, data_saida, valor, observacoes)
                VALUES (:cliente_id, :pet_id, :data_entrada, :data_saida, :valor, :observacoes)";
        $this->conn->executeQuery($sql, [
            'cliente_id' => $h->getClienteId(),
            'pet_id' => $h->getPetId(),
            'data_entrada' => $h->getDataEntrada()->format('Y-m-d H:i:s'),
            'data_saida' => $h->getDataSaida()->format('Y-m-d H:i:s'),
            'valor' => $h->getValor(),
            'observacoes' => $h->getObservacoes(),
        ]);
    }

    public function registrarFinanceiro($baseId, HospedagemCaes $h): void

    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}{$baseId}.financeiro (descricao, valor, data, pet_id, pet_nome)
                VALUES (:descricao, :valor, NOW(), :pet_id, (SELECT nome FROM {$_ENV['DBNAMETENANT']}{$baseId}.pet WHERE id = :pet_id LIMIT 1))";
        $this->conn->executeQuery($sql, [
            'descricao' => 'Hospedagem do Pet',
            'valor' => $h->getValor(),
            'pet_id' => $h->getPetId(),
        ]);
    }

    public function getClientes($baseId)
    {
        return $this->conn->fetchAllAssociative("SELECT * FROM {$_ENV['DBNAMETENANT']}{$baseId}.cliente");
    }

    public function getPets($baseId): array
    {
        $sql = "SELECT 
                    p.id, 
                    p.nome, 
                    c.id AS dono_id, 
                    c.nome AS dono_nome
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.pet p
                LEFT JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON c.id = p.dono_id";

        return $this->conn->fetchAllAssociative($sql);
    }




    public function localizaTodos($baseId): array
    {
        $sql = "SELECT h.id, h.cliente_id, c.nome AS cliente_nome,
                       h.pet_id, p.nome AS pet_nome,
                       h.data_entrada, h.data_saida, h.valor, h.observacoes
                FROM {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes h
                LEFT JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON c.id = h.cliente_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON p.id = h.pet_id
                ORDER BY h.data_entrada DESC";

        return $this->conn->fetchAllAssociative($sql);
    }


    public function localizaPorId($baseId, int $id): ?array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes WHERE id = :id";
        return $this->conn->fetchAssociative($sql, ['id' => $id]) ?: null;
    }

    public function delete($baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes WHERE id = :id", ['id' => $id]);
    }


    public function updateHospedagem($baseId, int $id, HospedagemCaes $h): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes
                SET cliente_id = :cliente_id,
                    pet_id = :pet_id,
                    data_entrada = :data_entrada,
                    data_saida = :data_saida,
                    valor = :valor,
                    observacoes = :observacoes
                WHERE id = :id";

        $this->conn->executeQuery($sql, [
            'cliente_id'    => $h->getClienteId(),
            'pet_id'        => $h->getPetId(),
            'data_entrada'  => $h->getDataEntrada()->format('Y-m-d H:i:s'),
            'data_saida'    => $h->getDataSaida()->format('Y-m-d H:i:s'),
            'valor'         => $h->getValor(),
            'observacoes'   => $h->getObservacoes(),
            'id'            => $id,
        ]);
    }

    public function localizaPorData($baseId, \DateTime $data): array
    {
        $query = "
            SELECT h.*, c.nome as cliente_nome, p.nome as pet_nome
            FROM {$_ENV['DBNAMETENANT']}{$baseId}.hospedagem_caes h
            INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.cliente c ON c.id = h.cliente_id
            INNER JOIN {$_ENV['DBNAMETENANT']}{$baseId}.pet p ON p.id = h.pet_id
            WHERE h.data_entrada <= :data AND h.data_saida >= :data
            ORDER BY h.data_entrada ASC
        ";

        return $this->conn->fetchAllAssociative($query, [
            'data' => $data->format('Y-m-d'),
        ]);
    }





//    /**
//     * @return HospedagemCaes[] Returns an array of HospedagemCaes objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('h.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?HospedagemCaes
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
