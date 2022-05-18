<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;

class SystemoveNastaveni
{

    /**
     * @var int
     */
    private $rok;

    public function __construct(int $rok) {
        $this->rok = $rok;
    }

    public function zaznamyDoKonstant() {
        try {
            $zaznamy = dbFetchAll(<<<SQL
SELECT systemove_nastaveni.klic,
       systemove_nastaveni.hodnota,
       systemove_nastaveni.datovy_typ,
       systemove_nastaveni.aktivni
FROM systemove_nastaveni
SQL
            );
        } catch (\ConnectionException $connectionException) {
            // testy nebo úplně prázdný Gamecon na začátku nemají ještě databázi
            return;
        } catch (\DbException $dbException) {
            if (in_array($dbException->getCode(), [1146 /* table does not exist */, 1054 /* new column does not exist */])) {
                return; // tabulka či sloupec musí vzniknout SQL migrací
            }
            throw $dbException;
        }
        foreach ($zaznamy as $zaznam) {
            $nazevKonstanty = trim(strtoupper($zaznam['klic']));
            if (!defined($nazevKonstanty)) {
                $hodnota = $zaznam['aktivni']
                    ? $zaznam['hodnota']
                    : $this->dejVychoziHodnotu($nazevKonstanty);
                $hodnota = $this->zkonvertujHodnotuNaTyp($hodnota, $zaznam['datovy_typ']);
                define($nazevKonstanty, $hodnota);
            }
        }
    }

    public function zkonvertujHodnotuNaTyp($hodnota, string $datovyTyp) {
        switch (strtolower(trim($datovyTyp))) {
            case 'boolean' :
            case 'bool' :
                return (bool)$hodnota;
            case 'integer' :
            case 'int' :
                return (int)$hodnota;
            case 'number' :
            case 'float' :
                return (float)$hodnota;
            case 'date' : // když to změníš, rozbiješ JS systemove-nastaveni.js
                return (new DateTimeCz($hodnota))->formatDatumDb();
            case 'datetime' : // když to změníš, rozbiješ JS systemove-nastaveni.js
                return (new DateTimeCz($hodnota))->formatDb();
            case 'string' :
            default :
                return (string)$hodnota;
        }
    }

