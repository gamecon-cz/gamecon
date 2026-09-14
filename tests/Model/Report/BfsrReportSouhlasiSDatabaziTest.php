<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Pravo;
use Gamecon\Report\BfsrReport;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;

/**
 * Report počítá nad slevami, které se nikde neukládají - `shop_nakupy` drží
 * ceníkovou cenu a slevu dopočítává za běhu `Cenik::cena()`. Chyba se proto
 * projeví jako tiše špatné číslo, ne jako pád, a ručně se odhalí až tím, že si
 * někdo počty přesčítá.
 *
 * Tenhle test bere počty přímo z databáze a porovnává je proti tomu, co report
 * napočítá: každý nakoupený kus musí spadnout právě do jedné kategorie a
 * kategorie se musí sečíst na počet řádků v `shop_nakupy`.
 */
class BfsrReportSouhlasiSDatabaziTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 4661;
    private const ID_ROLE = -46614661;

    /**
     * Uživatel má právo na jedno jakékoliv tričko zdarma, takže mu `Cenik`
     * vynuluje nejlevnější kus v košíku - tím vznikne tričko zdarma v jiné
     * barvě než červené či modré, což je přesně případ, který se dřív
     * započítal i mezi placená.
     *
     * Právo je bezpodmínečné (na rozdíl od bonusového {@see Pravo::MODRE_TRICKO_ZDARMA}),
     * takže fixtura nezávisí na prahu z globální konstanty, který může nastavit
     * jiný test dřív v témže procesu.
     */
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 4661, login_uzivatele = 'BfsrSvrsky', jmeno_uzivatele = 'Bfsr', prijmeni_uzivatele = 'Svrsky', email1_uzivatele = 'bfsr.svrsky@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_BFSR_SVRSKY', 'Test role BFSR svršky', '', -1, 'trvala', '')
SQL,
            [
                0 => self::ID_ROLE,
            ],
        ],
        [
            <<<SQL
INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)
SQL,
            [
                0 => self::ID_ROLE,
                1 => Pravo::JAKEKOLIV_TRICKO_ZDARMA,
            ],
        ],
        [
            <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL,
            [
                0 => self::ID_UZIVATELE,
                1 => self::ID_ROLE,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46610, nazev = 'Tričko červené L', model_rok = $0, kod_predmetu = CONCAT('tricko_panske_organizatorske_L_', $0), cena_aktualni = 400, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46611, nazev = 'Tričko modré L', model_rok = $0, kod_predmetu = CONCAT('tricko_panske_vypravecske_L_', $0), cena_aktualni = 350, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46612, nazev = 'Tričko účastnické L', model_rok = $0, kod_predmetu = CONCAT('tricko_panske_ucastnicke_L_', $0), cena_aktualni = 250, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        // Zelené tričko je jediné, co není červené ani modré a zároveň ho
        // nedostane zdarma (zdarma jde nejlevnější kus) - jen díky němu může
        // kategorie "placené" vůbec vyjít nenulově.
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46613, nazev = 'Tričko zelené L', model_rok = $0, kod_predmetu = CONCAT('tricko_panske_zelene_L_', $0), cena_aktualni = 450, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
SELECT $1, id_predmetu, $0, cena_aktualni FROM shop_predmety WHERE id_predmetu BETWEEN 46610 AND 46613
SQL,
            [
                0 => ROCNIK,
                1 => self::ID_UZIVATELE,
            ],
        ],
    ];

    /**
     * @test
     */
    public function kazdySvrsekVDatabaziSpadneDoPraveJedneKategorie(): void
    {
        $svrsky = $this->svrskyPodleReportu();
        self::assertNotEmpty($svrsky, 'V databázi nejsou žádné svršky, test by nic neověřil');

        $pocty = self::prazdnePocty();
        foreach ($svrsky as $svrsek) {
            $kategorie = BfsrReport::kategorieSvrsku($svrsek);
            self::assertArrayHasKey(
                $kategorie,
                $pocty,
                "Svršek '{$svrsek->nazev}' spadl do neznámé kategorie '{$kategorie}'",
            );
            ++$pocty[$kategorie];
        }

        self::assertSame(
            count($svrsky),
            array_sum($pocty),
            'Součet kategorií neodpovídá počtu nakoupených svršků - některý se počítá dvakrát nebo chybí',
        );
    }

    /**
     * Report hlásil nula prodaných triček, přestože se prodávala. Nákup bez
     * slevy musí skončit mezi placenými.
     *
     * @test
     */
    public function placeneSvrskyNejsouNula(): void
    {
        $pocty = self::prazdnePocty();
        foreach ($this->svrskyPodleReportu() as $svrsek) {
            ++$pocty[BfsrReport::kategorieSvrsku($svrsek)];
        }

        self::assertGreaterThan(
            0,
            $pocty[BfsrReport::SVRSEK_PLACENY],
            'Report hlásí nula prodaných svršků, přestože v databázi nákupy bez slevy jsou',
        );
    }

    /**
     * Uživatel koupil čtyři trička a na jedno má nárok zdarma, takže vyjde
     * jedno zdarma a tři zbylá se rozdělí mezi slevu a placené - dohromady čtyři.
     *
     * @test
     */
    public function pocetSvrskuOdpovidaDatabazi(): void
    {
        $vDatabazi = (int) dbOneCol(<<<SQL
            SELECT COUNT(*)
            FROM shop_nakupy
            JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
            WHERE shop_nakupy.id_uzivatele = $0 AND shop_nakupy.rok = $1 AND shop_predmety.typ = $2
            SQL,
            [
                0 => self::ID_UZIVATELE,
                1 => ROCNIK,
                2 => TypPredmetu::TRICKO,
            ],
        );

        self::assertSame(4, $vDatabazi, 'Fixtura měla založit čtyři trička');
        self::assertCount(
            $vDatabazi,
            $this->svrskyPodleReportu(),
            'Report vidí jiný počet svršků, než kolik je nakoupených v databázi',
        );
    }

    /**
     * Svršek zdarma se nesmí započítat ještě jednou jako placený. Dřív se to
     * dělo u triček zdarma v jiné barvě než červené či modré - ta propadla
     * ze své větve dál mezi placená.
     *
     * @test
     */
    public function zadnySvrsekSeNezapocitaDvakrat(): void
    {
        $svrskuZdarma = 0;
        foreach ($this->svrskyPodleReportu() as $svrsek) {
            if ($svrsek->castka !== 0.0 || $svrsek->sleva <= 0.0) {
                continue;
            }
            ++$svrskuZdarma;
            self::assertSame(
                BfsrReport::SVRSEK_ZDARMA,
                BfsrReport::kategorieSvrsku($svrsek),
                "Svršek '{$svrsek->nazev}' je zdarma, ale report ho vykázal jako placený",
            );
        }

        self::assertSame(
            1,
            $svrskuZdarma,
            'Fixtura měla vyrobit právě jedno tričko zdarma, jinak test nic neověřuje',
        );
    }

    /**
     * @return array<string, int>
     */
    private static function prazdnePocty(): array
    {
        return [
            BfsrReport::SVRSEK_ZDARMA    => 0,
            BfsrReport::SVRSEK_SE_SLEVOU => 0,
            BfsrReport::SVRSEK_PLACENY   => 0,
        ];
    }

    /**
     * Svršky tak, jak je vidí report - přes Finance, takže se slevami
     * dopočítanými za běhu.
     *
     * @return list<PolozkaProBfgr>
     */
    private function svrskyPodleReportu(): array
    {
        $svrsky = [];
        foreach (\Uzivatel::zIdUrcite(self::ID_UZIVATELE)->finance()->dejPolozkyProBfgr() as $polozka) {
            if (Predmet::jeToTricko($polozka->kodPredmetu, $polozka->typ)
                || Predmet::jeToTilko($polozka->kodPredmetu, $polozka->typ)
            ) {
                $svrsky[] = $polozka;
            }
        }

        return $svrsky;
    }
}
