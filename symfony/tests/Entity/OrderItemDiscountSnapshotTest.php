<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\OrderItem;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Sloupec `discount_snapshot` v databázi je od migrace slev, ale entita ho dlouho
 * nemapovala, takže `doctrine:schema:validate` ho hlásil jako přebytečný a Doctrine by ho
 * při generování schématu zahodil. Uložit se musí jako JSON a vrátit jako pole.
 */
class OrderItemDiscountSnapshotTest extends AbstractDatabaseKernelTestCase
{
    /**
     * @test
     */
    public function snapshotSeUlozyAPrecteJakoPole(): void
    {
        $metadata = $this->entityManager()->getClassMetadata(OrderItem::class);

        self::assertTrue(
            $metadata->hasField('discountSnapshot'),
            'Entita sloupec discount_snapshot nemapuje',
        );
        self::assertSame(
            'discount_snapshot',
            $metadata->getColumnName('discountSnapshot'),
        );
        self::assertSame(
            'json',
            $metadata->getTypeOfField('discountSnapshot'),
            'Snapshot je strukturovaný záznam pravidla, ne řetězec',
        );
        self::assertTrue(
            $metadata->isNullable('discountSnapshot'),
            'Nákup bez slevy nemá co zaznamenat',
        );
    }
}
