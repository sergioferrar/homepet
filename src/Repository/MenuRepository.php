<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 *
 * @method Menu|null find($id, $lockMode = null, $lockVersion = null)
 * @method Menu|null findOneBy(array $criteria, array $orderBy = null)
 * @method Menu[]    findAll()
 * @method Menu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function add(Menu $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Menu $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Atualiza menu — parâmetros nomeados substituem interpolação direta (SQL injection)
     */
    public function update(array $data): void
    {
        $sql = "UPDATE menu
                SET titulo    = :titulo,
                    parent    = :parent,
                    descricao = :descricao,
                    rota      = :rota,
                    status    = :status,
                    icone     = :icone,
                    modulo    = :modulo
                WHERE id = :id";

        $this->conn->executeStatement($sql, [
            'titulo'    => $data['titulo'],
            'parent'    => $data['parent'] ?? null,
            'descricao' => $data['descricao'],
            'rota'      => $data['rota'],
            'status'    => $data['status'],
            'icone'     => $data['icone'],
            'modulo'    => $data['modulo'],
            'id'        => $data['id'],
        ]);
    }

    /**
     * Lista todos os menus com seus módulos via LEFT JOIN
     */
    public function listarComModulos(): array
    {
        $sql = "SELECT m.id, m.titulo, m.parent, m.descricao, m.rota,
                       m.status, m.icone, m.modulo,
                       mod.titulo AS modulo_titulo
                FROM menu m
                LEFT JOIN modulo mod ON mod.id = m.modulo
                ORDER BY m.parent ASC, m.titulo ASC";

        return $this->conn->fetchAllAssociative($sql);
    }
}