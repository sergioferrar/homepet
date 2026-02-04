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

    public function findFinanceiro($baseId, int $financeiroId): ?Financeiro
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.venda 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $financeiroId]);
        $dados = $stmt->fetchAssociative();

        if (!$dados) {
            return null; // Retorna null se não encontrar o registro
        }

/*
        // Criando e preenchendo um objeto da entidade `Financeiro`
        $financeiro = new Financeiro();
        $financeiro->setId($dados['id']);
        $financeiro->setDescricao($dados['descricao']);
        $financeiro->setValor((float) $dados['valor']);
        $financeiro->setData(new \DateTime($dados['data']));
        $financeiro->setPetId($dados['pet_id']);

        return $financeiro; // Retorna um objeto válido
        */
    }


    public function findAllFinanceiro($baseId, $financeiroId): array
    {
        $sql = "SELECT id, descricao, toal, data, pet_id, pet_nome
                FROM {$_ENV['DBNAMETENANT']}.venda
                LEFT JOIN pet ON (pet.id = venda.pet_id)
                WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $financeiroId]);
        return $stmt->fetchAllAssociative();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT 
                    MIN(f.id) as id, 
                    c.nome AS dono_nome,
                    GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') AS pets,
                    SUM(f.total) AS total_valor,
                    DATE(f.data) AS data
                FROM {$_ENV['DBNAMETENANT']}.venda f
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                WHERE f.estabelecimento_id = :baseId 
                  AND DATE(f.data) = :data
                  AND f.status NOT IN ('Inativa', 'Pendente', 'Carrinho')
                GROUP BY c.id, DATE(f.data)
                ORDER BY c.nome";

        $stmt = $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'data' => $data->format('Y-m-d')
        ]);
        return $stmt->fetchAllAssociative();
    }


   public function getRelatorioPorPeriodo($baseId, \DateTime $dataInicio, \DateTime $dataFim): array
    {
        $sql = "SELECT DATE(f.data) as data, SUM(f.total) as total
                FROM {$_ENV['DBNAMETENANT']}.venda f
                WHERE f.estabelecimento_id = :baseId 
                  AND f.data BETWEEN :dataInicio AND :dataFim
                  AND f.status NOT IN ('Inativa', 'Pendente', 'Carrinho')
                GROUP BY DATE(f.data)
                ORDER BY DATE(f.data) DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId'     => $baseId,
            'dataInicio' => $dataInicio->format('Y-m-d'),
            'dataFim'    => $dataFim->format('Y-m-d'),
        ]);
    }

    public function save($baseId, Financeiro $financeiro, bool $inativo = false): void
    {
        $descricao = $financeiro->getDescricao();
        $valor = $financeiro->getValor();
        $data = $financeiro->getData()->format('Y-m-d');
        $petId = $financeiro->getPetId() ?? null;
        $status = $inativo ? 'inativo' : ($financeiro->getStatus() ?? null);
        $inativar = $inativo ? 1 : 0;
        $origem = $financeiro->getOrigem() ?? 'pdv'; // Define origem padrão como pdv

        // tenta ler quantidade de diárias do objeto ou do POST
        $qtdDiarias = method_exists($financeiro, 'getQuantidadeDiarias')
            ? (int) $financeiro->getQuantidadeDiarias()
            : (int) ($_POST['quantidade_diarias'] ?? 1);

        // SQL base - insere na tabela venda
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.venda 
                (estabelecimento_id, pet_id, total, data, status, inativar, origem) 
                VALUES (:estabelecimento_id, :pet_id, :total, :data, :status, :inativar, :origem)";

        // se for internação com várias diárias → cria vários registros
        if (stripos($descricao, 'internação') !== false && $qtdDiarias > 1) {
            for ($i = 1; $i <= $qtdDiarias; $i++) {
                $this->conn->executeQuery($sql, [
                    'estabelecimento_id' => $baseId,
                    'pet_id' => $petId,
                    'total' => $valor,
                    'data' => $data,
                    'status' => $status,
                    'inativar' => $inativar,
                    'origem' => $origem,
                ]);
            }
            return; // encerra aqui
        }

        // se não for internação ou só 1 diária → grava normalmente
        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'pet_id' => $petId,
            'total' => $valor,
            'data' => $data,
            'status' => $status,
            'inativar' => $inativar,
            'origem' => $origem,
        ]);
    }



    public function update($baseId, Financeiro $financeiro): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.financeiro 
                SET descricao = :descricao, valor = :valor, data = :data, pet_id = :pet_id 
                WHERE estabelecimento_id = '{$baseId}' AND id = :id";

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
    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.financeiro WHERE estabelecimento_id = '{$baseId}' AND id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function totalAgendamento($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM {$_ENV['DBNAMETENANT']}.agendamento
        WHERE estabelecimento_id = '{$baseId}' AND concluido = 1";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAgendamentoDia($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAgendamento
        FROM {$_ENV['DBNAMETENANT']}.agendamento
        WHERE estabelecimento_id = '{$baseId}' AND concluido = 1 AND DATE(data) = DATE(NOW())";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function totalAnimais($baseId)
    {
        $sql = "SELECT COUNT(*) AS totalAnimal
        FROM {$_ENV['DBNAMETENANT']}.pet
        WHERE estabelecimento_id = '{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function totalLucro($baseId)
    {
        $sql = "SELECT sum(valor) AS lucroTotal
        FROM {$_ENV['DBNAMETENANT']}.financeiro
        WHERE estabelecimento_id = '{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function totalLucroPorMes($baseId, $mes = null, $ano = null)
    {
        $mes = $mes ?? date('m');
        $ano = $ano ?? date('Y');

        $sql = "SELECT SUM(total) AS lucroTotal
                FROM {$_ENV['DBNAMETENANT']}.venda
                WHERE estabelecimento_id = :baseId
                  AND MONTH(data) = :mes
                  AND YEAR(data) = :ano
                  AND status NOT IN ('Inativa', 'Pendente', 'Carrinho')";

        $stmt = $this->conn->prepare($sql);
        $result = $stmt->executeQuery([
            'baseId' => $baseId,
            'mes' => $mes,
            'ano' => $ano
        ]);

        return $result->fetchAssociative();
    }


    public function lucroDiario($baseId)
    {
        $sql = "SELECT SUM(total) as valor, data
        FROM {$_ENV['DBNAMETENANT']}.venda
        WHERE estabelecimento_id = '{$baseId}'
        GROUP BY data";

        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function verificaPagamentoExistente($baseId, $petId, $valor, $dataReferencia): bool
    {
        $sql = "SELECT COUNT(*) FROM {$_ENV['DBNAMETENANT']}.financeiro
                WHERE estabelecimento_id = '{$baseId}' 
                    AND pet_id = :pet_id
                    AND valor = :valor
                    AND DATE(data) = :data_referencia
                    AND descricao = 'Hospedagem do Pet'";

        return (bool) $this->conn->fetchOne($sql, [
            'pet_id' => $petId,
            'valor' => $valor,
            'data_referencia' => (new \DateTime($dataReferencia))->format('Y-m-d'),
        ]);
    }

    public function somarPorDescricao($baseId, $descricaoParcial, \DateTime $inicio, \DateTime $fim): float
    {
        $sql = "SELECT SUM(valor) FROM {$_ENV['DBNAMETENANT']}.financeiro
                WHERE estabelecimento_id = :base_id
                  AND descricao LIKE :descricao
                  AND data BETWEEN :inicio AND :fim
                  AND (status IS NULL OR status != 'inativo')";

        return (float) $this->conn->fetchOne($sql, [
            'base_id'   => $baseId,
            'descricao' => '%' . $descricaoParcial . '%',
            'inicio'    => $inicio->format('Y-m-d'),
            'fim'       => $fim->format('Y-m-d'),
        ]);
    }
    
    public function buscarPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM {$_ENV['DBNAMETENANT']}.financeiro 
                WHERE estabelecimento_id = :baseId AND pet_id = :petId
                ORDER BY data DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function findTotalByDate(int $baseId, $data): array
    {
        $sql = "SELECT f.id, DATE(f.data) AS data, SUM(f.total) AS total_valor, c.nome AS dono_nome, GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') AS pets
            FROM {$_ENV['DBNAMETENANT']}.venda f
            LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
            LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
            WHERE f.estabelecimento_id = $baseId
              AND DATE(f.data) = DATE('{$data}')
              AND f.status NOT IN ('Inativa', 'Pendente', 'Carrinho')
            GROUP BY c.id, DATE(f.data)
            ORDER BY data DESC";

        $result = $this->conn->executeQuery($sql);        
        return $result->fetchAllAssociative();
    }

    public function findTotalByDateAndOrigem(int $baseId, $data, string $origem): array
    {
        $sql = "SELECT f.id, DATE(f.data) AS data, SUM(f.total) AS total_valor, c.nome AS dono_nome, GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') AS pets, f.origem
            FROM {$_ENV['DBNAMETENANT']}.venda f
            LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
            LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
            WHERE f.estabelecimento_id = $baseId
              AND DATE(f.data) = DATE('{$data}')
              AND f.origem = :origem
              AND f.status NOT IN ('Inativa', 'Pendente', 'Carrinho')
            GROUP BY c.id, DATE(f.data)
            ORDER BY data DESC";

        $result = $this->conn->executeQuery($sql, ['origem' => $origem]);        
        return $result->fetchAllAssociative();
    }



public function inativar($baseId, int $id): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.venda 
                SET status = 'Pendente', inativar = 1
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id' => $id
        ]);
    }


    public function findInativos(int $baseId, ?int $petId = null): array
    {
        $sql = "SELECT f.id, f.observacao AS descricao, f.total, f.data, f.pet_id, 
                       p.nome AS pet_nome, c.nome AS dono_nome, COALESCE(f.metodo_pagamento, 'Dinheiro') AS metodo_pagamento,
                       'venda' AS tipo
                  FROM {$_ENV['DBNAMETENANT']}.venda f
             LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
             LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON p.dono_id = c.id
                 WHERE f.estabelecimento_id = :baseId
                   AND f.status = 'Inativa'";

        $params = ['baseId' => $baseId];

        if ($petId) {
            $sql .= " AND f.pet_id = :petId";
            $params['petId'] = $petId;
        }

        $sql .= " ORDER BY f.data DESC";

        return $this->conn->fetchAllAssociative($sql, $params);
    }


    public function buscarAtivosPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM {$_ENV['DBNAMETENANT']}.venda 
                WHERE estabelecimento_id = :baseId 
                  AND pet_id = :petId
                  AND status NOT IN ('Inativa', 'Pendente', 'Carrinho')
                ORDER BY data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId' => $petId
        ]);
    }



}
