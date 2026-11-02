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
        // Connection::query() + Result::fetch() não existem mais no DBAL 3
        // (fatal "Call to undefined method Doctrine\DBAL\Result::fetch()"),
        // e o id vinha concatenado direto na query (injeção).
        $sql = "SELECT id, nome, descricao, valor
            FROM homepet_{$baseId}.servico
            WHERE estabelecimento_id = :estab AND id = :id
            LIMIT 1";

        $row = $this->conn->fetchAssociative($sql, [
            'estab' => $baseId,
            'id'    => (int) $idServico,
        ]);

        return $row ?: null;
    }

    public function findAllService($baseId, $tipo = null): array
    {
        $sql = "SELECT * 
            FROM homepet_{$baseId}.servico 
            WHERE estabelecimento_id = '{$baseId}'";
        
        if ($tipo !== null) {
            $sql .= " AND tipo = :tipo";
        }

        $stmt = $this->conn->executeQuery($sql, $tipo !== null ? ['tipo' => $tipo] : []);
        return $stmt->fetchAllAssociative();
    }

    public function findService($baseId, int $id): ?Servico
    {
        $sql = "SELECT * 
            FROM homepet_{$baseId}.servico 
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
        $sql = "INSERT INTO homepet_{$baseId}.servico (estabelecimento_id, nome, descricao, valor) VALUES 
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
        $sql = "UPDATE homepet_{$baseId}.servico SET nome = :nome, descricao = :descricao, valor = :valor 
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
        $sql = "DELETE FROM homepet_{$baseId}.servico 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id"; 
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

/** Lista enxuta de serviços para o catálogo do PDV. */
    public function listarParaPdv(int $estabelecimentoId): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT id, nome, valor, descricao
             FROM homepet_{$estabelecimentoId}.servico
             WHERE estabelecimento_id = :estab",
            ['estab' => $estabelecimentoId]
        );
    }

    /** Busca dados atuais do serviço antes de registrar uma venda. */
    public function buscarDadosVenda(int $estabelecimentoId, int $servicoId): ?array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT id, nome, valor, comissao_percentual
             FROM servico WHERE id = :id AND estabelecimento_id = :estab',
            ['id' => $servicoId, 'estab' => $estabelecimentoId]
        );

        return $row ?: null;
    }

    /** Lista serviços comissionáveis para a tela de configuração. */
    public function listarParaComissao(int $estabelecimentoId): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT id, nome, tipo, valor, comissao_percentual
             FROM homepet_{$estabelecimentoId}.servico
             WHERE estabelecimento_id = :estab
             ORDER BY tipo, nome",
            ['estab' => $estabelecimentoId]
        );
    }

    /** Salva o percentual de comissão de um serviço. */
    public function atualizarComissao(int $estabelecimentoId, int $servicoId, ?float $percentual): int
    {
        return $this->conn->executeStatement(
            "UPDATE homepet_{$estabelecimentoId}.servico
             SET comissao_percentual = :pct
             WHERE id = :id AND estabelecimento_id = :estab",
            ['pct' => $percentual, 'id' => $servicoId, 'estab' => $estabelecimentoId]
        );
    }
}
