<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoogleDriveDir;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleDriveDir>
 *
 * @method GoogleDriveDir|null find($id, $lockMode = null, $lockVersion = null)
 * @method GoogleDriveDir|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method GoogleDriveDir[]    findAll()
 * @method GoogleDriveDir[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class GoogleDriveDirRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleDriveDir::class);
    }
}
