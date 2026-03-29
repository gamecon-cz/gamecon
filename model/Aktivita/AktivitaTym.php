<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;

// todo(tym): zrevidovat že se zde nenachází nějaké metody co nedávájí smysl (např. kapitán aktivity)
// todo(tym): ORM styl
class AktivitaTym extends \DbObject
{
    public const HAJENI_TEAMU_HODIN    = 72; // počet hodin po kterýc aktivita automatick vykopává nesestavený tým

    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    // todo(tym): Je potřeba zajistit že před přidáním účastníka do týmu je přihlášený na všechny aktivity týmu
    // todo(tym): dochází ke zdvojené kontrole na kapacitu
    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu, bool $ignorovatLimity = false) {
        self::zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);

        if ($kodTymu === 0) {
            $idTymu = self::vytvorNovyTym($idUzivatele, $idAktivity, $ignorovatLimity);
        } else {
            $idTymu = self::najdiTymPodleKodu($idAktivity, $kodTymu);
            if (!$ignorovatLimity) {
                self::zkontrolujVolnouKapacituVTymu($idTymu);
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
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
            [$idUzivatele, $idAktivity],
        );
        if ($existujiciTym) {
            throw new \Chyba('Už jsi přihlášen v týmu na této aktivitě');
        }
    }

    // todo(tym): Je potřeba zajistit že před přidáním účastníka do týmu je přihlášený na všechny aktivity týmu
    /**
     * Před přidáním do týmu musí být uživatel přihlášen na všechny aktivty
     */
    private static function vytvorNovyTym(int $idUzivatele, int $idAktivity, bool $ignorovatLimity): int {
        if (!$ignorovatLimity) {
            self::zkontrolujMuzeZalozitTym($idAktivity);
        }

        $existujiciKody = array_map(
            'intval',
            dbOneArray(
                'SELECT akce_tym.kod FROM akce_tym
                 JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0',
                [$idAktivity],
            ),
        );
        do {
            $kod = rand(1000, 9999);
        } while (in_array($kod, $existujiciKody, true));
        dbQuery(
            'INSERT INTO akce_tym (kod, id_kapitan, zalozen) VALUES ($0, $1, NOW())',
            [$kod, $idUzivatele],
        );
        $idTymu = (int)dbInsertId();
        self::pridejTymNaAktivitu($idTymu, $idAktivity);
        return $idTymu;
    }

    public static function zkontrolujMuzeZalozitTym(int $idAktivity) {
        if (!self::muzePridatDalsiTym($idAktivity)) {
            throw new \Chyba('Na aktivitě je už maximální počet týmů');
        }
    }


    /** @return [int|null, int] [$team_kapacita, $pocetAktualnych] nebo null pokud team_kapacita není nastaven */
    public static function tymAktivitaKapacity(int $idAktivity): ?array {
        $limit = dbOneCol(
            'SELECT team_kapacita FROM akce_seznam WHERE id_akce = $0',
            [$idAktivity],
        );
        $pocetAktualnych = (int)dbOneCol(
            'SELECT COUNT(*) FROM akce_tym_akce WHERE id_akce = $0',
            [$idAktivity],
        );
        return $limit !== null ? [(int)$limit, $pocetAktualnych] : null;
    }

    /**
     * Ověří zda se může založit další tým (je místo v kapacitě).
     * @return bool true pokud se může založit, false pokud je kapacita plná
     */
    public static function muzePridatDalsiTym(int $idAktivity): bool {
        $info = self::tymAktivitaKapacity($idAktivity);
        if ($info === null) {
            return true; // bez limitu - může se vždycky založit
        }
        [$limit, $pocet] = $info;
        return $pocet < $limit;
    }

    public static function najdiTymPodleKodu(int $idAktivity, int $kodTymu): int {
        $idTymu = (int)dbOneCol(
            'SELECT akce_tym.id FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0
             WHERE akce_tym.kod = $1',
            [$idAktivity, $kodTymu],
        );
        if (!$idTymu) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }
        return $idTymu;
    }

    public static function zkontrolujVolnouKapacituVTymu(int $idTymu): void {
        $pocetClenu = (int)dbOneCol(
            'SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE id_tymu = $0',
            [$idTymu],
        );
        // limit nastavený kapitánem na týmu, jinak team_max z aktivity
        $limit = dbOneCol(
            'SELECT COALESCE(akce_tym.`limit`, akce_seznam.team_max)
             FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_tym.id = $0
             LIMIT 1',
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
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
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
            } elseif ($idUzivatele === $idKapitan) {
                // odcházel kapitán → předat kapitánství nejstaršímu členovi
                $novyKapitan = (int)$zbyvajiciClen;
                dbQuery(
                    'UPDATE akce_tym SET id_kapitan = $0 WHERE id = $1',
                    [$novyKapitan, $idTymu],
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
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
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
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
            [$idUzivatele, $idAktivity],
        );
    }

    public static function jeKapitanem(int $idUzivatele, int $idAktivity): bool {
        return (bool)dbOneCol(
            'SELECT 1 FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0
             WHERE akce_tym.id_kapitan = $1 LIMIT 1',
            [$idAktivity, $idUzivatele],
        );
    }

    public static function maAktivitaTym(int $idAktivity): bool {
        return (bool)dbOneCol(
            'SELECT 1 FROM akce_tym_akce WHERE id_akce = $0 LIMIT 1',
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
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_tym.verejny = 1',
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
            'UPDATE akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             SET akce_tym.verejny = $0
             WHERE akce_tym.kod = $2',
            [(int)$verejny, $idAktivity, $kodTymu],
        );
    }

    public static function zkontrolujZeJeKapitan(int $kodTymu, int $idAktivity, int $idUzivatele): void {
        $kapitan = dbOneCol(
            'SELECT akce_tym.id_kapitan FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0
             WHERE akce_tym.kod = $1',
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
            'SELECT akce_tym.verejny FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $0
             WHERE akce_tym.kod = $1',
            [$idAktivity, $kodTymu],
        );
        return $verejny !== null ? (bool)(int)$verejny : null;
    }

    /**
     * Vrátí počet volných míst ve všech veřejných týmech na aktivitě.
     * @return int Součet volných míst (limit - počet členů) ve všech veřejných týmech
     */
    // todo(tym): tým bez limitu (limit === null) se počítá jako 0 volných míst - pokud tým nemá limit, měl by se asi počítat jako neomezený
    public static function pocetVolnychMistVVerejnychTymech(int $idAktivity): int {
        $tymy = self::verejneTymy($idAktivity);
        $volnaMista = 0;
        foreach ($tymy as $tym) {
            if ($tym->limit !== null) {
                $volnaMista += max(0, $tym->limit - $tym->pocetClenu);
            }
        }
        return $volnaMista;
    }

    public static function expirovaneTymyIds(?int $hajeniHodin = null): array {
        $hajeniHodin = $hajeniHodin ?? self::HAJENI_TEAMU_HODIN;
        return dbOneArray(
            'SELECT akce_tym.id FROM akce_tym
             WHERE akce_tym.zalozen < NOW() - INTERVAL $0 HOUR
               AND akce_tym.verejny = 0',
            [$hajeniHodin],
        );
    }

    /**
     * Přidá tým na aktivitu (záznam do akce_tym_akce).
     * Pokud tým na aktivitě už je, nic se nestane.
     */
    public static function pridejTymNaAktivitu(int $idTymu, int $idAktivity): void {
        dbQuery(
            'INSERT IGNORE INTO akce_tym_akce (id_tymu, id_akce) VALUES ($0, $1)',
            [$idTymu, $idAktivity],
        );
    }

    /**
     * Vrátí ID týmu, ve kterém je uživatel na dané aktivitě, nebo null.
     */
    public static function idTymuUzivatele(int $idUzivatele, int $idAktivity): ?int {
        $id = dbOneCol(
            'SELECT akce_tym.id FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
            [$idUzivatele, $idAktivity],
        );
        return $id !== null ? (int)$id : null;
    }

    /** Vrátí timestamp založení týmu uživatele na dané aktivitě, nebo null */
    public static function casZalozeniTymuUzivatele(int $idUzivatele, int $idAktivity): ?int {
        $zalozen = dbOneCol(
            'SELECT UNIX_TIMESTAMP(akce_tym.zalozen) FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = $1
             WHERE akce_tym_prihlaseni.id_uzivatele = $0',
            [$idUzivatele, $idAktivity],
        );
        return $zalozen !== null ? (int)$zalozen : null;
    }

    /**
     * Vrátí ID aktivit, na které je tým přihlášen, kromě zadané výjimky.
     * @return int[]
     */
    public static function idDalsichAktivitTymu(int $idTymu, int $vyjmaIdAktivity = -1): array {
        if ($vyjmaIdAktivity >= 0) {
            return array_map(
                    'intval',
                    dbOneArray(
                        'SELECT id_akce FROM akce_tym_akce WHERE id_tymu = $0 AND id_akce != $1',
                        [$idTymu, $vyjmaIdAktivity],
                    ),
                );
        } else {
        return array_map(
                'intval',
                dbOneArray(
                    'SELECT id_akce FROM akce_tym_akce WHERE id_tymu = $0',
                    [$idTymu],
                ),
            );
        }
    }
}
