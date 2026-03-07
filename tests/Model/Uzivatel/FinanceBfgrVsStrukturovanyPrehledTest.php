<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Pravo;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;
use Gamecon\Uzivatel\Finance;

class FinanceBfgrVsStrukturovanyPrehledTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 444, login_uzivatele = 'TestBfgr', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Bfgr', email1_uzivatele = 'test.bfgr@example.org'
SQL,
        // PREDMET A (id 44401)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44401, nazev = 'předmět A', model_rok = $0, kod_predmetu = CONCAT('predmet_a_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::PREDMET],
        ],
        // PREDMET B (id 44402)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44402, nazev = 'předmět B', model_rok = $0, kod_predmetu = CONCAT('predmet_b_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::PREDMET],
        ],
        // UBYTOVANI A (id 44403)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44403, nazev = 'ubytování A', model_rok = $0, kod_predmetu = CONCAT('ubytovani_a_', $0), cena_aktualni = 200, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::UBYTOVANI],
        ],
        // UBYTOVANI B (id 44404)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44404, nazev = 'ubytování B', model_rok = $0, kod_predmetu = CONCAT('ubytovani_b_', $0), cena_aktualni = 250, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 2
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::UBYTOVANI],
        ],
        // TRICKO cervene (id 44405)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44405, nazev = 'červené tričko', model_rok = $0, kod_predmetu = CONCAT('tricko_cervene_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::TRICKO],
        ],
        // TRICKO modre (id 44406)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44406, nazev = 'modré tričko', model_rok = $0, kod_predmetu = CONCAT('tricko_modre_', $0), cena_aktualni = 180, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::TRICKO],
        ],
        // JIDLO A (id 44407)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44407, nazev = 'jídlo A', model_rok = $0, kod_predmetu = CONCAT('jidlo_a_', $0), cena_aktualni = 80, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::JIDLO],
        ],
        // JIDLO B (id 44408)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44408, nazev = 'jídlo B', model_rok = $0, kod_predmetu = CONCAT('jidlo_b_', $0), cena_aktualni = 80, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1, ubytovani_den = 2
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::JIDLO],
        ],
        // VSTUPNE vcas (id 44409)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44409, nazev = 'vstupné', model_rok = $0, kod_predmetu = CONCAT('vstupne_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::VSTUPNE],
        ],
        // VSTUPNE pozde (id 44410)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44410, nazev = 'vstupné pozdě', model_rok = $0, kod_predmetu = CONCAT('vstupne_pozde_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::VSTUPNE],
        ],
        // PARCON (id 44411)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44411, nazev = 'parcon', model_rok = $0, kod_predmetu = CONCAT('parcon_', $0), cena_aktualni = 50, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::PARCON],
        ],
        // PROPLACENI_BONUSU (id 44412)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44412, nazev = 'proplacení bonusu', model_rok = $0, kod_predmetu = CONCAT('proplaceni_', $0), cena_aktualni = 500, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK, 1 => TypPredmetu::PROPLACENI_BONUSU],
        ],
        // PREDMET stary rok (id 44413)
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 44413, nazev = 'starý předmět', model_rok = $0, kod_predmetu = CONCAT('predmet_stary_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [0 => ROCNIK - 1, 1 => TypPredmetu::PREDMET],
        ],
    ];

    private function vlozNakup(int $idPredmetu, float $cenaNakupni): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES($0, $1, $2, $3)',
            [0 => 444, 1 => $idPredmetu, 2 => ROCNIK, 3 => $cenaNakupni],
        );
    }

    private function dejFinanci(): Finance
    {
        return new Finance(\Uzivatel::zIdUrcite(444), 0, SystemoveNastaveni::zGlobals());
    }

    private function pridelPravo(int $idPrava): void
    {
        $unique  = uniqid('', true);
        $idRole  = -random_int(100000, 999999);
        $kodRole = 'TEST_BFGR_' . $idPrava . '_' . $unique;
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ($idPrava, 'test_pravo_$idPrava', 'test')
SQL,
        );
        dbQuery(
            "INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role) VALUES ($idRole, '$kodRole', 'Test role $unique', '', -1, 'trvala', '')",
        );
        dbQuery("INSERT INTO prava_role(id_role, id_prava) VALUES ($idRole, $idPrava)");
        dbQuery("INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES (444, $idRole, 444)");
    }

    /**
     * @param array<PolozkaProBfgr> $polozky
     */
    private function sumaCastekBfgr(array $polozky, ?int $typ = null): float
    {
        $suma = 0.0;
        foreach ($polozky as $polozka) {
            if ($typ === null || $polozka->typ === $typ) {
                $suma += $polozka->castka;
            }
        }

        return round($suma, 2);
    }

    private function sumaCastekStrukturovany(array $polozky, ?int $typ = null): float
    {
        $suma = 0.0;
        foreach ($polozky as $polozka) {
            if ($typ === null || $polozka['typ'] === $typ) {
                $suma += (float)$polozka['castka'];
            }
        }

        return round($suma, 2);
    }

    /** @test */
    public function testPrazdnyNakupBfgr(): void
    {
        $finance = $this->dejFinanci();
        self::assertSame([], $finance->dejPolozkyProBfgr());
    }

    /** @test */
    public function testPrazdnyNakupStrukturovany(): void
    {
        $finance = $this->dejFinanci();
        self::assertSame([], $finance->dejStrukturovanyPrehled());
    }

    /** @test */
    public function testJedenPredmet(): void
    {
        $this->vlozNakup(44401, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(100.0, $bfgr[0]->castka);
        self::assertSame(0.0, $bfgr[0]->sleva);
        self::assertSame(TypPredmetu::PREDMET, $bfgr[0]->typ);
        self::assertSame('1', $bfgr[0]->pocet);

        self::assertCount(1, $strukturovany);
        self::assertSame(100.0, $strukturovany[0]['castka']);
        self::assertSame(1, $strukturovany[0]['pocet']);
        self::assertSame(TypPredmetu::PREDMET, $strukturovany[0]['typ']);
    }

    /** @test */
    public function testVicePredmetuStejnehoIdSeSeskupi(): void
    {
        $this->vlozNakup(44401, 100);
        $this->vlozNakup(44401, 100);
        $this->vlozNakup(44401, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(3, $bfgr);
        foreach ($bfgr as $polozka) {
            self::assertSame(100.0, $polozka->castka);
            self::assertSame('1', $polozka->pocet);
        }

        self::assertCount(1, $strukturovany);
        self::assertSame(3, $strukturovany[0]['pocet']);
        self::assertSame(300.0, $strukturovany[0]['castka']);

        self::assertSame($this->sumaCastekBfgr($bfgr), $this->sumaCastekStrukturovany($strukturovany));
    }

    /** @test */
    public function testRuznePredmetySeSeskupiZvlast(): void
    {
        $this->vlozNakup(44401, 100);
        $this->vlozNakup(44402, 150);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(2, $bfgr);
        self::assertCount(2, $strukturovany);
        self::assertSame($this->sumaCastekBfgr($bfgr), $this->sumaCastekStrukturovany($strukturovany));
    }

    /** @test */
    public function testUbytovaniIndividualne(): void
    {
        $this->vlozNakup(44403, 200);
        $this->vlozNakup(44404, 250);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(2, $bfgr);
        self::assertCount(2, $strukturovany);
        self::assertSame($this->sumaCastekBfgr($bfgr), $this->sumaCastekStrukturovany($strukturovany));
    }

    /** @test */
    public function testTrickoPlnaCena(): void
    {
        $this->vlozNakup(44405, 150);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(150.0, $bfgr[0]->castka);
        self::assertSame(0.0, $bfgr[0]->sleva);

        self::assertCount(1, $strukturovany);
        self::assertSame(150.0, $strukturovany[0]['castka']);
        self::assertSame(1, $strukturovany[0]['pocet']);
    }

    /** @test */
    public function testJidloPlnaCena(): void
    {
        $this->vlozNakup(44407, 80);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(80.0, $bfgr[0]->castka);
        self::assertSame(TypPredmetu::JIDLO, $bfgr[0]->typ);

        self::assertCount(1, $strukturovany);
        self::assertSame(80.0, $strukturovany[0]['castka']);
        self::assertSame(TypPredmetu::JIDLO, $strukturovany[0]['typ']);
    }

    /** @test */
    public function testVstupneVcas(): void
    {
        $this->vlozNakup(44409, 300);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(300.0, $bfgr[0]->castka);
        self::assertSame(TypPredmetu::VSTUPNE, $bfgr[0]->typ);

        self::assertCount(1, $strukturovany);
        self::assertSame(300.0, $strukturovany[0]['castka']);
        self::assertSame(TypPredmetu::VSTUPNE, $strukturovany[0]['typ']);
    }

    /** @test */
    public function testVstupnePozde(): void
    {
        $this->vlozNakup(44410, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(100.0, $bfgr[0]->castka);
        self::assertSame(TypPredmetu::VSTUPNE, $bfgr[0]->typ);

        self::assertCount(1, $strukturovany);
        self::assertSame(100.0, $strukturovany[0]['castka']);
        self::assertSame(TypPredmetu::VSTUPNE, $strukturovany[0]['typ']);
    }

    /** @test */
    public function testProplaceniBonusuJenVBfgr(): void
    {
        $this->vlozNakup(44412, 500);

        $financeBfgr = $this->dejFinanci();
        $bfgr        = $financeBfgr->dejPolozkyProBfgr();
        $bfgrProplaceni = array_filter($bfgr, fn(PolozkaProBfgr $p) => $p->typ === TypPredmetu::PROPLACENI_BONUSU);
        self::assertCount(1, $bfgrProplaceni);

        $financeStrukturovany = $this->dejFinanci();
        $strukturovany        = $financeStrukturovany->dejStrukturovanyPrehled();
        self::assertCount(0, $strukturovany);
    }

    /** @test */
    public function testParconVObou(): void
    {
        $this->vlozNakup(44411, 50);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(TypPredmetu::PARCON, $bfgr[0]->typ);

        self::assertCount(1, $strukturovany);
        self::assertSame(TypPredmetu::PARCON, $strukturovany[0]['typ']);
    }

    /** @test */
    public function testUbytovaniZdarma(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->vlozNakup(44403, 200);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(0.0, $bfgr[0]->castka);
        self::assertSame(200.0, $bfgr[0]->sleva);

        self::assertCount(1, $strukturovany);
        self::assertSame(0.0, $strukturovany[0]['castka']);

        self::assertSame(0.0, $finance->cenaUbytovani());
    }

    /** @test */
    public function testJidloZdarma(): void
    {
        $this->pridelPravo(Pravo::JIDLO_ZDARMA);
        $this->vlozNakup(44407, 80);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(0.0, $bfgr[0]->castka);
        self::assertSame(80.0, $bfgr[0]->sleva);

        self::assertCount(1, $strukturovany);
        self::assertSame(0.0, $strukturovany[0]['castka']);
    }

    /** @test */
    public function testJidloSeSlevou(): void
    {
        $this->pridelPravo(Pravo::JIDLO_SE_SLEVOU);
        $this->vlozNakup(44407, 80);
        $finance       = $this->dejFinanci();
        $sleva         = SystemoveNastaveni::zGlobals()->slevaOrguNaJidloCastka();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(80.0 - $sleva, $bfgr[0]->castka);
        self::assertSame($sleva, $bfgr[0]->sleva);

        self::assertCount(1, $strukturovany);
        self::assertSame(80.0 - $sleva, $strukturovany[0]['castka']);
    }

    /** @test */
    public function testJakekolivTrickoZdarma(): void
    {
        $this->pridelPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA);
        $this->vlozNakup(44405, 150);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertCount(1, $bfgr);
        self::assertSame(0.0, $bfgr[0]->castka);
        self::assertSame(150.0, $bfgr[0]->sleva);

        self::assertCount(1, $strukturovany);
        self::assertSame(0.0, $strukturovany[0]['castka']);
    }

    /** @test */
    public function testModelRokJinyNezAktualniPridavaRokDoNazvu(): void
    {
        $this->vlozNakup(44413, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        $ocekavanyRok = (string)(ROCNIK - 1);
        self::assertStringEndsWith($ocekavanyRok, $bfgr[0]->nazev);
        self::assertStringEndsWith($ocekavanyRok, $strukturovany[0]['nazev']);
    }

    /** @test */
    public function testModelRokStejnyNepridavaRok(): void
    {
        $this->vlozNakup(44401, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        self::assertStringNotContainsString((string)ROCNIK, $bfgr[0]->nazev);
        self::assertStringNotContainsString((string)ROCNIK, $strukturovany[0]['nazev']);
    }

    /** @test */
    public function testSmisenyNakupSumaSouhlasi(): void
    {
        $this->vlozNakup(44401, 100);
        $this->vlozNakup(44401, 100);
        $this->vlozNakup(44403, 200);
        $this->vlozNakup(44407, 80);
        $this->vlozNakup(44409, 300);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        // BFGR sum (all types present, no PROPLACENI_BONUSU)
        $sumaBfgr          = $this->sumaCastekBfgr($bfgr);
        $sumaStrukturovany = $this->sumaCastekStrukturovany($strukturovany);
        self::assertSame($sumaBfgr, $sumaStrukturovany);

        // Individual type totals match Finance getters
        self::assertSame(200.0, round($finance->cenaPredmetu(), 2));
        self::assertSame(200.0, round($finance->cenaUbytovani(), 2));
        self::assertSame(80.0, round($finance->cenaStravy(), 2));
    }

    /** @test */
    public function testSmisenyNakupSeSlevami(): void
    {
        $this->pridelPravo(Pravo::UBYTOVANI_ZDARMA);
        $this->pridelPravo(Pravo::JIDLO_ZDARMA);
        $this->vlozNakup(44403, 200);
        $this->vlozNakup(44407, 80);
        $this->vlozNakup(44401, 100);
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        $bfgrSeSlevou = array_filter($bfgr, fn(PolozkaProBfgr $p) => $p->sleva > 0);
        self::assertGreaterThan(0, count($bfgrSeSlevou));

        // Sum of castka for shared types matches
        $sumaBfgr          = $this->sumaCastekBfgr($bfgr);
        $sumaStrukturovany = $this->sumaCastekStrukturovany($strukturovany);
        self::assertSame($sumaBfgr, $sumaStrukturovany);

        self::assertSame(100.0, round($finance->cenaPredmetu(), 2));
        self::assertSame(0.0, round($finance->cenaUbytovani(), 2));
        self::assertSame(0.0, round($finance->cenaStravy(), 2));
    }

    /** @test */
    public function testKompletniNakupVsechTypu(): void
    {
        $this->vlozNakup(44401, 100);  // PREDMET
        $this->vlozNakup(44403, 200);  // UBYTOVANI
        $this->vlozNakup(44405, 150);  // TRICKO
        $this->vlozNakup(44407, 80);   // JIDLO
        $this->vlozNakup(44409, 300);  // VSTUPNE
        $this->vlozNakup(44411, 50);   // PARCON
        $this->vlozNakup(44412, 500);  // PROPLACENI_BONUSU
        $finance       = $this->dejFinanci();
        $bfgr          = $finance->dejPolozkyProBfgr();
        $strukturovany = $finance->dejStrukturovanyPrehled();

        // BFGR has entries for all types 1-7
        $bfgrTypy = array_unique(array_map(fn(PolozkaProBfgr $p) => $p->typ, $bfgr));
        sort($bfgrTypy);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $bfgrTypy);

        // Strukturovany has entries for types but NOT 7 (PROPLACENI_BONUSU)
        $strukturovanyTypy = array_unique(array_column($strukturovany, 'typ'));
        sort($strukturovanyTypy);
        self::assertNotContains(TypPredmetu::PROPLACENI_BONUSU, $strukturovanyTypy);
        self::assertSame([1, 2, 3, 4, 5, 6], $strukturovanyTypy);

        // Sum comparison: BFGR excluding typ=7 should match strukturovany total
        // But VSTUPNE typ mapping differs (5 in BFGR → 10 in strukturovany), so compare raw sums
        $sumaBfgrBezProplaceni = 0.0;
        foreach ($bfgr as $polozka) {
            if ($polozka->typ !== TypPredmetu::PROPLACENI_BONUSU) {
                $sumaBfgrBezProplaceni += $polozka->castka;
            }
        }
        $sumaBfgrBezProplaceni = round($sumaBfgrBezProplaceni, 2);
        $sumaStrukturovany    = $this->sumaCastekStrukturovany($strukturovany);
        self::assertSame($sumaBfgrBezProplaceni, $sumaStrukturovany);
    }
}
