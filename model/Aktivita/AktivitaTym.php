<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;

// todo: ORM styl
class AktivitaTym extends \DbObject
{
    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu, bool $ignorovatLimity = false) {
        self::zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);

        if ($kodTymu === 0) {
            $idTymu = self::vytvorNovyTym($idUzivatele, $idAktivity, $ignorovatLimity);
        } else {
            $idTymu = self::najdiTymPodleKodu($idAktivity, $kodTymu);
            if (!$ignorovatLimity) {
                self::zkontrolujKapacituTymu($idTymu);
            }
        }

        dbInsertUpdate(AkceTymSqlStruktura::AKCE_TYM_PRIHLASENI_TABULKA, [
            AkceTymSqlStruktura::PRIHLASENI_ID_UZIVATELE => $idUzivatele,
            AkceTymSqlStruktura::PRIHLASENI_ID_TYMU      => $idTymu,
        ]);
    }

    private static function zkontrolujZeNeniVJinemTymu(int $idUzivatele, int $idAktivity): void {
        $existujiciTym = (int)dbOneCol(
            'SELECT akce_tym_prihlaseni.id_tymu FROM akce_tym_prihlaseni
             JOIN akce_tym ON akce_tym.id = akce_tym_prihlaseni.id_tymu
             WHERE akce_tym_prihlaseni.id_uzivatele = $0 AND akce_tym.id_akce = $1',
            [$idUzivatele, $idAktivity],
        );
        if ($existujiciTym) {
            throw new \Chyba('Už jsi přihlášen v týmu na této aktivitě');
        }
    }

    private static function vytvorNovyTym(int $idUzivatele, int $idAktivity, bool $ignorovatLimity): int {
        if (!$ignorovatLimity) {
            self::zkontrolujMaxPocetTymu($idAktivity);
        }

        $kod = rand(1000, 9999);
        dbQuery(
            'INSERT INTO akce_tym (id_akce, kod, id_kapitan, zalozen) VALUES ($0, $1, $2, NOW())',
            [$idAktivity, $kod, $idUzivatele],
        );
        return (int)dbInsertId();
    }

    private static function zkontrolujMaxPocetTymu(int $idAktivity): void {
        $teamKapacita = dbOneCol(
            'SELECT team_kapacita FROM akce_seznam WHERE id_akce = $0',
            [$idAktivity],
        );
        if ($teamKapacita === null) {
            return; // bez limitu počtu týmů
        }
        $pocetTymu = (int)dbOneCol(
            'SELECT COUNT(*) FROM akce_tym WHERE id_akce = $0',
            [$idAktivity],
        );
        if ($pocetTymu >= (int)$teamKapacita) {
            throw new \Chyba('Na aktivitě je už maximální počet týmů');
        }
    }

    private static function najdiTymPodleKodu(int $idAktivity, int $kodTymu): int {
        $idTymu = (int)dbOneCol(
            'SELECT id FROM akce_tym WHERE id_akce = $0 AND kod = $1',
            [$idAktivity, $kodTymu],
        );
        if (!$idTymu) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }
        return $idTymu;
    }

    private static function zkontrolujKapacituTymu(int $idTymu): void {
        $pocetClenu = (int)dbOneCol(
            'SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE id_tymu = $0',
            [$idTymu],
        );
        // limit nastavený kapitánem na týmu, jinak team_max z aktivity
        $limit = dbOneCol(
            'SELECT COALESCE(akce_tym.`limit`, akce_seznam.team_max)
             FROM akce_tym
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym.id_akce
             WHERE akce_tym.id = $0',
            [$idTymu],
        );
        if ($limit !== null && $pocetClenu >= (int)$limit) {
            throw new \Chyba('Tým je už plný');
        }
    }

    public static function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity) {
        $tym = dbOneLine(
            'SELECT akce_tym.id, akce_tym.id_kapitan FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             WHERE akce_tym_prihlaseni.id_uzivatele = $0 AND akce_tym.id_akce = $1',
            [$idUzivatele, $idAktivity],
        );
        if (!$tym) {
            return; // uživatel není v žádném týmu na této aktivitě
        }
        $idTymu = (int)$tym['id'];
        $idKapitan = (int)$tym['id_kapitan'];

        dbBegin();
        try {
            // smazat uživatele z týmu
            dbQuery(
                'DELETE FROM akce_tym_prihlaseni WHERE id_uzivatele = $0 AND id_tymu = $1',
                [$idUzivatele, $idTymu],
            );

            $zbyvajiciClen = dbOneCol(
                'SELECT akce_tym_prihlaseni.id_uzivatele FROM akce_tym_prihlaseni
                 WHERE akce_tym_prihlaseni.id_tymu = $0
                 ORDER BY akce_tym_prihlaseni.id ASC
                 LIMIT 1',
                [$idTymu],
            );

            if (!$zbyvajiciClen) {
                // tým je prázdný → smazat
                dbQuery('DELETE FROM akce_tym WHERE id = $0', [$idTymu]);
                // dual-write: vyčistit legacy sloupce
                dbQuery(
                    'UPDATE akce_seznam SET zamcel = NULL, zamcel_cas = NULL, team_nazev = NULL WHERE id_akce = $0',
                    [$idAktivity],
                );
            } elseif ($idUzivatele === $idKapitan) {
                // odcházel kapitán → předat kapitánství nejstaršímu členovi
                $novyKapitan = (int)$zbyvajiciClen;
                dbQuery(
                    'UPDATE akce_tym SET id_kapitan = $0 WHERE id = $1',
                    [$novyKapitan, $idTymu],
                );
                // dual-write: aktualizovat legacy sloupec
                dbQuery(
                    'UPDATE akce_seznam SET zamcel = $0 WHERE id_akce = $1',
                    [$novyKapitan, $idAktivity],
                );
            }

            dbCommit();
        } catch (\Exception $e) {
            dbRollback();
            throw $e;
        }
    }

    public static function infoOTymuUzivatele(int $idUzivatele, int $idAktivity): ?InfoOTymu {
        $row = dbOneLine(
            'SELECT
                (SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE akce_tym_prihlaseni.id_tymu = akce_tym.id) AS pocet_clenu,
                COALESCE(akce_tym.`limit`, akce_seznam.team_max) AS team_limit
             FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym.id_akce
             WHERE akce_tym_prihlaseni.id_uzivatele = $0 AND akce_tym.id_akce = $1',
            [$idUzivatele, $idAktivity],
        );
        if (!$row) {
            return null;
        }
        return new InfoOTymu(
            pocetClenu: (int)$row['pocet_clenu'],
            limit: $row['team_limit'] !== null ? (int)$row['team_limit'] : null,
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

    public static function jeKapitanem(int $idUzivatele, int $idAktivity): bool {
        return (bool)dbOneCol(
            'SELECT 1 FROM akce_tym WHERE id_akce = $0 AND id_kapitan = $1 LIMIT 1',
            [$idAktivity, $idUzivatele],
        );
    }

    public static function casZalozeniNejstarsihoTymu(int $idAktivity): ?string {
        return dbOneCol(
            'SELECT MIN(zalozen) FROM akce_tym WHERE id_akce = $0',
            [$idAktivity],
        ) ?: null;
    }

    public static function maAktivitaTym(int $idAktivity): bool {
        return (bool)dbOneCol(
            'SELECT 1 FROM akce_tym WHERE id_akce = $0 LIMIT 1',
            [$idAktivity],
        );
    }

    /** @return VerejnyTym[] */
    public static function verejneTymy(int $idAktivity): array {
        $rows = dbFetchAll(
            'SELECT akce_tym.kod, akce_tym.nazev,
                    COALESCE(akce_tym.`limit`, akce_seznam.team_max) AS team_limit,
                    (SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE akce_tym_prihlaseni.id_tymu = akce_tym.id) AS pocet_clenu
             FROM akce_tym
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym.id_akce
             WHERE akce_tym.id_akce = $0 AND akce_tym.verejny = 1',
            [$idAktivity],
        );
        return array_map(
            fn(array $row) => new VerejnyTym(
                kod: (int)$row['kod'],
                nazev: $row['nazev'],
                pocetClenu: (int)$row['pocet_clenu'],
                limit: $row['team_limit'] !== null ? (int)$row['team_limit'] : null,
            ),
            $rows,
        );
    }

    public static function nastavVerejnostTymu(int $kodTymu, int $idAktivity, bool $verejny): void {
        dbQuery(
            'UPDATE akce_tym SET verejny = $0 WHERE id_akce = $1 AND kod = $2',
            [(int)$verejny, $idAktivity, $kodTymu],
        );
    }

    public static function zkontrolujZeJeKapitan(int $kodTymu, int $idAktivity, int $idUzivatele): void {
        $kapitan = dbOneCol(
            'SELECT id_kapitan FROM akce_tym WHERE id_akce = $0 AND kod = $1',
            [$idAktivity, $kodTymu],
        );
        if ($kapitan === null) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }
        if ((int)$kapitan !== $idUzivatele) {
            throw new \Chyba('Tuto akci může provést pouze kapitán týmu');
        }
    }

    public static function verejnostTymuPodleKodu(int $kodTymu, int $idAktivity): ?bool {
        $verejny = dbOneCol(
            'SELECT verejny FROM akce_tym WHERE id_akce = $0 AND kod = $1',
            [$idAktivity, $kodTymu],
        );
        return $verejny !== null ? (bool)(int)$verejny : null;
    }

    public static function expirovaneTymyIds(int $hajeniHodin): array {
        return dbOneArray(
            'SELECT akce_tym.id FROM akce_tym
             WHERE akce_tym.zalozen < NOW() - INTERVAL $0 HOUR
               AND akce_tym.verejny = 0',
            [$hajeniHodin],
        );
    }
}
