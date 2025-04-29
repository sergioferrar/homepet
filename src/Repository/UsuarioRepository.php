<?php

namespace App\Repository;


use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Usuario>
 *
 * @method Usuario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Usuario|null findOneBy(array $criteria, array $orderBy = null)
 * @method Usuario[]    findAll()
 * @method Usuario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsuarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    /**
     * @var
     */
    private $conn;
    private $baseId;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);

        $this->conn = $registry->getManager()->getConnection();
    }

    public function setBaseId($baseId='u199209817_login')
    {
        $this->baseId = $baseId;
    }

    public function listaTodos(){
        $sql = "SELECT u.*, e.*, u.id AS userId, e.id AS shopId
            FROM usuario u
            LEFT JOIN estabelecimento e ON (e.id = u.petshop_id) 
            ";
        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }

    public function listaTodosPrivado($petshop_id){
        $sql = "SELECT u.*
            FROM usuario u
            WHERE u.petshop_id = $petshop_id
            ";
        $query = $this->conn->query($sql);
        return $query->fetchAll();
    }


    public function loadUserByIdentifier(string $usernameOrEmail): ?Usuario
    {
        $entityManager = $this->getEntityManager();
        return $entityManager->createQuery(
            "SELECT u
                FROM App\Entity\Usuario u
                WHERE email = :query"
        )
            ->setParameter('query', $usernameOrEmail)
            ->getOneOrNullResult();
    }

    /** @deprecated since Symfony 5.3 */
    public function loadUserByUsername(string $usernameOrEmail): ?User
    {
        return $this->loadUserByIdentifier($usernameOrEmail);
    }

    public function add(Usuario $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Usuario $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Usuario) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->add($user, true);
    }

    public function update(Usuario $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }



}