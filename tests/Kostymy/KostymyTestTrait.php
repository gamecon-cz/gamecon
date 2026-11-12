<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StrihKostymuEnum;
use Gamecon\Kostymy\VelikostKostymuEnum;

/**
 * Data pro testy půjčovny kostýmů přímo v databázi (legacy spojení), bez pravidel služeb.
 */
trait KostymyTestTrait
{
    private static function vlozKostym(
        string $nazev = 'Plášť',
        StavKostymuEnum $stav = StavKostymuEnum::K_DISPOZICI,
    ): int {
        dbInsert('kostym', [
            'nazev'            => $nazev,
            'popis'            => '',
            'velikost'         => VelikostKostymuEnum::M->value,
            'miry'             => '',
            'strih'            => StrihKostymuEnum::UNISEX->value,
            'kategorie'        => '',
            'stav'             => $stav->value,
            'interni_poznamka' => '',
        ]);

        return (int) dbInsertId();
    }

    private static function vlozAktivitu(
        ?string $zacatek = '2099-01-01 10:00:00',
        ?string $konec = '2099-01-01 14:00:00',
        int $typ = TypAktivity::LARP,
    ): int {
        dbInsert('akce_seznam', [
            'nazev_akce' => 'Larp ' . uniqid(),
            'stav'       => StavAktivity::AKTIVOVANA,
            'typ'        => $typ,
            'teamova'    => 0,
            'kapacita'   => 10,
            'zacatek'    => $zacatek,
            'konec'      => $konec,
            'rok'        => ROCNIK,
        ]);

        return (int) dbInsertId();
    }

    private static function vlozPrihlasku(
        int $idAktivity,
        int $idUzivatele,
        int $stav = StavPrihlaseni::PRIHLASEN,
    ): void {
        dbInsert('akce_prihlaseni', [
            'id_akce'             => $idAktivity,
            'id_uzivatele'        => $idUzivatele,
            'id_stavu_prihlaseni' => $stav,
        ]);
    }

    private static function vlozRezervaci(int $idKostymu, int $idAktivity, int $idUzivatele): int
    {
        dbInsert('kostym_rezervace', [
            'id_kostymu'   => $idKostymu,
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idUzivatele,
            'rezervovano'  => '2026-01-01 00:00:00',
        ]);

        return (int) dbInsertId();
    }

    private static function vlozVydej(int $idKostymu, ?int $idRezervace, ?int $idAktivity, int $idUzivatele): int
    {
        dbInsert('kostym_vydej', [
            'id_kostymu'   => $idKostymu,
            'id_rezervace' => $idRezervace,
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idUzivatele,
            'vydano'       => '2026-01-01 00:00:00',
            'poznamka'     => '',
        ]);

        return (int) dbInsertId();
    }

    /**
     * @return list<array{id_kostymu: string, id_akce: string, id_uzivatele: string}>
     */
    private static function rezervaceAktivity(int $idAktivity): array
    {
        return dbFetchAll(<<<'SQL'
SELECT id_kostymu, id_akce, id_uzivatele FROM kostym_rezervace WHERE id_akce = $0 ORDER BY id
SQL, [$idAktivity]);
    }
}
