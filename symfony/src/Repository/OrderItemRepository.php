<?php

declare(strict_types=1);

namespace App\Repository;

use App\Discount\DiscountableItem;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductTagCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * Find purchases by customer and year
     *
     * @return OrderItem[]
     */
    public function findByCustomerAndYear(User $customer, int $year): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.customer = :customer')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->orderBy('oi.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * S variantou počítá jen tu jednu velikost — zásoba se doprodává per varianta, takže
     * mřížka potřebuje obojí: součet za produkt i počet za konkrétní velikost.
     */
    public function countCustomerPurchases(User $customer, Product $product, int $year, ?ProductVariant $variant = null): int
    {
        $dotaz = $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.customer = :customer')
            ->andWhere('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('product', $product)
            ->setParameter('year', $year);

        if ($variant !== null) {
            $dotaz->andWhere('oi.variant = :variant')
                ->setParameter('variant', $variant);
        }

        return (int) $dotaz->getQuery()->getSingleScalarResult();
    }

    /**
     * Letošní nákupy zákazníka v podobě, v jaké je pravidla umí spárovat.
     *
     * Čte se ze snapshotu na položce, ne z produktu: nákup zachycuje kód a tagy, jaké měl
     * produkt v době koupě, takže pozdější přeštítkování nezmění, co se tehdy spotřebovalo.
     *
     * `accommodationDay` zůstává null — nároky na konkrétní noc se nespotřebovávají
     * (`DiscountScope::isConsumable()`), takže se do kvóty nepočítají a den je jim jedno.
     *
     * @return DiscountableItem[]
     */
    public function discountableCustomerPurchases(User $customer, int $year): array
    {
        $rows = $this->createQueryBuilder('orderItem')
            ->select('orderItem.id', 'orderItem.productCode', 'orderItem.productTags', 'orderItem.originalPrice', 'orderItem.purchasePrice')
            ->where('orderItem.customer = :customer')
            ->andWhere('orderItem.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->getQuery()
            ->getArrayResult();

        $items = [];
        foreach ($rows as $row) {
            $tags = [];
            // Sloupec je nullable a getArrayResult() vrací syrovou hodnotu, takže se
            // výchozí `= []` z entity neuplatní; produkt bez tagů by shodil mřížku.
            foreach ($row['productTags'] ?? [] as $code) {
                $tag = ProductTagCode::tryFrom($code);
                if ($tag !== null) {
                    $tags[] = $tag;
                }
            }

            // Řadí se podle ceny PŘED slevou — pořadí musí odpovídat tomu, v jakém se
            // nároky rozdávaly, a zlevněná položka by se jinak tvářila jako nejlevnější.
            $items[] = new DiscountableItem(
                key: $row['id'],
                productCode: $row['productCode'] ?? '',
                price: (float) ($row['originalPrice'] ?? $row['purchasePrice']),
                tags: $tags,
            );
        }

        return $items;
    }

    /**
     * Counts both sale paths, since OrderItem maps to shop_nakupy. Pair it only with
     * {@see ProductRepository::producedQuantityByVariantCode()}, never with
     * remaining_quantity — CapacityManager already decrements that for new-cart sales.
     *
     * @param int[] $variantIds
     *
     * @return array<int, int>
     */
    public function countSoldByVariant(array $variantIds, int $year): array
    {
        if ($variantIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.variant) AS variantId', 'COUNT(oi.id) AS sold')
            ->where('oi.variant IN (:variantIds)')
            ->andWhere('oi.year = :year')
            ->groupBy('oi.variant')
            ->setParameter('variantIds', $variantIds)
            ->setParameter('year', $year)
            ->getQuery()
            ->getArrayResult();

        $prodano = [];
        foreach ($rows as $row) {
            $prodano[(int) $row['variantId']] = (int) $row['sold'];
        }

        return $prodano;
    }

    /**
     * Added back into the remaining count, as legacy does, so the last bed still reads as
     * available once it is yours instead of sold out under its own ticked checkbox.
     *
     * @param int[] $variantIds
     *
     * @return array<int, int>
     */
    public function countHeldByCustomer(array $variantIds, User $customer, int $year): array
    {
        if ($variantIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.variant) AS variantId', 'COUNT(oi.id) AS held')
            ->where('oi.variant IN (:variantIds)')
            ->andWhere('oi.year = :year')
            ->andWhere('oi.customer = :customer')
            ->groupBy('oi.variant')
            ->setParameter('variantIds', $variantIds)
            ->setParameter('year', $year)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getArrayResult();

        $drzeno = [];
        foreach ($rows as $row) {
            $drzeno[(int) $row['variantId']] = (int) $row['held'];
        }

        return $drzeno;
    }
}
