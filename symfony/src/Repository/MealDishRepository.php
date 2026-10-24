<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MealDish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MealDish>
 *
 * @method MealDish|null find($id, $lockMode = null, $lockVersion = null)
 * @method MealDish|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method MealDish[]    findAll()
 * @method MealDish[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class MealDishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MealDish::class);
    }

    /**
     * @return MealDish[] the year's whole menu, in the order the menu lists it
     */
    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('dish')
            ->where('dish.year = :year')
            ->setParameter('year', $year)
            ->orderBy('dish.position', 'ASC')
            ->addOrderBy('dish.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
