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

    public function setBaseId($baseId = 'homepet_login')
    {
        $this->baseId = $baseId;
    }

    public function findAgendamentosByCliente($baseId, int $clienteId): array
    {
        $sql = "SELECT c.id, c.nome, email, telefone, rua, bairro, cidade, cep, p.dono_id AS dono, p.id, p.nome AS nomePet, p.dono_id, complemento, numero
                FROM homepet_1.cliente c 
                JOIN homepet_1.pet p ON c.id = p.id
                WHERE c.id = $clienteId";
        //die($sql);
        $stmt = $this->conn->executeQuery($sql, ['clienteId' => $clienteId]);
        return $stmt->fetchAllAssociative();
    }

    public function localizaTodosClientePorID($baseId, $clienteId){
        $sql = "SELECT * FROM homepet_{$baseId}.cliente WHERE id='{$clienteId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function localizaTodosCliente($baseId){
        $sql = "SELECT * FROM homepet_{$baseId}.cliente";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function save($baseId, array $clientData): void
    {
        $sql = "INSERT INTO homepet_{$baseId}.cliente (nome, email, telefone, rua, numero, complemento, bairro, cidade, whatsapp) VALUES 
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
        $sql = "UPDATE homepet_{$baseId}.cliente 
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
        $sql = 'DELETE FROM homepet_{$baseid}.cliente WHERE id = :id';
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
                FROM homepet_'.$baseId.'.cliente 
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

}
