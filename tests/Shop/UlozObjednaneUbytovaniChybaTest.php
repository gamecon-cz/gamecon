<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;

class UlozObjednaneUbytovaniChybaTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function ucastnik(): \Uzivatel
    {
        $suffix = uniqid('', false);
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Ubytovany',
    prijmeni_uzivatele = 'Test'
SQL,
            [
                0 => 'ubytovany_' . $suffix,
                1 => 'ubytovany.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorNoc(string $kodTypu, int $den, int $kusuVyrobeno = 10): int
    {
        $pripony = ['_st', '_ct', '_pa', '_so', '_ne'];
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = 400,
    stav = $2,
    kusu_vyrobeno = $3,
    ubytovani_den = $4
SQL,
            [
                0 => $kodTypu . ' den ' . $den,
                1 => $kodTypu . $pripony[$den],
                2 => StavPredmetu::VEREJNY,
                3 => $kusuVyrobeno,
                4 => $den,
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

    /**
     * Import chytá `Chyba` zvlášť: takový řádek se odroluje, zapíše se do chyb a **jede se
     * dál**. Cokoli jiného spadne do `catch (\Throwable)`, které výjimku přehodí a zabije
     * celý import. Převod na `AccommodationWriter` (ten hází `RuntimeException`) proto musí
     * typ výjimky přeložit, jinak jeden špatný řádek shodí celý soubor.
     *
     * @test
     */
    public function chybaValidaceNociJeChybaNeRuntimeException(): void
    {
        $ucastnik = $this->ucastnik();
        $kodTypu = 'JEDNANOC' . strtoupper(substr(uniqid('', false), -6));
        $ctvrtek = $this->vytvorNoc($kodTypu, DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK);

        $zachycena = null;
        try {
            // Jedna noc bez práva na výjimku — legacy pravidlo „nejméně dvě noci".
            ShopUbytovani::ulozObjednaneUbytovaniUcastnika([$ctvrtek], $ucastnik, false);
        } catch (\Throwable $throwable) {
            $zachycena = $throwable;
        }

        self::assertInstanceOf(
            \Chyba::class,
            $zachycena,
            'Import spoléhá na to, že validace hází Chyba — jinak se nepřeskočí řádek, ale spadne celý import',
        );
    }
}
