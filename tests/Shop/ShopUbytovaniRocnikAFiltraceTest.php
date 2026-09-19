<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\Shop;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class ShopUbytovaniRocnikAFiltraceTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Ubytovani'
SQL,
            [
                0 => 'test_ubytovani_' . $suffix,
                1 => 'test.ubytovani.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmetUbytovani(
        string $nazev,
        int $modelRok,
        ?int $kusuVyrobeno = 10,
        int $ubytovaniDen = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        ?string $podtyp = null,
    ): int {
        $unique = uniqid($modelRok . '_', true);
        // model_rok and typ are virtual columns from the shop_predmety_s_typem view;
        // typ comes from product_product_tag and model_rok from archived_at
        // (NULL → current ROCNIK, else YEAR(archived_at)).
        $archivedAt = $modelRok === ROCNIK
            ? null
            : sprintf('%d-12-31 23:59:59', $modelRok);
        // podtyp='hotel' is emitted by the view when breakfast_included=1;
        // podtyp='mikina' when the product has the 'mikina' tag (see view definition).
        $breakfastIncluded = $podtyp === PodtypPredmetu::HOTEL ? 1 : 0;
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = 500,
    stav = $2,
    kusu_vyrobeno = $3,
    ubytovani_den = $4,
    archived_at = $5,
    breakfast_included = $6
SQL,
            [
                0 => $nazev,
                1 => strtoupper(str_replace(' ', '_', $nazev)) . '_' . $unique,
                2 => StavPredmetu::VEREJNY,
                3 => $kusuVyrobeno,
                4 => $ubytovaniDen,
                5 => $archivedAt,
                6 => $breakfastIncluded,
            ],
        );
        $idPredmetu = dbInsertId();
        dbQuery(
            "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'ubytovani'",
            [
                0 => $idPredmetu,
            ],
        );
        if ($podtyp === PodtypPredmetu::MIKINA) {
            dbQuery(
                "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'mikina'",
                [
                    0 => $idPredmetu,
                ],
            );
        }

        return $idPredmetu;
    }

    private function objednejPredmet(\Uzivatel $uzivatel, int $idPredmetu): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_nakupy SET
    id_uzivatele = $0,
    id_predmetu = $1,
    rok = $2,
    cena_nakupni = (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = $1),
    datum = NOW()
