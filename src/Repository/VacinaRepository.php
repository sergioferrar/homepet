<?php

namespace App\Repository;

use App\Entity\Vacina;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vacina>
 */
class VacinaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacina::class);
    }

    public function findByPet(int $petId, int $estabelecimentoId): array
    {

        return $this->createQueryBuilder('v')
        ->andWhere('v.pet_id = :petId')
        ->andWhere('v.estabelecimento_id = :estabId')
        ->setParameter('petId', $petId)
        ->setParameter('estabId', $estabelecimentoId)
        ->orderBy('v.dataAplicacao', 'DESC')
        ->getQuery()
        ->getResult();

    }

    public function addTimelineItems(int $petId, int $estabelecimentoId): array
    {
        $vacinas = $this->findByPet($petId, $estabelecimentoId);

        $items = [];
        foreach ($vacinas as $v) {
            $items[] = [
                'data'      => $v->getDataAplicacao(),
                'tipo'      => 'Vacina',
                'descricao' => sprintf(
                    "%s - Lote: %s | Validade: %s",
                    $v->getTipo(),
                    $v->getLote() ?: '—',
                    $v->getDataValidade() ? $v->getDataValidade()->format('d/m/Y') : '—'
                ),
            ];
        }

        return $items;
    }
}
