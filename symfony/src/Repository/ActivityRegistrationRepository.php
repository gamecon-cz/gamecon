<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityRegistration>
 *
 * @method ActivityRegistration|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityRegistration|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityRegistration[]    findAll()
 * @method ActivityRegistration[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityRegistration::class);
    }
}
