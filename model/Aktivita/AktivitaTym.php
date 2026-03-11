<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;

// todo: ORM styl
class AktivitaTym extends \DbObject
{
    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu) {
        if ($kodTymu === 0) {
            $idTymu = (int)dbOneCol(
                'SELECT id FROM akce_tym WHERE id_akce = $0',
                [$idAktivity],
            );
            if (!$idTymu) {
                // kapitán zakládá nový tým
                $kod = rand(1000, 9999);
                dbQuery(
                    'INSERT INTO akce_tym (id_akce, kod, id_kapitan, zalozen) VALUES ($0, $1, $2, NOW())',
                    [$idAktivity, $kod, $idUzivatele],
                );
                $idTymu = (int)dbInsertId();
            }
        } else {
            $idTymu = (int)dbOneCol(
                'SELECT id FROM akce_tym WHERE id_akce = $0 AND kod = $1',
                [$idAktivity, $kodTymu],
            );
        }

        dbInsertUpdate(AkceTymSqlStruktura::AKCE_TYM_PRIHLASENI_TABULKA, [
            AkceTymSqlStruktura::PRIHLASENI_ID_UZIVATELE => $idUzivatele,
            AkceTymSqlStruktura::PRIHLASENI_ID_TYMU      => $idTymu,
        ]);
    }

    public static function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity) {
        dbQuery(
            'DELETE akce_tym_prihlaseni FROM akce_tym_prihlaseni
             JOIN akce_tym ON akce_tym.id = akce_tym_prihlaseni.id_tymu
             WHERE akce_tym_prihlaseni.id_uzivatele = $0 AND akce_tym.id_akce = $1',
            [$idUzivatele, $idAktivity],
        );
    }

    public static function vratKodTymuProUzivatele(int $idUzivatele, int $idAktivity) {
        return (int)dbOneCol(
            'SELECT akce_tym.kod FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             WHERE akce_tym_prihlaseni.id_uzivatele = $0 AND akce_tym.id_akce = $1',
            [$idUzivatele, $idAktivity],
        );
    }
}
