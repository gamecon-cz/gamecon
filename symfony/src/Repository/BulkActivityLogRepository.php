<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BulkActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BulkActivityLog>
 *
 * @method BulkActivityLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method BulkActivityLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method BulkActivityLog[]    findAll()
 * @method BulkActivityLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class BulkActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BulkActivityLog::class);
    }

    public function save(BulkActivityLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BulkActivityLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
