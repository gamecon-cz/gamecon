<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Discount\DiscountRuleLoader;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * A rule that holds nothing of a year is stored once, for every year. Only the two rules that name this
 * year's free dice and badge have a row per year, so that a new year does not inherit last year's items.
 */
class PravidlaSlevNezavislaNaRocnikuTest extends AbstractTestDb
{
    private const array POJMENOVANA = ['kostka_zdarma', 'placka_zdarma'];

    /**
     * @test
     */
    public function jedenactPravidelPlatiProKazdyRocnik(): void
    {
        $obecna = dbOneArray(<<<'SQL'
            SELECT code
            FROM discount_rule
            WHERE year = $0 AND code NOT IN ($1)
            ORDER BY code
            SQL, [
            0 => DiscountRuleLoader::ANY_YEAR,
            1 => self::POJMENOVANA,
        ]);

        self::assertCount(11, $obecna);
        self::assertFalse((bool) dbOneCol(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM discount_rule
                WHERE year <> $0 AND code NOT IN ($1)
            )
            SQL, [
            0 => DiscountRuleLoader::ANY_YEAR,
            1 => self::POJMENOVANA,
        ]), 'Žádné z nich nesmí mít řádek pro konkrétní ročník');
    }

    /**
     * @test
     */
    public function pojmenovanaPravidlaMajiObecnouSablonuBezKodu(): void
    {
        foreach (self::POJMENOVANA as $kod) {
            $kodPredmetu = dbOneCol(
                <<<'SQL'
                    SELECT JSON_VALUE(parameters, '$.productCode')
                    FROM discount_rule
                    WHERE code = $0 AND year = $1
                    SQL,
                [
                    0 => $kod,
                    1 => DiscountRuleLoader::ANY_YEAR,
                ],
            );
            self::assertNull($kodPredmetu, "Šablona {$kod} nesmí jmenovat předmět, žádný ročník ho nezdědí");
            self::assertSame(1, (int) dbOneCol(<<<'SQL'
                SELECT COUNT(*)
                FROM discount_rule
                WHERE code = $0 AND year = $1
                SQL, [
                0 => $kod,
                1 => DiscountRuleLoader::ANY_YEAR,
            ]));
        }
    }

    /**
     * @test
     */
    public function rocnikBezVlastnichRadkuMaVsechnaPravidlaAPojmenovanaBezKodu(): void
    {
        $loader = new DiscountRuleLoader('dbFetchAll');

        $pravidla = $loader->rulesForYear(2093);

        self::assertCount(13, $pravidla);
        self::assertNull($loader->namedItemCode(2093, 'kostka'));
        self::assertNull($loader->namedItemCode(2093, 'placka'));
    }

    /**
     * @test
     */
    public function pravidlaJdouPodlePrioritySKodemAzJakoDruhymKriteriem(): void
    {
        foreach ([['b_rychle', 1], ['a_pozdeji', 2], ['c_pozdeji', 2]] as [$kod, $priorita]) {
            dbExecute(<<<'SQL'
                INSERT INTO discount_rule (code, name, description, required_right, priority, parameters, year, active)
                VALUES ($0, $0, '', 1005, $1, '{"scope":"tag","effect":"free","tag":"jidlo"}', 2093, 1)
                SQL, [
                0 => $kod,
                1 => $priorita,
            ]);
        }

        $kody = array_map(
            static fn ($pravidlo): string => $pravidlo->code,
            (new DiscountRuleLoader('dbFetchAll'))->rulesForYear(2093),
        );

        self::assertSame(['b_rychle', 'a_pozdeji', 'c_pozdeji'], array_slice($kody, 0, 3));
    }

    /**
     * @test
     */
    public function aktualniRocnikMaVlastniRadekProKazdeZPojmenovanychPravidel(): void
    {
        $pocet = (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM discount_rule
            WHERE year = $0 AND code IN ($1)
            SQL, [
            0 => ROCNIK,
            1 => self::POJMENOVANA,
        ]);

        self::assertSame(2, $pocet, 'Migrace zakládá řádky pro ročník, ve kterém běží; další ročníky zakládá import');
    }
}
