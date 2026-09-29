<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db\Migrace;

use Gamecon\Shop\StavPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Godric\DbMigrations\Migration;

class ZasobaVariantPodleProdanychTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private const MIGRACE = __DIR__ . '/../../../migrace/2026-09-29-124030_zasoba-variant-podle-prodanych.php';

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 94101,
    login_uzivatele = 'test_zasoba_variant',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Zásoba',
    email1_uzivatele = 'test.zasoba.variant@example.org'
SQL,
    ];

    /**
     * @test
     */
    public function zasobaJeVyrobenoMinusLetosProdane(): void
    {
        $idVarianty = $this->predmetSVariantou(94110, 'zasoba_94110', kusuVyrobeno: 10);
        $this->nakup(94110, $idVarianty, ROCNIK, kusu: 3);
        $this->nakup(94110, $idVarianty, ROCNIK - 1, kusu: 2);

        $this->spustMigraci();

        self::assertSame(7, $this->zasoba($idVarianty), 'Last year\'s purchases must not reduce this year\'s stock');
    }

    /**
     * Cancelling a purchase without a variant returns nothing to stock, so it must not have taken any.
     *
     * @test
     */
    public function nakupBezVariantyZasobuNeubira(): void
    {
        $idVarianty = $this->predmetSVariantou(94120, 'zasoba_94120', kusuVyrobeno: 10);
        $this->nakup(94120, $idVarianty, ROCNIK, kusu: 1);
        $this->nakup(94120, null, ROCNIK, kusu: 2);

        $this->spustMigraci();

        self::assertSame(9, $this->zasoba($idVarianty));
    }

    /**
     * A night hangs under its room type, but its capacity is on its own row sharing the variant's code.
     *
     * @test
     */
    public function variantaCiziHoVlastnikaBereKapacituZeSvehoRadku(): void
    {
        $this->predmet(94130, 'typ_94130', kusuVyrobeno: null);
        $this->predmet(94131, 'noc_94131', kusuVyrobeno: 5);
        $idVarianty = $this->varianta(94130, 'noc_94131', zasoba: 5);
        $this->nakup(94131, $idVarianty, ROCNIK, kusu: 2);

        $this->spustMigraci();

        self::assertSame(3, $this->zasoba($idVarianty));
    }

    /**
     * @test
     */
    public function neomezenaZustavaNeomezena(): void
    {
        $idVarianty = $this->predmetSVariantou(94140, 'zasoba_94140', kusuVyrobeno: null);
        $this->nakup(94140, $idVarianty, ROCNIK, kusu: 2);

        $this->spustMigraci();

        self::assertNull($this->zasoba($idVarianty));
    }

    /**
     * The desk may oversell; stock must then show the shortfall rather than claim zero.
     *
     * @test
     */
    public function prodanoNadKapacituDaZapornouZasobu(): void
    {
        $idVarianty = $this->predmetSVariantou(94150, 'zasoba_94150', kusuVyrobeno: 2);
        $this->nakup(94150, $idVarianty, ROCNIK, kusu: 3);

        $this->spustMigraci();

        self::assertSame(-1, $this->zasoba($idVarianty));
    }

    private function spustMigraci(): void
    {
        (new Migration(self::MIGRACE, basename(self::MIGRACE), dbConnect()))->apply();
    }

    private function predmetSVariantou(int $idPredmetu, string $kod, ?int $kusuVyrobeno): int
    {
        $this->predmet($idPredmetu, $kod, $kusuVyrobeno);

        return $this->varianta($idPredmetu, $kod, zasoba: $kusuVyrobeno);
    }

    private function predmet(int $idPredmetu, string $kod, ?int $kusuVyrobeno): void
    {
        dbQuery(
            'INSERT INTO shop_predmety SET id_predmetu = $0, nazev = $1, kod_predmetu = $1, cena_aktualni = 100,
                stav = $2, nabizet_do = NOW() + INTERVAL 1 DAY, kusu_vyrobeno = $3, popis = \'\'',
            [
                0 => $idPredmetu,
                1 => $kod,
                2 => StavPredmetu::VEREJNY,
                3 => $kusuVyrobeno,
            ],
        );
    }

    private function varianta(int $idVlastnika, string $kod, ?int $zasoba): int
    {
        dbQuery(
            'INSERT INTO product_variant (product_id, name, code, price, remaining_quantity, position)
             VALUES ($0, $1, $1, 100, $2, 0)',
            [
                0 => $idVlastnika,
                1 => $kod,
                2 => $zasoba,
            ],
        );

        return (int) dbInsertId();
    }

    private function nakup(int $idPredmetu, ?int $idVarianty, int $rok, int $kusu): void
    {
        for ($kus = 0; $kus < $kusu; ++$kus) {
            dbQuery(
                'INSERT INTO shop_nakupy (id_uzivatele, id_objednatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
                 VALUES (94101, 94101, $0, $1, $2, 100, NOW())',
                [
                    0 => $idPredmetu,
                    1 => $idVarianty,
                    2 => $rok,
                ],
            );
        }
    }

    private function zasoba(int $idVarianty): ?int
    {
        $zasoba = dbOneCol('SELECT remaining_quantity FROM product_variant WHERE id = $0', [
            0 => $idVarianty,
        ]);

        return $zasoba === null ? null : (int) $zasoba;
    }
}
