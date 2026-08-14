<?php

namespace App\Repository;

use App\Entity\Plano;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plano>
 *
 * @method Plano|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plano|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plano[]    findAll()
 * @method Plano[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanoRepository extends ServiceEntityRepository
{
    private $conn;
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plano::class);
        $this->conn = $this->getEntityManager()->getConnection();
        $this->em   = $this->getEntityManager();
    }

    /**
     * Lista todos os planos com contagem de estabelecimentos.
     * Usado no painel admin.
     */
    public function listaPlanos(): array
    {
        return $this->em->createQuery(
            'SELECT p FROM App\Entity\Plano p ORDER BY p.id DESC'
        )->getResult();
    }

    /**
     * Lista planos ativos para exibição na landing page.
     */
    public function listaPlanosHome(): array
    {
        return $this->em->createQuery(
            "SELECT p FROM App\Entity\Plano p WHERE p.status = 'Ativo' ORDER BY p.valor ASC"
        )->getResult();
    }

    /**
     * Retorna um único plano pelo ID.
     */
    public function verPlano(int $planoId): ?Plano
    {
        return $this->em->getRepository(Plano::class)->find($planoId);
    }

    /**
     * Persiste um novo plano.
     */
    public function add(Plano $entity, bool $flush = false): void
    {
        $this->em->persist($entity);
        if ($flush) {
            $this->em->flush();
        }
    }

    /**
     * Remove um plano.
     */
    public function remove(Plano $entity, bool $flush = false): void
    {
        $this->em->remove($entity);
        if ($flush) {
            $this->em->flush();
        }
    }
}
