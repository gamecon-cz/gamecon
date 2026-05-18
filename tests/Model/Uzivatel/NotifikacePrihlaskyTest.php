<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\NotifikacePrihlasky;

class NotifikacePrihlaskyTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 777, login_uzivatele = 'TestNotifikace', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Notifikace', email1_uzivatele = 'test.notifikace@example.org'
SQL,
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 77701, nazev = 'vstupné', model_rok = $0, kod_predmetu = CONCAT('notif_vstupne_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::VSTUPNE,
            ],
        ],
    ];

    private function vlozNakup(float $cenaNakupni): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES($0, $1, $2, $3)',
            [
                0 => 777,
                1 => 77701,
                2 => ROCNIK,
                3 => $cenaNakupni,
            ],
        );
    }

    private function upravNakup(float $novaCenaNakupni): void
    {
        dbQuery(
            'UPDATE shop_nakupy SET cena_nakupni = $0 WHERE id_uzivatele = $1 AND id_predmetu = $2 AND rok = $3',
            [
                0 => $novaCenaNakupni,
                1 => 777,
                2 => 77701,
                3 => ROCNIK,
            ],
        );
    }

    /**
     * @test
     */
    public function zmenaDobrovolnehoVstupneMeniSnapshotObjednavek(): void
    {
        $this->vlozNakup(300.0);

        $uzivatel = \Uzivatel::zIdUrcite(777);
        $notifikacePrihlasky = new NotifikacePrihlasky($uzivatel, SystemoveNastaveni::zGlobals());

        $predchoziSnapshot = $notifikacePrihlasky->snapshotObjednavekZUctu();

        $this->upravNakup(200.0);
        $uzivatel->finance()->obnovUdaje();

        $aktualniSnapshot = $notifikacePrihlasky->snapshotObjednavekZUctu();

        self::assertSame(
            [
                'vstupné (300 Kč)' => 1,
            ],
            $predchoziSnapshot['Dobrovolné vstupné'],
        );
        self::assertSame(
            [
                'vstupné (200 Kč)' => 1,
            ],
            $aktualniSnapshot['Dobrovolné vstupné'],
        );
        self::assertNotSame($predchoziSnapshot, $aktualniSnapshot);
    }
}
