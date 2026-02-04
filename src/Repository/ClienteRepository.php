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
            FROM {$_ENV['DBNAMETENANT']}.agendamento a
            INNER JOIN {$_ENV['DBNAMETENANT']}.agendamento_pet_servico aps ON aps.agendamentoId = a.id
            INNER JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.id = aps.petId
            INNER JOIN {$_ENV['DBNAMETENANT']}.servico s ON s.id = aps.servicoId
            WHERE a.estabelecimento_id = '{$baseId}' AND p.dono_id = :clienteId
            ORDER BY a.data DESC, a.horaChegada DESC";

        $stmt = $this->conn->executeQuery($sql, ['clienteId' => $clienteId]);
        return $stmt->fetchAllAssociative();
    }

    public function localizaTodosClientePorID($baseId, $clienteId){
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.cliente 
            WHERE estabelecimento_id = '{$baseId}' AND id='{$clienteId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

    public function localizaTodosCliente($baseId)
    {
        $sql = "SELECT 
                    id,
                    COALESCE(nome, 'Sem nome') as nome,
                    COALESCE(email, '-') as email,
                    COALESCE(telefone, '-') as telefone
                FROM {$_ENV['DBNAMETENANT']}.cliente 
                WHERE estabelecimento_id = '{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetchAllAssociative();
    }


    public function save($baseId, array $clientData): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.cliente
        (estabelecimento_id, nome, cpf, email, telefone, rua, numero, complemento, bairro, cidade, whatsapp)
        VALUES  (:estabelecimento_id, :nome, :cpf, :email, :telefone, :rua, :numero, :complemento, :bairro, :cidade, :whatsapp)";

        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'nome' => $clientData['nome'],
            'cpf' => $clientData['cpf'] ?? null,
            'email' => $clientData['email'],
            'telefone' => $clientData['telefone'],
            'rua' => $clientData['rua'],
            'numero' => $clientData['numero'],
            'complemento' => $clientData['complemento'],
            'bairro' => $clientData['bairro'],
            'cidade' => $clientData['cidade'],
            'whatsapp' => $clientData['whatsapp'],
        ]);
    }

    public function update($baseId, array $clienteData): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.cliente 
                SET nome = :nome, cpf = :cpf, email = :email, telefone = :telefone, 
                    rua = :rua, numero = :numero, complemento = :complemento, 
                    bairro = :bairro, cidade = :cidade, whatsapp = :whatsapp
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'nome' => $clienteData['nome'],
            'cpf' => $clienteData['cpf'] ?? null,
            'email' => $clienteData['email'],
            'telefone' => $clienteData['telefone'],
            'rua' => $clienteData['rua'],
            'numero' => $clienteData['numero'],
            'complemento' => $clienteData['complemento'],
            'bairro' => $clienteData['bairro'],
            'cidade' => $clienteData['cidade'],
            'whatsapp' => $clienteData['whatsapp'],
            'baseId' => $baseId,
            'id' => $clienteData['id']
        ]);
    }

    public function delete($baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.cliente WHERE estabelecimento_id = '{$baseId}' AND  id = :id";
        $this->conn->executeQuery($sql, ['id' => $id]);
    }

    public function search($baseId, string $term = null): array
    {
        $where = "WHERE estabelecimento_id = '{$baseId}'";
        $params = [];

        if ($term) {
            $where .= " AND (nome LIKE :term OR cpf LIKE :term OR email LIKE :term OR telefone LIKE :term OR rua LIKE :term)";
            $params['term'] = "%$term%";
        }

        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.cliente $where";
        // die($sql);
        $stmt = $this->conn->executeQuery($sql, $params);
        return $stmt->fetchAllAssociative();
    }

    public function getLastInsertedId(): int
    {
        return $this->conn->lastInsertId();
    }

    public function findClienteByPetId($baseId, int $petId): ?array
    {
        $sql = "SELECT c.id, c.nome, c.email, c.telefone, c.whatsapp, c.rua,
                    c.numero, c.complemento, c.bairro, c.cidade, c.cep
                FROM {$_ENV['DBNAMETENANT']}.cliente c
                INNER JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.dono_id = c.id
                WHERE c.estabelecimento_id = '{$baseId}' AND  p.id = :petId";

        $stmt = $this->conn->executeQuery($sql, ['petId' => $petId]);
        return $stmt->fetchAssociative() ?: null;
    }

    public function findPetsByCliente($baseId, int $clienteId): array
        {
            $sql = "SELECT 
                        id,
                        nome,
                        especie,
                        sexo,
                        raca,
                        porte,
                        idade,
                        observacoes
                    FROM {$_ENV['DBNAMETENANT']}.pet 
                    WHERE dono_id = :id AND estabelecimento_id = '{$baseId}'";
            return $this->conn->fetchAllAssociative($sql, ['id' => $clienteId]);
        }

    public function findUltimosAgendamentos($baseId, int $clienteId): array
    {
        $sql = "SELECT 
                    a.data, 
                    s.nome as servico_nome, 
                    s.valor as servico_valor, 
                    p.nome as pet
                FROM {$_ENV['DBNAMETENANT']}.agendamento a
                INNER JOIN {$_ENV['DBNAMETENANT']}.agendamento_pet_servico aps ON aps.agendamentoId = a.id
                INNER JOIN {$_ENV['DBNAMETENANT']}.pet p ON aps.petId = p.id
                INNER JOIN {$_ENV['DBNAMETENANT']}.servico s ON aps.servicoId = s.id
                WHERE p.dono_id = :id AND a.estabelecimento_id = '{$baseId}'
                ORDER BY a.data DESC, a.horaChegada DESC
                LIMIT 5";

        return $this->conn->fetchAllAssociative($sql, ['id' => $clienteId]);
    }


    public function hasFinanceiroPendente($baseId, int $clienteId): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM {$_ENV['DBNAMETENANT']}.financeiropendente f
                JOIN {$_ENV['DBNAMETENANT']}.pet p ON f.pet_id = p.id
                WHERE f.estabelecimento_id = :baseId AND p.dono_id = :id";

        $total = $this->conn->fetchOne($sql, [
            'baseId' => $baseId,
            'id' => $clienteId,
        ]);

        return $total > 0;
    }

    public function findClientesComPet($baseId): array
    {
        $sql = "SELECT c.id, c.nome, c.telefone
                FROM {$_ENV['DBNAMETENANT']}.cliente c
                WHERE c.estabelecimento_id = :baseId
                AND EXISTS (
                    SELECT 1 FROM {$_ENV['DBNAMETENANT']}.pet p
                    WHERE p.dono_id = c.id
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();
        $clientes = $result->fetchAllAssociative();

        // Enriquecer com os pets
        foreach ($clientes as &$cliente) {
            $cliente['pets'] = $this->listarPetsDoCliente($baseId, $cliente['id']);
        }

        return $clientes;
    }

    public function listarPetsDoCliente($baseId, $clienteId): array
    {
        $sql = "SELECT id, nome, raca FROM {$_ENV['DBNAMETENANT']}.pet
                WHERE dono_id = :clienteId AND estabelecimento_id = :baseId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('clienteId', $clienteId);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function countTotalDono($baseId): int
    {
        $sql = "SELECT COUNT(*) FROM {$_ENV['DBNAMETENANT']}.cliente WHERE estabelecimento_id = :baseId";
        return (int) $this->conn->fetchOne($sql, ['baseId' => $baseId]);
    }

    public function findByNomeLike($baseId, string $query): array
    {
        $sql = "SELECT * 
            FROM {$_ENV['DBNAMETENANT']}.cliente 
            WHERE nome like '%{$query}%' AND estabelecimento_id = {$baseId}";
        $query = $this->conn->executeQuery($sql);
        return $this->conn->fetchAllAssociative();
    }

}
