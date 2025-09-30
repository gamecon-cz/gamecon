<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByLogin(string $login): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.login = :login')
            ->setParameter('login', $login)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find users registered in the current year.
     *
     * @return User[]
     */
    public function findRegisteredThisYear(): array
    {
        $startOfYear = new \DateTime('first day of January this year');
        $endOfYear = new \DateTime('last day of December this year');

        return $this->createQueryBuilder('u')
            ->andWhere('u.registrovan BETWEEN :start AND :end')
            ->setParameter('start', $startOfYear)
            ->setParameter('end', $endOfYear)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by partial name match.
     *
     * @return User[]
     */
    public function findByPartialName(string $searchTerm): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.jmeno LIKE :term OR u.prijmeni LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('u.prijmeni', 'ASC')
            ->addOrderBy('u.jmeno', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active users (not dead mail).
     */
    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.mrtvyMail = false')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
