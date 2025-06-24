<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use App\Entity\ProntuarioPet;

class ProntuarioPetRepository
{
    private $conn;
    public function __construct(Connection $conn) { $this->conn = $conn; }

    public function salvar(int $baseId, ProntuarioPet $r)
    {
        $this->conn->insert("u199209817_{$baseId}.prontuariopet", [
            'pet_id' => $r->getPetId(),
            'data' => $r->getData()->format('Y-m-d H:i:s'),
            'tipo' => $r->getTipo(),
            'descricao' => $r->getDescricao(),
            'anexo' => $r->getAnexo(),
            'criado_em' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    public function listarPorPet(int $baseId, int $petId): array
    {
        return $this->conn->fetchAllAssociative("SELECT * 
            FROM u199209817_{$baseId}.prontuariopet 
            WHERE pet_id = ? 
            ORDER BY data DESC", [$petId]);
    }
}