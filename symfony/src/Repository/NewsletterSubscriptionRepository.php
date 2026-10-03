<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscription>
 *
 * @method NewsletterSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterSubscription|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method NewsletterSubscription[]    findAll()
 * @method NewsletterSubscription[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class NewsletterSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscription::class);
    }
}
