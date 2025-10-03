<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriptionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriptionLog>
 *
 * @method NewsletterSubscriptionLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterSubscriptionLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method NewsletterSubscriptionLog[]    findAll()
 * @method NewsletterSubscriptionLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class NewsletterSubscriptionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriptionLog::class);
    }

    public function save(NewsletterSubscriptionLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NewsletterSubscriptionLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
