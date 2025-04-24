<?php

namespace App\Repository;

use App\Entity\Vacina;
use Doctrine\DBAL\Connection;

class VacinaRepository
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function findAll($baseId): array
    {
        $sql = "SELECT v.*, CONCAT(p.nome, ' (', c.nome, ')') AS pet_nome
                FROM u199209817_{$baseId}.vacina v
                LEFT JOIN u199209817_{$baseId}.pet p ON p.id = v.pet_id
                LEFT JOIN u199209817_{$baseId}.cliente c ON c.id = p.dono_id
                ORDER BY v.data_aplicacao DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function find($baseId, $id): ?array
    {
        $sql = "SELECT * FROM u199209817_{$baseId}.vacina WHERE id = :id";
        return $this->conn->fetchAssociative($sql, ['id' => $id]) ?: null;
    }

    public function insert($baseId, Vacina $v): void
    {
        $sql = "INSERT INTO u199209817_{$baseId}.vacina (pet_id, tipo, data_aplicacao, data_validade, lote)
                VALUES (:pet_id, :tipo, :data_aplicacao, :data_validade, :lote)";
        $this->conn->executeQuery($sql, [
            'pet_id'         => $v->getPetId(),
            'tipo'           => $v->getTipo(),
            'data_aplicacao' => $v->getDataAplicacao()->format('Y-m-d'),
            'data_validade'  => $v->getDataValidade()->format('Y-m-d'),
            'lote'           => $v->getLote(),
        ]);
    }

    public function update($baseId, int $id, Vacina $v): void
    {
        $sql = "UPDATE u199209817_{$baseId}.vacina
                SET pet_id = :pet_id, tipo = :tipo, data_aplicacao = :data_aplicacao,
                    data_validade = :data_validade, lote = :lote
                WHERE id = :id";
        $this->conn->executeQuery($sql, [
            'id'             => $id,
            'pet_id'         => $v->getPetId(),
            'tipo'           => $v->getTipo(),
            'data_aplicacao' => $v->getDataAplicacao()->format('Y-m-d'),
            'data_validade'  => $v->getDataValidade()->format('Y-m-d'),
            'lote'           => $v->getLote(),
        ]);
    }

    public function delete($baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM u199209817_{$baseId}.vacina WHERE id = :id", ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        return $this->conn->fetchAllAssociative("SELECT p.id, CONCAT(p.nome, ' (', c.nome, ')') AS nome FROM u199209817_{$baseId}.pet p LEFT JOIN u199209817_{$baseId}.cliente c ON c.id = p.dono_id");
    }

    public function getVacinasSugeridas(): array
    {
        return [
            'Cães' => [
                'V8 (Polivalente)', 'V10 (Polivalente)', 'V12 (Polivalente)', 'Raiva', 'Tosse dos Canis (Bordetella)',
                'Giárdia', 'Leishmaniose', 'Influenza Canina H3N8', 'Influenza Canina H3N2',
                'Influenza Canina Combinada (H3N8 + H3N2)', 'Doença de Lyme', 'Coronavírus Canino',
                'Babesiose', 'Tétano'
            ],
            'Gatos' => [
                'V3 (Trivalente)', 'V4 (Tetravalente)', 'V5 (Pentavalente)', 'Raiva', 'Leucemia Felina (FeLV)',
                'Peritonite Infecciosa Felina (FIP)', 'Bordetella bronchiseptica', 'FIV',
                'Dermatófitos', 'Feline Influenza'
            ]
        ];
    }
}