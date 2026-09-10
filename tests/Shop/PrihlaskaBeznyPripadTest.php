<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Role\Role;

/**
 * Běžný případ přihlášky: účastník se přihlásí na GC a objedná si ubytování,
 * předmět, tričko a jídlo. Ověřuje, že se objednávka uloží celá a že se
 * objednané kusy odečtou z dostupných zásob.
 */
class PrihlaskaBeznyPripadTest extends AbstractTestPrihlaska
{
    /**
     * @test
     */
    public function beznyUzivatelSePrihlasiNaGc(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        self::assertFalse($uzivatel->gcPrihlasen(), 'Uživatel nesmí být na GC přihlášen ještě před odesláním přihlášky');

        $this->odesliPrihlasku($uzivatel, []);

        self::assertTrue(
            \Uzivatel::zIdUrcite($uzivatel->id())->gcPrihlasen(),
            'Po odeslání přihlášky musí být uživatel přihlášen na GC',
        );
        self::assertSame(
            1,
            $this->pocetRoliUzivatele($uzivatel, Role::PRIHLASEN_NA_LETOSNI_GC),
            'Přihlášení na GC se musí zapsat právě jednou',
        );
    }

    /**
     * @test
     */
    public function objednavkaSeUloziVcetneVsechObjednanychPolozek(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();

        $idPredmetu = $this->vytvorPredmet('Placka', kusuVyrobeno: 10, cena: 50.0);
        $idTricka = $this->vytvorTricko('Tričko modré', kusuVyrobeno: 10, cena: 250.0);
        $idJidla = $this->vytvorJidlo('oběd', den: self::DEN_PATEK, kusuVyrobeno: 10, cena: 120.0);
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 10, cena: 300.0);

        $this->odesliPrihlasku($uzivatel, [
            'shopP' => [
                $idPredmetu => 2,
            ],
            'shopT'          => [$idTricka],
            'cShopJidloZmen' => '1',
            'cShopJidlo'     => [
                $idJidla => '1',
            ],
            'shopUbytovaniDny' => $idsNoci,
        ]);

        self::assertSame(2, $this->pocetNakupu($uzivatel, $idPredmetu), 'Musí se uložit oba objednané kusy předmětu');
        self::assertSame(1, $this->pocetNakupu($uzivatel, $idTricka), 'Musí se uložit objednané tričko');
        self::assertSame(1, $this->pocetNakupu($uzivatel, $idJidla), 'Musí se uložit objednané jídlo');
        foreach ($idsNoci as $idNoci) {
            self::assertSame(1, $this->pocetNakupu($uzivatel, $idNoci), 'Musí se uložit obě objednané noci ubytování');
        }

        self::assertSame(
            6,
            $this->pocetVsechNakupu($uzivatel),
            'Objednávka nesmí obsahovat nic navíc: 2 předměty + tričko + jídlo + 2 noci',
        );
    }

    /**
     * @test
     */
    public function objednaneKusySeOdectouZDostupnychZasob(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka', kusuVyrobeno: 10, cena: 50.0);

        self::assertSame(10, $this->zbyvajiciKusy($idPredmetu), 'Před objednávkou musí být k dispozici všech 10 kusů');

        $this->odesliPrihlasku($uzivatel, [
            'shopP' => [
                $idPredmetu => 3,
            ],
        ]);

        self::assertSame(
            7,
            $this->zbyvajiciKusy($idPredmetu),
            'Po objednání 3 kusů musí zbývat 7 dostupných',
        );
    }

    /**
     * @test
     */
    public function objednavkaUlozicenuPlatnouVOkamzikuNakupu(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka', kusuVyrobeno: 10, cena: 50.0);

        $this->odesliPrihlasku($uzivatel, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);

        $this->zmenCenuPredmetu($idPredmetu, 999.0);

        self::assertSame(
            50.0,
            $this->nakupniCena($uzivatel, $idPredmetu),
            'Nákupní cena se musí zafixovat při nákupu, pozdější změna ceníku ji nesmí přepsat',
        );
    }

    /**
     * @test
     */
    public function opakovaneOdeslaniPrihlaskySeStejnymObsahemNicNezdvoji(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka', kusuVyrobeno: 10, cena: 50.0);
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 10, cena: 300.0);

        $post = [
            'shopP' => [
                $idPredmetu => 2,
            ],
            'shopUbytovaniDny' => $idsNoci,
        ];

        $this->odesliPrihlasku($uzivatel, $post);
        $this->odesliPrihlasku($uzivatel, $post);

        self::assertSame(2, $this->pocetNakupu($uzivatel, $idPredmetu), 'Druhé odeslání téže přihlášky nesmí předmět zdvojit');
        self::assertSame(
            4,
            $this->pocetVsechNakupu($uzivatel),
            'Druhé odeslání téže přihlášky nesmí zdvojit ani ubytování',
        );
    }

    /**
     * @test
     */
    public function upravaPrihlaskySnizenimPoctuKusuVratiZasobu(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka', kusuVyrobeno: 10, cena: 50.0);

        $this->odesliPrihlasku($uzivatel, [
            'shopP' => [
                $idPredmetu => 3,
            ],
        ]);
        self::assertSame(7, $this->zbyvajiciKusy($idPredmetu));

        $this->odesliPrihlasku($uzivatel, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);

        self::assertSame(1, $this->pocetNakupu($uzivatel, $idPredmetu), 'Po snížení počtu musí zůstat jediný kus');
        self::assertSame(9, $this->zbyvajiciKusy($idPredmetu), 'Zrušené kusy se musí vrátit do dostupných zásob');
    }

    /**
     * @test
     */
    public function odhlaseniZUbytovaniSmazeVsechnyNoci(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 10, cena: 300.0);

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny' => $idsNoci,
        ]);
        self::assertSame(2, $this->pocetVsechNakupu($uzivatel));

        $this->odesliPrihlasku($uzivatel, [
            'shopUbytovaniDny'    => [],
            'shopUbytovaniNechci' => '1',
        ]);

        self::assertSame(0, $this->pocetVsechNakupu($uzivatel), 'Zrušení ubytování musí smazat všechny objednané noci');
        self::assertTrue(
            \Uzivatel::zIdUrcite($uzivatel->id())->nechceUbytovani(),
            'Zaškrtnutí „nechci ubytování“ se musí uložit',
        );
    }

    private function zmenCenuPredmetu(
        int $idPredmetu,
        float $cena,
    ): void {
        dbQuery(
            'UPDATE shop_predmety SET cena_aktualni = $1 WHERE id_predmetu = $0',
            [
                0 => $idPredmetu,
                1 => $cena,
            ],
        );
    }

    private function nakupniCena(
        \Uzivatel $uzivatel,
        int $idPredmetu,
    ): float {
        return (float) dbOneCol(
            'SELECT cena_nakupni FROM shop_nakupy WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2',
            [
                0 => $uzivatel->id(),
                1 => $idPredmetu,
                2 => ROCNIK,
            ],
        );
    }

    private function pocetRoliUzivatele(
        \Uzivatel $uzivatel,
        int $idRole,
    ): int {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = $0 AND id_role = $1',
            [
                0 => $uzivatel->id(),
                1 => $idRole,
            ],
        );
    }
}
