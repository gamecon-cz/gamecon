<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserRoleText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRoleText>
 *
 * @method UserRoleText|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRoleText|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserRoleText[]    findAll()
 * @method UserRoleText[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRoleTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRoleText::class);
    }

    public function save(UserRoleText $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserRoleText $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
