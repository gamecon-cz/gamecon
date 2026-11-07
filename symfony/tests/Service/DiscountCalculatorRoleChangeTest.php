<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Service\DiscountCalculator;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * A role change reprices the cart in the very request that made it, through the same
 * calculator that already priced things before the change.
 */
class DiscountCalculatorRoleChangeTest extends AbstractDatabaseKernelTestCase
{
    private const PRAVO_JIDLO_ZDARMA = 1005;

    public function testPriceFollowsARoleGrantedWithinTheRequest(): void
    {
        $calculator = static::getContainer()->get(DiscountCalculator::class);
        $ucastnik = $this->entityManager()->find(User::class, $this->ucastnikVSql('sleva_po_zmene_role_'));
        self::assertNotNull($ucastnik);
        $obed = (new Product())->setName('Oběd')->setCode('obed_test')->setCurrentPrice('180.00')
            ->addTag((new ProductTag())->setCode(ProductTagCode::JIDLO->value)->setName('Jídlo'));

        self::assertSame('180.00', $calculator->calculateDiscount($obed, $ucastnik, ROCNIK)['finalPrice']);

        $idRole = $this->connection()->fetchOne(
            'SELECT prava_role.id_role
             FROM prava_role
             INNER JOIN role_seznam ON role_seznam.id_role = prava_role.id_role
             WHERE prava_role.id_prava = :pravo AND role_seznam.rocnik_role IN (:rok, -1)
             LIMIT 1',
            [
                'pravo' => self::PRAVO_JIDLO_ZDARMA,
                'rok'   => ROCNIK,
            ],
        );
        self::assertNotFalse($idRole, 'Some role must grant free meals');
        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
            [
                'uzivatel' => $ucastnik->getId(),
                'role'     => $idRole,
            ],
        );

        self::assertSame('0.00', $calculator->calculateDiscount($obed, $ucastnik, ROCNIK)['finalPrice']);
    }
}
