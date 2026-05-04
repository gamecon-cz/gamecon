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
    public function zobraziTooltipyProZnameTypyUbytovaniPodleNazvu(): void
    {
        $uzivatel = $this->vytvorUzivatele((string) uniqid());

        $this->vytvorPredmetUbytovani('Dvoulůžák čtvrtek', ROCNIK, 10);
        $this->vytvorPredmetUbytovani('Hotelový dvojlůžák deluxe čtvrtek', ROCNIK, 10);

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $html = $shop->ubytovani()->ubytovaniHtml(true);

        self::assertStringContainsString('class="shop_popis shopUbytovani_radek gc_tooltip"', $html);
        self::assertStringContainsString('Dvoulůžák', $html);
        self::assertStringContainsString('buňkový typ ubytování v rámci kolejí.', $html);
        self::assertStringContainsString('Hotelový dvojlůžák deluxe', $html);
        self::assertStringContainsString('dvoulůžkový hotelový pokoj s vlastní koupelnou a toaletou; prostornější a komfortnější než standard, snídaně v ceně.', $html);
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
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
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
}
