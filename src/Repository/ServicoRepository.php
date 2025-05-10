<?php
namespace App\Repository;

use App\Entity\Servico;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Servico>
 *
 * @method Servico|null find($id, $lockMode = null, $lockVersion = null)
 * @method Servico|null findOneBy(array $criteria, array $orderBy = null)
 * @method Servico[]    findAll()
 * @method Servico[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

class ServicoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Servico::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function listaServicoPorId($baseId, $idServico)
    {
        $sql = "SELECT id, nome, descricao, valor 
            FROM {$_ENV['DBNAMETENANT']}.servico
            WHERE estabelecimento_id = '{$baseId}' AND id = {$idServico}";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function findAllService($baseId): array
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.servico 
            WHERE estabelecimento_id = '{$baseId}'"; // Tabela com 's' minúsculo

        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAllAssociative();
    }

    public function findService($baseId, int $id): ?Servico
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.servico 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id"; // Tabela com 's' minúsculo

        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        $servicoData = $stmt->fetchAssociative();

        if (!$servicoData) {
            return null;
        }

        $servico = new Servico();
        $servico->setId($servicoData['id']);
        $servico->setNome($servicoData['nome']);
        $servico->setDescricao($servicoData['descricao']);
        $servico->setValor($servicoData['valor']);

        return $servico;
    }

    public function save($baseId, Servico $servico): void
    {
        // Tabela com 's' minúsculo
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.servico (estabelecimento_id, nome, descricao, valor) VALUES 
        (:estabelecimento_id, :nome, :descricao, :valor)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
        $stmt->bindValue('nome', $servico->getNome());
        $stmt->bindValue('descricao', $servico->getDescricao());
        $stmt->bindValue('valor', $servico->getValor());
        $stmt->execute();
    }

    public function update($baseId, Servico $servico): void
    {
        // Tabela com 's' minúsculo
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.servico SET nome = :nome, descricao = :descricao, valor = :valor 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('nome', $servico->getNome());
        $stmt->bindValue('descricao', $servico->getDescricao());
        $stmt->bindValue('valor', $servico->getValor());
        $stmt->bindValue('id', $servico->getId());
        $stmt->execute();
    }

    public function delete($baseId, int $id): void
    {
        // Tabela com 's' minúsculo
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.servico 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id"; 
        $this->conn->executeQuery($sql, ['id' => $id]);
    }
}
