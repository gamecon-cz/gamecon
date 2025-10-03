<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemSettingLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemSettingLog>
 *
 * @method SystemSettingLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemSettingLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method SystemSettingLog[]    findAll()
 * @method SystemSettingLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class SystemSettingLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemSettingLog::class);
    }

    public function save(SystemSettingLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SystemSettingLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
