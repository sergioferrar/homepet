<?php
namespace App\Repository;

use App\Entity\ProntuarioPet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProntuarioPetRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProntuarioPet::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

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
        return $this->conn->fetchAllAssociative("SELECT p.id AS pet_id, * 
            FROM prontuariopet 
            WHERE pet_id = ? 
            ORDER BY data DESC", [$petId]);
    }
}