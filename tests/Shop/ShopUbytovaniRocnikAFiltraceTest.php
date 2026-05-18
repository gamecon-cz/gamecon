<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\Shop;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
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
            [0 => $idPredmetu],
        );
        if ($podtyp === PodtypPredmetu::MIKINA) {
            dbQuery(
                "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'mikina'",
                [0 => $idPredmetu],
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

    private function uzivatelNechceUbytovani(\Uzivatel $uzivatel): bool
    {
        return (bool) dbOneCol(<<<SQL
SELECT nechce_ubytovani
FROM uzivatele_hodnoty
WHERE id_uzivatele = $0
SQL,
            [$uzivatel->id()],
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
        self::assertSame(1, $ubytovani->obsazenoMist($den, 'Dvoulůžák'));
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
    public function zobraziTooltipyProZnameTypyUbytovaniPodleNazvu(): void
    {
        $this->pripravXTemplateCache();

        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, 10);
        $this->vytvorPredmetUbytovani('Hotelový dvojlůžák deluxe čtvrtek', ROCNIK, 10);

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $html = $shop->ubytovani()->ubytovaniHtml(true);

        self::assertStringContainsString('class="shop_popis shopUbytovani_radek gc_tooltip"', $html);
        self::assertStringContainsString('Dvoulůžák', $html);
        self::assertStringContainsString('Postel na 2L koleji.', $html);
        self::assertStringContainsString('Hotelový dvojlůžák deluxe', $html);
        self::assertStringContainsString('Postel na 2L hotelu deluxe se snídaní.', $html);
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
    public function volbaNechceUbytovaniSeUkladaPodleCheckboxu(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, 10);

        $_POST['shopUbytovaniDny'] = [
            1 => '',
        ];
        $_POST['shopUbytovaniNechci'] = 'on';

        try {
            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false, ulozitNechceUbytovani: true);

            self::assertTrue($this->uzivatelNechceUbytovani($uzivatel));

            unset($_POST['shopUbytovaniNechci']);

            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false, ulozitNechceUbytovani: true);

            self::assertFalse($this->uzivatelNechceUbytovani($uzivatel));
        } finally {
            unset($_POST['shopUbytovaniDny'], $_POST['shopUbytovaniNechci']);
        }
    }

    /**
     * @test
     */
    public function checkboxNechceUbytovaniSeIgnorujePokudJeVybraneUbytovani(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $idPredmetuUbytovani = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );
        $this->vytvorPredmetUbytovani(
            'Dvoulůžák pátek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
        );
        $this->vytvorPredmetUbytovani(
            'Dvoulůžák sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idPredmetuUbytovani,
        ];
        $_POST['shopUbytovaniNechci'] = 'on';

        try {
            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false, ulozitNechceUbytovani: true);

            self::assertFalse($this->uzivatelNechceUbytovani($uzivatel));
        } finally {
            unset($_POST['shopUbytovaniDny'], $_POST['shopUbytovaniNechci']);
        }
    }

    /**
     * @test
     */
    public function bezJedneNociUloziVybranyHotelovyTypProVsechnyTriNoci(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák standard čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            PodtypPredmetu::HOTEL,
        );
        $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák standard pátek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            PodtypPredmetu::HOTEL,
        );
        $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák standard sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            PodtypPredmetu::HOTEL,
        );

        $idHotelDeluxeCtvrtek = $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák deluxe čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            PodtypPredmetu::HOTEL,
        );
        $idHotelDeluxePatek = $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák deluxe pátek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            PodtypPredmetu::HOTEL,
        );
        $idHotelDeluxeSobota = $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák deluxe sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            PodtypPredmetu::HOTEL,
        );

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idHotelDeluxeCtvrtek,
        ];

        try {
            $shop->ubytovani()->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }

        $ulozenaIds = array_map('intval', dbOneArray(<<<SQL
SELECT shop_nakupy.id_predmetu
FROM shop_nakupy
JOIN shop_predmety_s_typem AS shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_nakupy.id_uzivatele = $0
  AND shop_nakupy.rok = $1
  AND shop_predmety.typ = $2
ORDER BY shop_predmety.ubytovani_den
SQL,
            [$uzivatel->id(), ROCNIK, TypPredmetu::UBYTOVANI],
        ));

        self::assertSame(
            [$idHotelDeluxeCtvrtek, $idHotelDeluxePatek, $idHotelDeluxeSobota],
            $ulozenaIds,
        );
    }

    /**
     * @test
     */
    public function bezJedneNociVykresliSnidaneProStredecniNocATriNociOdCtvrtka(): void
    {
        $this->pripravXTemplateCache();

        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $typHoteluStreda = 'Hotelový jednolůžák standard snidane streda ' . uniqid();
        $typHoteluCtvrtek = 'Hotelový jednolůžák standard snidane ctvrtek ' . uniqid();

        $idHotelStreda = $this->vytvorPredmetUbytovani(
            $typHoteluStreda . ' středa',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_STREDA,
            PodtypPredmetu::HOTEL,
        );

        $idHotelCtvrtek = $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            PodtypPredmetu::HOTEL,
        );
        $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' pátek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            PodtypPredmetu::HOTEL,
        );
        $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            PodtypPredmetu::HOTEL,
        );

        $html = (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
            ->ubytovani()
            ->ubytovaniHtml(true);

        preg_match(
            '~<input[^>]*class="shopUbytovani_radio"[^>]*value="' . preg_quote((string) $idHotelStreda, '~') . '"[^>]*>~u',
            $html,
            $hotelStredaInput,
        );
        preg_match(
            '~<input[^>]*class="shopUbytovani_radio"[^>]*value="' . preg_quote((string) $idHotelCtvrtek, '~') . '"[^>]*>~u',
            $html,
            $hotelCtvrtekInput,
        );
        preg_match(
            '~<input[^>]*name="shopUbytovaniDny\[1]"[^>]*value=""[^>]*data-typ="Žádné"[^>]*>~u',
            $html,
            $zadneInput,
        );

        self::assertNotEmpty($hotelStredaInput, 'V HTML ubytování chybí input pro středeční hotel.');
        self::assertStringContainsString('data-snidane-dny="1"', $hotelStredaInput[0]);
        self::assertNotEmpty($hotelCtvrtekInput, 'V HTML ubytování chybí input pro čtvrteční hotel.');
        self::assertStringContainsString('data-snidane-dny="2,3,4"', $hotelCtvrtekInput[0]);
        self::assertNotEmpty($zadneInput, 'V HTML ubytování chybí input pro žádné ubytování.');
        self::assertStringContainsString('data-snidane-dny="2,3,4"', $zadneInput[0]);
    }

    /**
     * @test
     */
    public function adminUbytovaniTabulkaPredavaDataProHoteloveSnidane(): void
    {
        require_once __DIR__ . '/../../admin/scripts/modules/_submoduly/ubytovani_tabulka.php';
        $this->pripravXTemplateCache();

        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $typHoteluCtvrtek = 'Hotelový jednolůžák standard admin snidane ctvrtek ' . uniqid();

        $idHotelCtvrtek = $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            PodtypPredmetu::HOTEL,
        );

        $html = \UbytovaniTabulka::ubytovaniTabulkaZ(
            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))->ubytovani(),
            SystemoveNastaveni::zGlobals(),
            true,
        );

        preg_match(
            '~<input[^>]*class="shopUbytovani_radio"[^>]*value="' . preg_quote((string) $idHotelCtvrtek, '~') . '"[^>]*>~u',
            $html,
            $hotelCtvrtekInput,
        );
        preg_match(
            '~<input[^>]*name="shopUbytovaniDny\[1]"[^>]*value=""[^>]*data-typ="Žádné"[^>]*>~u',
            $html,
            $zadneInput,
        );

        self::assertNotEmpty($hotelCtvrtekInput, 'V HTML adminího ubytování chybí input pro čtvrteční hotel.');
        self::assertStringContainsString('data-podtyp="hotel"', $hotelCtvrtekInput[0]);
        self::assertStringContainsString('data-snidane-dny="2,3,4"', $hotelCtvrtekInput[0]);
        self::assertNotEmpty($zadneInput, 'V HTML adminího ubytování chybí input pro žádné ubytování.');
        self::assertStringContainsString('data-snidane-dny="2,3,4"', $zadneInput[0]);
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }
}
