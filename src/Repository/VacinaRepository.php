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
        FROM {$_ENV['DBNAMETENANT']}.vacina v
        LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.id = v.pet_id
        LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
        WHERE v.estabelecimento_id = '{$baseId}'
        ORDER BY v.data_aplicacao DESC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function find($baseId, $id): ?array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.vacina WHERE estabelecimento_id = '{$baseId}' AND id = :id";
        return $this->conn->fetchAssociative($sql, ['id' => $id]) ?: null;
    }

    public function insert($baseId, Vacina $v): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.vacina (estabelecimento_id, pet_id, tipo, data_aplicacao, data_validade, lote)
        VALUES (:estabelecimento_id, :pet_id, :tipo, :data_aplicacao, :data_validade, :lote)";
        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'pet_id' => $v->getPetId(),
            'tipo' => $v->getTipo(),
            'data_aplicacao' => $v->getDataAplicacao()->format('Y-m-d'),
            'data_validade' => $v->getDataValidade()->format('Y-m-d'),
            'lote' => $v->getLote(),
        ]);
    }

    public function update($baseId, int $id, Vacina $v): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.vacina
        SET pet_id = :pet_id, tipo = :tipo, data_aplicacao = :data_aplicacao,
        data_validade = :data_validade, lote = :lote
        WHERE estabelecimento_id = '{$baseId}' AND id = :id";
        $this->conn->executeQuery($sql, [
            'id' => $id,
            'pet_id' => $v->getPetId(),
            'tipo' => $v->getTipo(),
            'data_aplicacao' => $v->getDataAplicacao()->format('Y-m-d'),
            'data_validade' => $v->getDataValidade()->format('Y-m-d'),
            'lote' => $v->getLote(),
        ]);
    }

    public function delete($baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM {$_ENV['DBNAMETENANT']}.vacina WHERE estabelecimento_id = '{$baseId}' AND id = :id", ['id' => $id]);
    }

    public function findAllPets($baseId): array
    {
        return $this->conn->fetchAllAssociative("SELECT p.id, CONCAT(p.nome, ' (', c.nome, ')') AS nome FROM {$_ENV['DBNAMETENANT']}.pet p LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id");
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