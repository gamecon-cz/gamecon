<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;

// todo: ORM styl
class AktivitaTym extends \DbObject
{
    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu) {
        $kodTymuProPrihlaseni = $kodTymu;
        if ($kodTymuProPrihlaseni === 0) {
            // todo: if exists then generate new code
            // dbFetchRow()
            $kodTymuProPrihlaseni = rand(1000, 9999);
        }

        dbInsertUpdate(AkceTymSqlStruktura::AKCE_TYM_TABULKA, [
            AkceTymSqlStruktura::ID_UZIVATELE => $idUzivatele,
            AkceTymSqlStruktura::ID_AKCE => $idAktivity,
            AkceTymSqlStruktura::KOD_TYMU => $kodTymuProPrihlaseni,
        ]);
    }

    // todo: nefunguje ?
    public static function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity) {
        dbDelete('akce_prihlaseni', [
            AkceTymSqlStruktura::ID_UZIVATELE => $idUzivatele,
            AkceTymSqlStruktura::ID_AKCE => $idAktivity,
        ]);
    }


    public static function vratKodTymuProUzivatele(int $idUzivatele, int $idAktivity) {
        return (int)dbFetchRow("SELECT * FROM `akce_tym` WHERE id_uzivatele = $0 AND id_akce = $1", [
            $idUzivatele,
            $idAktivity
        ])[AkceTymSqlStruktura::KOD_TYMU];
    }

}
