<?php

namespace App\Repository;

use App\Entity\Cliente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Clientes>
 *
 * @method Cliente|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cliente|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cliente[]    findAll()
 * @method Cliente[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClienteRepository extends ServiceEntityRepository
{
    private $conn;
    private $baseId;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cliente::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function setBaseId($baseId = 'u199209817_login')
    {
        $this->baseId = $baseId;
    }


    public function findAgendamentosByCliente($baseId, int $clienteId): array
    {
        $sql = " SELECT 
                a.id,
                a.data,
                a.horaChegada,
                a.horaSaida,
                a.metodo_pagamento,
                a.taxi_dog,
                a.taxa_taxi_dog,
                a.concluido,
                p.nome AS pet_nome,
                s.nome AS servico_nome
            FROM u199209817_{$baseId}.agendamento a
            INNER JOIN u199209817_{$baseId}.agendamento_pet_servico aps ON aps.agendamentoId = a.id
            INNER JOIN u199209817_{$baseId}.pet p ON p.id = aps.petId
            INNER JOIN u199209817_{$baseId}.servico s ON s.id = aps.servicoId
            WHERE p.dono_id = :clienteId
            ORDER BY a.data DESC, a.horaChegada DESC";

        $stmt = $this->conn->executeQuery($sql, ['clienteId' => $clienteId]);
        return $stmt->fetchAllAssociative();
    }

    public function localizaTodosClientePorID($baseId, $clienteId){
        $sql = "SELECT * FROM u199209817_{$baseId}.cliente WHERE id='{$clienteId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function localizaTodosCliente($baseId){
        $sql = "SELECT * FROM u199209817_{$baseId}.cliente";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function save($baseId, array $clientData): void
    {
        $sql = "INSERT INTO u199209817_{$baseId}.cliente (nome, email, telefone, rua, numero, complemento, bairro, cidade, whatsapp) VALUES 
        ('{$clientData['nome']}', '{$clientData['email']}', '{$clientData['telefone']}', '{$clientData['rua']}', '{$clientData['numero']}', '{$clientData['complemento']}', '{$clientData['bairro']}', '{$clientData['cidade']}', '{$clientData['whatsapp']}')";
        $stmt = $this->conn->query($sql);
        
        //return $this->conn->lastInsertId();
    }

    /**
    Tive que fazer essa alteração de modo que não estava atualizando os dados devido o execute e o executeQuery
    fazem de forma que o Doctrine ta ainda inccompleto, estou ainda revendo esses casos.
    */
    public function update($baseId, array $clienteData): void
    {
        $sql = "UPDATE u199209817_{$baseId}.cliente 
                SET nome = '{$clienteData['nome']}', email = '{$clienteData['email']}', telefone = '{$clienteData['telefone']}', 
                    rua = '{$clienteData['rua']}', 
                    numero = '{$clienteData['numero']}', 
                    complemento = '{$clienteData['complemento']}', 
                    bairro = '{$clienteData['bairro']}', 
                    cidade = '{$clienteData['cidade']}',
                    whatsapp = '{$clienteData['whatsapp']}'
               WHERE id = '{$clienteData['id']}'";

        $stmt = $this->conn->query($sql);
        //$stmt->execute($clienteData);
    }

    public function delete($baseId, int $id): void
    {
        $sql = 'DELETE FROM u199209817_{$baseid}.cliente WHERE id = :id';
        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function search($baseId, string $term = null): array
    {
        $search = '';
        $where = '';
        if ($term && $term != null) {
            $search = ['term' => '%' . $term . '%'];
            $where = 'WHERE nome LIKE :term OR email LIKE :term OR telefone LIKE :term OR Endereco LIKE :term';
        }
        $sql = 'SELECT * 
                FROM u199209817_'.$baseId.'.cliente 
                ' . $where;
        if ($term && !empty($term)) {

            $stmt = $this->conn->executeQuery($sql, ['term' => '%' . $term . '%']);
            return $stmt->fetchAllAssociative();

        }
        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function getLastInsertedId(): int
    {
        return $this->conn->lastInsertId();
    }

    public function findClienteByPetId($baseId, int $petId): ?array
    {
        $sql = "SELECT 
                    c.id,
                    c.nome,
                    c.email,
                    c.telefone,
                    c.whatsapp,
                    c.rua,
                    c.numero,
                    c.complemento,
                    c.bairro,
                    c.cidade,
                    c.cep
                FROM homepet_{$baseId}.cliente c
                INNER JOIN homepet_{$baseId}.pet p ON p.dono_id = c.id
                WHERE p.id = :petId";

        $stmt = $this->conn->executeQuery($sql, ['petId' => $petId]);
        return $stmt->fetchAssociative() ?: null;
    }


}
