<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoogleApiUserToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleApiUserToken>
 *
 * @method GoogleApiUserToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method GoogleApiUserToken|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method GoogleApiUserToken[]    findAll()
 * @method GoogleApiUserToken[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class GoogleApiUserTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleApiUserToken::class);
    }
}
