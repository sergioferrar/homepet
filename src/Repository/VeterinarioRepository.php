<?php

namespace App\Repository;

use App\Entity\Veterinario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Veterinario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Veterinario[]    findAll()
 */
class VeterinarioRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Veterinario::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Veterinários de um estabelecimento com agenda resumida
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        $sql = "SELECT v.id, v.nome, v.especialidade, v.crmv, v.telefone, v.email, v.status,
                       COUNT(DISTINCT c.id) AS total_consultas
                FROM veterinario v
                LEFT JOIN consulta c ON c.veterinario_id = v.id
                           AND c.estabelecimento_id = v.estabelecimento_id
                WHERE v.estabelecimento_id = :estabelecimentoId
                GROUP BY v.id, v.nome, v.especialidade, v.crmv, v.telefone, v.email, v.status
                ORDER BY v.nome ASC";

        return $this->conn->fetchAllAssociative($sql, ['estabelecimentoId' => $estabelecimentoId]);
    }

    /**
     * Veterinários por especialidade
     */
    public function findByEspecialidade(string $especialidade): array
    {
        $sql = "SELECT v.id, v.nome, v.especialidade, v.crmv, v.telefone, v.email,
                       e.razaoSocial AS estabelecimento_nome
                FROM veterinario v
                LEFT JOIN estabelecimento e ON e.id = v.estabelecimento_id
                WHERE v.especialidade = :especialidade
                ORDER BY v.nome ASC";

        return $this->conn->fetchAllAssociative($sql, ['especialidade' => $especialidade]);
    }

    /**
     * Busca por nome parcial com dados do estabelecimento
     */
    public function findByNomeLike(string $nome): array
    {
        $sql = "SELECT v.id, v.nome, v.especialidade, v.crmv,
                       e.razaoSocial AS estabelecimento_nome
                FROM veterinario v
                LEFT JOIN estabelecimento e ON e.id = v.estabelecimento_id
                WHERE LOWER(v.nome) LIKE :nome
                ORDER BY v.nome ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'nome' => '%' . strtolower($nome) . '%',
        ]);
    }

    /**
     * Conta total de veterinários
     */
    public function countVeterinarios(): int
    {
        return (int) $this->conn->fetchOne("SELECT COUNT(id) FROM veterinario");
    }

    /**
     * Veterinários com suas internações recentes, em uma única query com LEFT JOIN
     * Evita o problema N+1 do leftJoin do ORM
     */
    public function findComExecucoesRecentes(int $estabelecimentoId, int $limite = 10): array
    {
        $sql = "SELECT v.id AS vet_id, v.nome AS vet_nome, v.especialidade,
                       ie.id AS exec_id, ie.data_execucao, ie.descricao AS exec_descricao,
                       i.motivo AS internacao_motivo,
                       p.nome AS pet_nome
                FROM veterinario v
                LEFT JOIN internacao_execucao ie ON ie.veterinario_id = v.id
                LEFT JOIN internacao i ON i.id = ie.internacao_id
                LEFT JOIN pet p ON p.id = i.pet_id
                WHERE v.estabelecimento_id = :estabelecimentoId
                ORDER BY ie.data_execucao DESC
                LIMIT :limite";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimentoId', $estabelecimentoId);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Busca um veterinário por ID com dados completos
     */
    public function findByIdCompleto(int $vetId, int $estabelecimentoId): ?array
    {
        $sql = "SELECT v.id, v.nome, v.especialidade, v.crmv, v.telefone, v.email, v.status,
                       COUNT(DISTINCT c.id) AS total_consultas,
                       COUNT(DISTINCT i_exec.id) AS total_procedimentos
                FROM veterinario v
                LEFT JOIN consulta c ON c.veterinario_id = v.id AND c.estabelecimento_id = v.estabelecimento_id
                LEFT JOIN internacao_execucao i_exec ON i_exec.veterinario_id = v.id
                WHERE v.id = :vetId AND v.estabelecimento_id = :estab
                GROUP BY v.id, v.nome, v.especialidade, v.crmv, v.telefone, v.email, v.status
                LIMIT 1";

        $result = $this->conn->fetchAssociative($sql, [
            'vetId' => $vetId,
            'estab' => $estabelecimentoId,
        ]);
        return $result ?: null;
    }

    /**
     * Salva veterinário
     */
    public function salvar(int $estabelecimentoId, Veterinario $v): int
    {
        $sql = "INSERT INTO veterinario (estabelecimento_id, nome, especialidade, crmv, telefone, email, status)
                VALUES (:estab, :nome, :especialidade, :crmv, :telefone, :email, :status)";

        $this->conn->executeQuery($sql, [
            'estab'        => $estabelecimentoId,
            'nome'         => $v->getNome(),
            'especialidade'=> $v->getEspecialidade(),
            'crmv'         => $v->getCrmv(),
            'telefone'     => $v->getTelefone(),
            'email'        => $v->getEmail(),
            'status'       => $v->getStatus() ?? 'ativo',
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Atualiza veterinário
     */
    public function atualizar(int $estabelecimentoId, Veterinario $v): void
    {
        $sql = "UPDATE veterinario
                SET nome = :nome, especialidade = :especialidade, crmv = :crmv,
                    telefone = :telefone, email = :email, status = :status
                WHERE id = :id AND estabelecimento_id = :estab";

        $this->conn->executeQuery($sql, [
            'nome'         => $v->getNome(),
            'especialidade'=> $v->getEspecialidade(),
            'crmv'         => $v->getCrmv(),
            'telefone'     => $v->getTelefone(),
            'email'        => $v->getEmail(),
            'status'       => $v->getStatus() ?? 'ativo',
            'id'           => $v->getId(),
            'estab'        => $estabelecimentoId,
        ]);
    }
}