<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ProductTagCode;
use App\Tests\AbstractDatabaseKernelTestCase;

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

    public function testCategoriesAreTheSuccessorOfTheOldTypColumn(): void
    {
        // Seven categories, one per product, mirroring typ 1–7. MIKINA is deliberately
        // not among them: it is carried in addition to PREDMET, not instead of it.
        $this->assertCount(7, ProductTagCode::categories());
        $this->assertFalse(ProductTagCode::MIKINA->isCategory());
        $this->assertTrue(ProductTagCode::PREDMET->isCategory());
    }
}
