<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ProductTagCode;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Shop\TypPredmetu;

/**
 * The enum is only worth anything while it matches product_tag. A case whose code is
 * not in the table matches no product, so any rule or query using it silently returns
 * nothing — the failure the enum exists to prevent, just moved one level up.
 *
 * Checked in both directions: a case with no row is a typo, and a row with no case is
 * a tag somebody added that the code cannot address.
 */
class ProductTagCodeTest extends AbstractDatabaseKernelTestCase
{
    /**
     * @return string[]
     */
    private function storedTagCodes(): array
    {
        $codes = $this->connection()->fetchFirstColumn('SELECT code FROM product_tag');
        $this->assertNotEmpty($codes, 'Žádné tagy v databázi — test by prošel naprázdno');

        return $codes;
    }

    public function testEveryCaseExistsInTheDatabase(): void
    {
        $stored = $this->storedTagCodes();

        foreach (ProductTagCode::cases() as $tag) {
            $this->assertContains(
                $tag->value,
                $stored,
                sprintf(
                    'ProductTagCode::%s má kód "%s", který v product_tag není. '
                    . 'Cokoli podle něj filtruje tiše nevrátí nic.',
                    $tag->name,
                    $tag->value,
                ),
            );
        }
    }

    public function testEveryStoredTagHasACase(): void
    {
        $missing = array_diff(
            $this->storedTagCodes(),
            array_column(ProductTagCode::cases(), 'value'),
        );

        $this->assertSame(
            [],
            array_values($missing),
            'Tagy v product_tag bez odpovídajícího ProductTagCode — kód je nedokáže adresovat.',
        );
    }

    public function testLegacyTypMapsToTheCategoryThatReplacedIt(): void
    {
        // The compatibility view still reports typ, so anything reading a legacy row
        // has to translate. A wrong number here silently discounts the wrong kind of
        // thing — meals priced as shirts.
        $this->assertSame(ProductTagCode::PREDMET, ProductTagCode::fromLegacyTyp(TypPredmetu::PREDMET));
        $this->assertSame(ProductTagCode::UBYTOVANI, ProductTagCode::fromLegacyTyp(TypPredmetu::UBYTOVANI));
        $this->assertSame(ProductTagCode::TRICKO, ProductTagCode::fromLegacyTyp(TypPredmetu::TRICKO));
        $this->assertSame(ProductTagCode::JIDLO, ProductTagCode::fromLegacyTyp(TypPredmetu::JIDLO));
        $this->assertSame(ProductTagCode::VSTUPNE, ProductTagCode::fromLegacyTyp(TypPredmetu::VSTUPNE));
        $this->assertSame(ProductTagCode::PARCON, ProductTagCode::fromLegacyTyp(TypPredmetu::PARCON));
        $this->assertSame(
            ProductTagCode::PROPLACENI_BONUSU,
            ProductTagCode::fromLegacyTyp(TypPredmetu::PROPLACENI_BONUSU),
        );
    }

    public function testEveryCategoryHasALegacyTyp(): void
    {
        // Both directions, so a category added without a typ cannot go unnoticed.
        $mapped = array_filter(
            array_map(static fn (int $typ): ?ProductTagCode => ProductTagCode::fromLegacyTyp($typ), range(1, 7)),
        );

        $this->assertEqualsCanonicalizing(ProductTagCode::categories(), array_values($mapped));
    }

    public function testUnknownLegacyTypIsNull(): void
    {
        $this->assertNull(ProductTagCode::fromLegacyTyp(0));
        $this->assertNull(ProductTagCode::fromLegacyTyp(99));
    }

    public function testCategoriesAreTheSuccessorOfTheOldTypColumn(): void
    {
        // Seven categories, one per product, mirroring typ 1–7. MIKINA is deliberately
        // not among them: it is carried in addition to PREDMET, not instead of it.
        $this->assertCount(7, ProductTagCode::categories());
        $this->assertFalse(ProductTagCode::MIKINA->isCategory());
        $this->assertTrue(ProductTagCode::PREDMET->isCategory());
    }
}