    public function ulozZmenuHodnoty($hodnota, string $klic, \Uzivatel $editujici): int {
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET hodnota = $1
WHERE klic = $2
SQL,
            [$this->formatujHodnotuProDb($hodnota, $klic), $klic]
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, hodnota)
SELECT $1, id_nastaveni, hodnota
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic]
        );
        return dbNumRows($updateQuery);
    }

    public function ulozZmenuPlatnosti(bool $aktivni, string $klic, \Uzivatel $editujici): int {
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET aktivni = $1
WHERE klic = $2
SQL,
            [$aktivni, $klic]
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, aktivni)
SELECT $1, id_nastaveni, aktivni
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic]
        );
        return dbNumRows($updateQuery);
    }

    private function formatujHodnotuProDb($hodnota, string $klic) {
        switch ($this->dejDatovyTyp($klic)) {
            case 'date' :
                return $hodnota
                    ? DateTimeCz::createFromFormat('j. n. Y', $hodnota)->formatDatumDb()
                    : $hodnota;
            case 'datetime' :
                return $hodnota
                    ? DateTimeCz::createFromFormat('j. n. Y H:i:s', $hodnota)->formatDb()
                    : $hodnota;
            default :
                return $hodnota;
        }
    }

    private function dejDatovyTyp(string $klic): ?string {
        static $datoveTypy;
        if ($datoveTypy === null) {
            $datoveTypy = dbArrayCol(<<<SQL
SELECT klic, datovy_typ
FROM systemove_nastaveni
SQL
            );
        }
        return $datoveTypy[$klic] ?? null;
    }

    public function dejVsechnyZaznamyNastaveni(): array {
        return $this->vlozOstatniBonusyVypravecuDoPopisu(
            $this->pridejVychoziHodnoty(
                dbFetchAll($this->dejSqlNaZaVsechnyZaznamyNastaveni())
            )
        );
    }

    private function vlozOstatniBonusyVypravecuDoPopisu(array $zaznamy): array {
        foreach ($zaznamy as &$zaznam) {
            if ($zaznam['klic'] !== 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU') {
                continue;
            }
            $bonusZaStandardni3hAz5hAktivitu = (int)$zaznam['hodnota'];
            $popis = &$zaznam['popis'];
            $popis .= '<hr><i>vypočtené bonusy</i>:<br>'
                . 'BONUS_ZA_1H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_1H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                . 'BONUS_ZA_2H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_2H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                . '•••<br>'
                . 'BONUS_ZA_6H_AZ_7H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_6H_AZ_7H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                . 'BONUS_ZA_8H_AZ_9H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_8H_AZ_9H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                . 'BONUS_ZA_10H_AZ_11H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_10H_AZ_11H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                . 'BONUS_ZA_12H_AZ_13H_AKTIVITU = ' . self::spocitejBonusVypravece('BONUS_ZA_12H_AZ_13H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>';
        }
        return $zaznamy;
    }

    private function dejSqlNaZaVsechnyZaznamyNastaveni(array $whereArray = ['1']): string {
        $where = implode(' AND ', $whereArray);
        return <<<SQL
SELECT systemove_nastaveni.klic,
       systemove_nastaveni.hodnota,
       systemove_nastaveni.datovy_typ,
       systemove_nastaveni.aktivni,
       systemove_nastaveni.nazev,
       systemove_nastaveni.popis,
       COALESCE(naposledy, systemove_nastaveni.zmena_kdy) AS kdy,
       posledni_s_uzivatelem.id_uzivatele,
       systemove_nastaveni.skupina
FROM systemove_nastaveni
LEFT JOIN (
    SELECT posledni_log.naposledy, systemove_nastaveni_log.id_nastaveni, systemove_nastaveni_log.id_uzivatele
    FROM (SELECT MAX(kdy) AS naposledy, id_nastaveni
        FROM systemove_nastaveni_log
        GROUP BY id_nastaveni
    ) AS posledni_log
    JOIN systemove_nastaveni_log on posledni_log.id_nastaveni = systemove_nastaveni_log.id_nastaveni
        AND naposledy = systemove_nastaveni_log.kdy
    GROUP BY systemove_nastaveni_log.id_nastaveni, systemove_nastaveni_log.id_uzivatele
) AS posledni_s_uzivatelem ON systemove_nastaveni.id_nastaveni = posledni_s_uzivatelem.id_nastaveni
WHERE {$where}
ORDER BY systemove_nastaveni.poradi
SQL;
    }

    public function dejZaznamyNastaveniPodleKlicu(array $klice): array {
        if (!$klice) {
            return [];
        }
        return $this->vlozOstatniBonusyVypravecuDoPopisu(
            $this->pridejVychoziHodnoty(
                dbFetchAll(
                    $this->dejSqlNaZaVsechnyZaznamyNastaveni(['systemove_nastaveni.klic IN ($1)']),
                    [$klice]
                )
            )
        );
    }

    private function pridejVychoziHodnoty(array $zaznamy): array {
        return array_map(
            function (array &$zaznam) {
                $zaznam['vychozi_hodnota'] = $this->dejVychoziHodnotu($zaznam['klic']);
                $zaznam['popis'] .= '<hr><i>výchozí hodnota</i>: ' . (
                    $zaznam['vychozi_hodnota'] !== ''
                        ? htmlspecialchars($zaznam['vychozi_hodnota'])
                        : '<i>' . htmlspecialchars('>>>není<<<') . '</i>'
                    );
                return $zaznam;
            },
            $zaznamy
        );
    }

    public function dejVychoziHodnotu(string $klic) {
        switch ($klic) {
            case 'GC_BEZI_OD' :
                return DateTimeGamecon::spocitejZacatekGameconu($this->rok)->formatDb();
            case 'GC_BEZI_DO' :
                return DateTimeGamecon::spocitejKonecGameconu($this->rok)->formatDb();
            default :
                return '';
        }
    }

    /**
     * @param string $klic
     * @param int $bonusZaStandardni3hAz5hAktivitu Nelze použít konstantu při změně v databázi, protože konstanta se změní až při dalším načtení PHP
     * @return int
     */
    public static function spocitejBonusVypravece(
        string $klic,
        int    $bonusZaStandardni3hAz5hAktivitu = BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU
    ): int {
        switch ($klic) {
            case 'BONUS_ZA_1H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 4);
            case 'BONUS_ZA_2H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 2);
            case 'BONUS_ZA_6H_AZ_7H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 1.5);
            case 'BONUS_ZA_8H_AZ_9H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2);
            case 'BONUS_ZA_10H_AZ_11H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2.5);
            case 'BONUS_ZA_12H_AZ_13H_AKTIVITU' :
                return self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 3);
            default :
                throw new \LogicException("Neznámý klíč bonusu vypravěče '$klic'");
        }
    }

    private static function zakrouhli(float $cislo): int {
        return (int)round($cislo, 0);
    }
}
