<?php
namespace App\Repository;

use App\Entity\HospedagemCaes;
use Doctrine\DBAL\Connection;

class HospedagemCaesRepository
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function insert($baseId, HospedagemCaes $h): void
    {
        $sql = "INSERT INTO homepet_{$baseId}.hospedagem_caes (cliente_id, pet_id, data_entrada, data_saida, valor, observacoes)
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
        $sql = "INSERT INTO homepet_{$baseId}.financeiro (descricao, valor, data, pet_id, pet_nome)
                VALUES (:descricao, :valor, NOW(), :pet_id, (SELECT nome FROM homepet_{$baseId}.pet WHERE id = :pet_id LIMIT 1))";
        $this->conn->executeQuery($sql, [
            'descricao' => 'Hospedagem do Pet',
            'valor' => $h->getValor(),
            'pet_id' => $h->getPetId(),
        ]);
    }

    public function getClientes($baseId)
    {
        return $this->conn->fetchAllAssociative("SELECT * FROM homepet_{$baseId}.cliente");
    }

    public function getPets($baseId)
    {
        $sql = "SELECT p.id, p.nome, c.nome AS dono_nome
                FROM homepet_{$baseId}.pet p
                LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id";

        return $this->conn->fetchAllAssociative($sql);
    }



    public function findAll($baseId): array
    {
        $sql = "SELECT h.id, h.cliente_id, c.nome AS cliente_nome,
                       h.pet_id, p.nome AS pet_nome,
                       h.data_entrada, h.data_saida, h.valor, h.observacoes
                FROM homepet_{$baseId}.hospedagem_caes h
                LEFT JOIN homepet_{$baseId}.cliente c ON c.id = h.cliente_id
                LEFT JOIN homepet_{$baseId}.pet p ON p.id = h.pet_id
                ORDER BY h.data_entrada DESC";

        return $this->conn->fetchAllAssociative($sql);
    }


    public function findById($baseId, int $id): ?array
    {
        $sql = "SELECT * FROM homepet_{$baseId}.hospedagem_caes WHERE id = :id";
        return $this->conn->fetchAssociative($sql, ['id' => $id]) ?: null;
    }

    public function delete($baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM homepet_{$baseId}.hospedagem_caes WHERE id = :id", ['id' => $id]);
    }
}