SQL,
            [$uzivatel->id(), $idPredmetu, ROCNIK],
        );
    }

    /**
     * @test
     */
    public function ignorujeHistorickeUbytovaniPriVyberuAktualnichTypu(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $den = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;

        // Production deduplicates archived nazev by appending ' (#id)' (see new-eshop migration),
        // so historical rows must carry a different nazev than the current-year row.
        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, 12);
        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek ' . (ROCNIK - 1), ROCNIK - 1, 0);
        $this->vytvorPredmetUbytovani('Spacák čtvrtek ' . (ROCNIK - 1), ROCNIK - 1, 25);

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $ubytovani = $shop->ubytovani();

        self::assertSame(12, $ubytovani->kapacita($den, 'Dvoulůžák'));
        self::assertFalse($ubytovani->existujeUbytovani($den, 'Spacák'));
    }

    /**
     * @test
     */
    public function neomezenaKapacitaUbytovaniFungujeJakoNekonecna(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $jinyUzivatel = $this->vytvorUzivatele((string) uniqid());
        $den = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;

        $idPredmetu = $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, null);
        $this->objednejPredmet($jinyUzivatel, $idPredmetu);

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $ubytovani = $shop->ubytovani();

        self::assertSame('∞', $ubytovani->kapacita($den, 'Dvoulůžák'));
        self::assertFalse($ubytovani->plno($den, 'Dvoulůžák'));
        self::assertFalse(ShopUbytovani::ubytovaniPresKapacitu($idPredmetu, $ubytovani->mozneDny()));
    }

    /**
     * @test
     */
    public function seradiTypyUbytovaniPodleDefinovanehoPoradi(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $typy = [
            'Jednolůžák',
            'Dvoulůžák',
            'Dvojlůžák',
            'Trojlůžák',
            'Spacák',
            'Hotelový jednolůžák standard',
            'Hotelový dvojlůžák standard',
            'Hotelový jednolůžák deluxe (buňka)',
            'Hotelový jednolůžák deluxe',
            'Hotelový dvojlůžák deluxe',
        ];

        $nahodnePoradi = $typy;
        shuffle($nahodnePoradi);
        foreach ($nahodnePoradi as $typ) {
            $this->vytvorPredmetUbytovani($typ . ' čtvrtek', ROCNIK, 10);
        }

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $realnePoradi = array_keys($shop->ubytovani()->mozneTypy());

        self::assertSame($typy, $realnePoradi);
    }

    /**
     * @test
     */
    public function seradiPopisneNazvyUbytovaniTakAbyKolejeBylyPredHotely(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $typy = [
            'Postel na "1L" koleji',
            'Postel na 2L koleji',
            'Postel na 3L koleji',
            'Postel na 1L hotelu se snídaní',
            'Postel na 2L hotelu se snídaní',
            'Postel na 1L hotelu deluxe se snídaní - dvojbuňka',
            'Postel na 1L hotelu deluxe se snídaní',
            'Postel na 2L hotelu deluxe se snídaní',
        ];

        $nahodnePoradi = $typy;
        shuffle($nahodnePoradi);
        foreach ($nahodnePoradi as $typ) {
            $this->vytvorPredmetUbytovani($typ . ' čtvrtek', ROCNIK, 10);
        }

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $realnePoradi = array_keys($shop->ubytovani()->mozneTypy());

        self::assertSame($typy, $realnePoradi);
    }

    /**
     * @test
     */
    public function seradiVariantyTypuUbytovaniPodleZakladnihoTypu(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $typy = [
            'Jednolůžák (C)',
            'Dvojlůžák (A)',
            'Spacák',
            'Hotelový dvoulůžák standard (A)',
            'Hotelový jednolůžák deluxe (buňka) (A)',
            'Hotelový jednolůžák deluxe (A)',
            'Hotelový dvoulůžák deluxe (A)',
        ];

        $nahodnePoradi = $typy;
        shuffle($nahodnePoradi);
        foreach ($nahodnePoradi as $typ) {
            $this->vytvorPredmetUbytovani($typ . ' čtvrtek', ROCNIK, 10);
        }

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $realnePoradi = array_keys($shop->ubytovani()->mozneTypy());

        self::assertSame($typy, $realnePoradi);
    }

    /**
     * @test
     */
    public function neuloziUbytovaniZJinehoRocniku(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $idHistorickehoUbytovani = $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK - 1, 10);

        $this->expectException(\Chyba::class);
        $this->expectExceptionMessage('není dostupná pro ročník');

        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$idHistorickehoUbytovani],
            $uzivatel,
            true,
            ROCNIK,
        );
    }

    /**
     * @test
     */
    public function dohledaIdsUbytovaniPodleKoduTypuADnu(): void
    {
        $unique = uniqid('', false);
        $ctvrtek = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;
        $patek = DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK;
        $idPatek = $this->vytvorUbytovaniSKodem('spacak' . $unique . '_pa', $patek);
        $idCtvrtek = $this->vytvorUbytovaniSKodem('spacak' . $unique . '_ct', $ctvrtek);

        self::assertSame(
            [$idCtvrtek, $idPatek],
            ShopUbytovani::dejIdsPredmetuUbytovaniPodleKoduTypu('spacak' . $unique, [$patek, $ctvrtek]),
        );
    }

    private function vytvorUbytovaniSKodem(string $kodPredmetu, int $ubytovaniDen): int
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $0,
    cena_aktualni = 500,
    stav = $1,
    kusu_vyrobeno = 10,
    ubytovani_den = $2
SQL,
            [
                0 => $kodPredmetu,
                1 => StavPredmetu::VEREJNY,
                2 => $ubytovaniDen,
            ],
        );
        $idPredmetu = dbInsertId();
        dbQuery(
            "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'ubytovani'",
            [
                0 => $idPredmetu,
            ],
        );

        return $idPredmetu;
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }
}
