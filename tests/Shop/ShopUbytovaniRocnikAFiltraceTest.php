<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
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
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = 500,
    stav = $3,
    kusu_vyrobeno = $4,
    typ = $5,
    ubytovani_den = $6,
    podtyp = $7
SQL,
            [
                0 => $nazev,
                1 => strtoupper(str_replace(' ', '_', $nazev)) . '_' . $unique,
                2 => $modelRok,
                3 => StavPredmetu::VEREJNY,
                4 => $kusuVyrobeno,
                5 => TypPredmetu::UBYTOVANI,
                6 => $ubytovaniDen,
                7 => $podtyp,
            ],
        );

        return dbInsertId();
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

    private function pridelPravo(\Uzivatel $uzivatel, int $idPrava): \Uzivatel
    {
        $unique = uniqid('', false);
        $idRole = -random_int(100000, 999999);
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ($0, $1, 'test')
SQL,
            [
                0 => $idPrava,
                1 => 'test_pravo_' . $idPrava,
            ],
        );
        dbQuery(<<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, $2, '', -1, 'trvala', '')
SQL,
            [
                0 => $idRole,
                1 => 'TEST_UBYTOVANI_' . $idPrava . '_' . $unique,
                2 => 'Test role ' . $unique,
            ],
        );
        dbQuery(
            'INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [$idRole, $idPrava],
        );
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [$uzivatel->id(), $idRole],
        );

        \Uzivatel::smazCache();

        return \Uzivatel::zIdUrcite($uzivatel->id());
    }

    /**
     * @return int[]
     */
    private function idsUlozenehoUbytovani(\Uzivatel $uzivatel): array
    {
        return array_map('intval', dbOneArray(<<<SQL
SELECT shop_nakupy.id_predmetu
FROM shop_nakupy
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_nakupy.id_uzivatele = $0
  AND shop_nakupy.rok = $1
  AND shop_predmety.typ = $2
ORDER BY shop_predmety.ubytovani_den
SQL,
            [$uzivatel->id(), ROCNIK, TypPredmetu::UBYTOVANI],
        ));
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

        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, 12);
        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK - 1, 0);
        $this->vytvorPredmetUbytovani('Spacák čtvrtek', ROCNIK - 1, 25);

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
        $idPredmetuUbytovaniPatek = $this->vytvorPredmetUbytovani(
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
            2 => (string) $idPredmetuUbytovaniPatek,
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
    public function uloziVybraneDveNavazujiciNociBezDopoctuTreti(): void
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
        $this->vytvorPredmetUbytovani(
            'Hotelový jednolůžák deluxe sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            PodtypPredmetu::HOTEL,
        );

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idHotelDeluxeCtvrtek,
            2 => (string) $idHotelDeluxePatek,
        ];

        try {
            $shop->ubytovani()->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }

        $ulozenaIds = $this->idsUlozenehoUbytovani($uzivatel);

        self::assertSame(
            [$idHotelDeluxeCtvrtek, $idHotelDeluxePatek],
            $ulozenaIds,
        );
    }

    /**
     * @test
     */
    public function neuloziUbytovaniPouzeNaJednuNocBezVyjimky(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $idPredmetuUbytovani = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idPredmetuUbytovani,
        ];

        try {
            $this->expectException(\Chyba::class);
            $this->expectExceptionMessage(ShopUbytovani::CHYBA_MINIMALNE_DVE_NOCI);

            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }
    }

    /**
     * @test
     */
    public function neuloziNenavazujiciNoci(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());
        $idCtvrtek = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );
        $idSobota = $this->vytvorPredmetUbytovani(
            'Dvoulůžák sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idCtvrtek,
            3 => (string) $idSobota,
        ];

        try {
            $this->expectException(\Chyba::class);
            $this->expectExceptionMessage(ShopUbytovani::CHYBA_NAVAZUJICI_NOCI);

            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }
    }

    /**
     * @test
     */
    public function adminskaUpravaUbytovaniPouzivaStejnouValidaciNoci(): void
    {
        $ucastnik = $this->vytvorUzivatele((string) uniqid());
        $admin = $this->vytvorUzivatele((string) uniqid());
        $idCtvrtek = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );
        $idSobota = $this->vytvorPredmetUbytovani(
            'Dvoulůžák sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idCtvrtek,
            3 => (string) $idSobota,
        ];

        try {
            $this->expectException(\Chyba::class);
            $this->expectExceptionMessage(ShopUbytovani::CHYBA_NAVAZUJICI_NOCI);

            (new Shop($ucastnik, $admin, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }
    }

    /**
     * @test
     */
    public function neuloziNenavazujiciNociAniPriVyjimceNaJednuNoc(): void
    {
        $uzivatel = $this->pridelPravo(
            $this->vytvorUzivatele((string) uniqid()),
            Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC,
        );
        $idCtvrtek = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );
        $idSobota = $this->vytvorPredmetUbytovani(
            'Dvoulůžák sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idCtvrtek,
            3 => (string) $idSobota,
        ];

        try {
            $this->expectException(\Chyba::class);
            $this->expectExceptionMessage(ShopUbytovani::CHYBA_NAVAZUJICI_NOCI);

            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }
    }

    /**
     * @test
     */
    public function vyjimkaZUbytovaniNaJednuNocZustavaZachovana(): void
    {
        $uzivatel = $this->pridelPravo(
            $this->vytvorUzivatele((string) uniqid()),
            Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC,
        );
        $admin = $this->vytvorUzivatele((string) uniqid());
        $idPredmetuUbytovani = $this->vytvorPredmetUbytovani(
            'Dvoulůžák čtvrtek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );

        $_POST['shopUbytovaniDny'] = [
            1 => (string) $idPredmetuUbytovani,
        ];

        try {
            (new Shop($uzivatel, $admin, SystemoveNastaveni::zGlobals()))
                ->ubytovani()
                ->zpracuj(vcetneSpolubydliciho: false);
        } finally {
            unset($_POST['shopUbytovaniDny']);
        }

        self::assertSame([$idPredmetuUbytovani], $this->idsUlozenehoUbytovani($uzivatel));
    }

    /**
     * @test
     */
    public function vykresliJednotliveNociASnidaneProHotelovePokoje(): void
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
        $idHotelPatek = $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' pátek',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            PodtypPredmetu::HOTEL,
        );
        $idHotelSobota = $this->vytvorPredmetUbytovani(
            $typHoteluCtvrtek . ' sobota',
            ROCNIK,
            10,
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            PodtypPredmetu::HOTEL,
        );

        $html = (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))
            ->ubytovani()
            ->ubytovaniHtml(true);

        self::assertStringContainsString('class="shopUbytovani_upozorneni"', $html);
        self::assertStringContainsString('data-minimalni-pocet-noci="2"', $html);

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
            '~<input[^>]*name="shopUbytovaniDny\[2]"[^>]*value="' . preg_quote((string) $idHotelPatek, '~') . '"[^>]*>~u',
            $html,
            $hotelPatekInput,
        );
        preg_match(
            '~<input[^>]*name="shopUbytovaniDny\[3]"[^>]*value="' . preg_quote((string) $idHotelSobota, '~') . '"[^>]*>~u',
            $html,
            $hotelSobotaInput,
        );
        preg_match(
            '~<input[^>]*name="shopUbytovaniDny\[1]"[^>]*value=""[^>]*data-typ="Žádné"[^>]*>~u',
            $html,
            $zadneInput,
        );

        self::assertNotEmpty($hotelStredaInput, 'V HTML ubytování chybí input pro středeční hotel.');
        self::assertStringContainsString('data-snidane-dny="1"', $hotelStredaInput[0]);
        self::assertNotEmpty($hotelCtvrtekInput, 'V HTML ubytování chybí input pro čtvrteční hotel.');
        self::assertStringContainsString('data-snidane-dny="2"', $hotelCtvrtekInput[0]);
        self::assertNotEmpty($hotelPatekInput, 'V HTML ubytování chybí input pro páteční hotel.');
        self::assertNotEmpty($hotelSobotaInput, 'V HTML ubytování chybí input pro sobotní hotel.');
        self::assertNotEmpty($zadneInput, 'V HTML ubytování chybí input pro žádné ubytování.');
        self::assertStringContainsString('data-snidane-dny="2"', $zadneInput[0]);
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
        self::assertStringContainsString('data-snidane-dny="2"', $hotelCtvrtekInput[0]);
        self::assertNotEmpty($zadneInput, 'V HTML adminího ubytování chybí input pro žádné ubytování.');
        self::assertStringContainsString('data-snidane-dny="2"', $zadneInput[0]);
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }
}
