<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;

class ZrusSnidaneProHotelovePokojeTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterSingleTestMethod(): bool
    {
        return true;
    }

    private function vytvorUzivatele(): \Uzivatel
    {
        $uniqueId = uniqid();
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Hotel'
SQL,
            [
                0 => 'test_hotel_' . $uniqueId,
                1 => 'test.hotel.' . $uniqueId . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmetJidlo(string $nazev, int $den): int
    {
        $uniqueId = uniqid();
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = 90,
    stav = $2,
    kusu_vyrobeno = NULL,
    ubytovani_den = $3
SQL,
            [
                0 => $nazev,
                1 => strtoupper(str_replace(' ', '_', $nazev)) . '_' . $uniqueId,
                2 => StavPredmetu::VEREJNY,
                3 => $den,
            ],
        );

        $idPredmetu = dbInsertId();

        dbQuery(
            'INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1',
            [
                0 => $idPredmetu,
                1 => 'jidlo',
            ],
        );

        return $idPredmetu;
    }

    private function vytvorPredmetUbytovani(string $nazev, int $den, bool $hotel = false): int
    {
        $uniqueId = uniqid();
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = 500,
    stav = $2,
    kusu_vyrobeno = 10,
    ubytovani_den = $3,
    breakfast_included = $4
SQL,
            [
                0 => $nazev,
                1 => strtoupper(str_replace(' ', '_', $nazev)) . '_' . $uniqueId,
                2 => StavPredmetu::VEREJNY,
                3 => $den,
                4 => $hotel ? 1 : 0,
            ],
        );

        $idPredmetu = dbInsertId();

        // Category tag is always 'ubytovani' — 'hotel' is no longer a tag,
        // it is the breakfast_included column above.
        dbQuery(
            'INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1',
            [
                0 => $idPredmetu,
                1 => 'ubytovani',
            ],
        );

        return $idPredmetu;
    }

    private function objednejPredmet(int $idUzivatele, int $idPredmetu): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_nakupy SET
    id_uzivatele = $0,
    id_predmetu = $1,
    rok = $2,
    cena_nakupni = (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = $1),
    datum = NOW()
SQL,
            [
                0 => $idUzivatele,
                1 => $idPredmetu,
                2 => ROCNIK,
            ],
        );
    }

    private function pocetNakupuPredmetu(int $idUzivatele, int $idPredmetu): int
    {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2',
            [
                0 => $idUzivatele,
                1 => $idPredmetu,
                2 => ROCNIK,
            ],
        );
    }

    /**
     * @test
     */
    public function zrusiSnidaniProHotelovyPokoj(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        // Hotel ve čtvrtek (den 1) → snídaně v pátek (den 2) se smaže
        $idHotel = $this->vytvorPredmetUbytovani(
            'Dvojlůžák čtvrtek',
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            hotel: true,
        );
        $idSnidane = $this->vytvorPredmetJidlo('Snídaně pátek', DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);

        $this->objednejPredmet($uzivatel->id(), $idHotel);
        $this->objednejPredmet($uzivatel->id(), $idSnidane);

        self::assertSame(1, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidane));

        $smazano = ShopUbytovani::zrusSnidaneProHotelovePokoje($uzivatel);

        self::assertSame(1, $smazano);
        self::assertSame(0, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidane));
    }

    /**
     * @test
     */
    public function nezrusiSnidaniProNehoteloveUbytovani(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        $idSpacak = $this->vytvorPredmetUbytovani(
            'Spacák čtvrtek',
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            hotel: false,
        );
        $idSnidane = $this->vytvorPredmetJidlo('Snídaně pátek', DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);

        $this->objednejPredmet($uzivatel->id(), $idSpacak);
        $this->objednejPredmet($uzivatel->id(), $idSnidane);

        $smazano = ShopUbytovani::zrusSnidaneProHotelovePokoje($uzivatel);

        self::assertSame(0, $smazano);
        self::assertSame(1, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidane));
    }

    /**
     * @test
     */
    public function nezrusiObedAniVeceri(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        $idHotel = $this->vytvorPredmetUbytovani(
            'Dvojlůžák čtvrtek',
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            hotel: true,
        );
        $idObed = $this->vytvorPredmetJidlo('Oběd pátek', DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);

        $this->objednejPredmet($uzivatel->id(), $idHotel);
        $this->objednejPredmet($uzivatel->id(), $idObed);

        $smazano = ShopUbytovani::zrusSnidaneProHotelovePokoje($uzivatel);

        self::assertSame(0, $smazano);
        self::assertSame(1, $this->pocetNakupuPredmetu($uzivatel->id(), $idObed));
    }

    /**
     * @test
     */
    public function nezrusiSnidaniVDenBezHotelu(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        // Hotel ve čtvrtek (den 1) → snídaně v pátek (den 2) se smaže
        // Snídaně v sobotu (den 3) se nesmaže — žádný hotel v pátek (den 2)
        $idHotel = $this->vytvorPredmetUbytovani(
            'Dvojlůžák čtvrtek',
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            hotel: true,
        );
        $idSnidanePatek = $this->vytvorPredmetJidlo('Snídaně pátek', DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);
        $idSnidaneSobota = $this->vytvorPredmetJidlo('Snídaně sobota', DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA);

        $this->objednejPredmet($uzivatel->id(), $idHotel);
        $this->objednejPredmet($uzivatel->id(), $idSnidanePatek);
        $this->objednejPredmet($uzivatel->id(), $idSnidaneSobota);

        $smazano = ShopUbytovani::zrusSnidaneProHotelovePokoje($uzivatel);

        self::assertSame(1, $smazano, 'Měla se smazat pouze snídaně v pátek');
        self::assertSame(0, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidanePatek));
        self::assertSame(1, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidaneSobota));
    }

    /**
     * @test
     */
    public function nesmazeNicKdyzUzivatelNemaHotel(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        $idSnidane = $this->vytvorPredmetJidlo('Snídaně pátek', DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);
        $this->objednejPredmet($uzivatel->id(), $idSnidane);

        $smazano = ShopUbytovani::zrusSnidaneProHotelovePokoje($uzivatel);

        self::assertSame(0, $smazano);
        self::assertSame(1, $this->pocetNakupuPredmetu($uzivatel->id(), $idSnidane));
    }
}
