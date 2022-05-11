<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;

class SystemoveNastaveni
{

    public function zaznamyDoKonstant() {
        try {
            $zaznamy = dbFetchAll(<<<SQL
SELECT systemove_nastaveni.klic,
       systemove_nastaveni.hodnota,
       systemove_nastaveni.datovy_typ
FROM systemove_nastaveni
SQL
            );
        } catch (\ConnectionException $connectionException) {
            // testy nebo úplně prázdný Gamecon na začátku nemají ještě databázi
            return;
        } catch (\DbException $dbException) {
            if ($dbException->getCode() === 1146 /* table does not exist */) {
                return; // tabulka musí vzniknout SQL migrací
            }
            throw $dbException;
        }
        foreach ($zaznamy as $zaznam) {
            $nazevKonstanty = trim(strtoupper($zaznam['klic']));
            if (!defined($nazevKonstanty)) {
                $hodnota = $this->zkonvertujHodnotuNaTyp($zaznam['hodnota'], $zaznam['datovy_typ']);
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
            case 'date' :
                return (new DateTimeCz($hodnota))->formatDatumDb();
            case 'datetime' :
                return (new DateTimeCz($hodnota))->formatDb();
            case 'string' :
            default :
                return (string)$hodnota;
        }
    }

    public function ulozZmeny(array $zmeny, \Uzivatel $editujici): int {
        $whenThenArray = [];
        $parametry = [];
        $klice = [];
        $indexParametru = 0;
        foreach ($zmeny as $klic => $hodnota) {
            $whenThenArray[] = 'WHEN $' . ($indexParametru++) . ' THEN $' . ($indexParametru++);
            $parametry[] = $klic; // WHEN
            $parametry[] = $hodnota; // THEN
            $klice[] = $klic;
        }
        $whenThen = implode(' ', $whenThenArray);
        $parametry[] = $klice; // bude prevedeno na hodnoty,oddelene,carkou
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET hodnota = (CASE klic $whenThen END)
WHERE klic IN ($$indexParametru)
SQL,
            $parametry
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, hodnota)
SELECT $1, id_nastaveni, hodnota
FROM systemove_nastaveni
WHERE klic IN ($2)
SQL,
            [$editujici->id(), $klice]
        );
        return dbNumRows($updateQuery);
    }

    public function dejVsechnyZaznamyNastaveni(): array {
        return $this->vlozOstatniBonusyVypravecuDoPopisu(
            dbFetchAll($this->dejSqlNaZaVsechnyZaznamyNastaveni())
        );
    }

    private function vlozOstatniBonusyVypravecuDoPopisu(array $zaznamy): array {
        foreach ($zaznamy as &$zaznam) {
            if ($zaznam['klic'] !== 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU') {
                continue;
            }
            $bonusZaStandardni3hAz5hAktivitu = (int)$zaznam['hodnota'];
            $popis = &$zaznam['popis'];
            $popis .= '<hr>vypočtené bonusy:<br>'
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
            dbFetchAll(
                $this->dejSqlNaZaVsechnyZaznamyNastaveni(['systemove_nastaveni.klic IN ($1)']),
                [$klice]
            )
        );
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
                return (int)($bonusZaStandardni3hAz5hAktivitu / 3);
            case 'BONUS_ZA_2H_AKTIVITU' :
                return (int)($bonusZaStandardni3hAz5hAktivitu / 2);
            case 'BONUS_ZA_6H_AZ_7H_AKTIVITU' :
                return (int)($bonusZaStandardni3hAz5hAktivitu * 1.5);
            case 'BONUS_ZA_8H_AZ_9H_AKTIVITU' :
                return (int)($bonusZaStandardni3hAz5hAktivitu * 2);
            case 'BONUS_ZA_10H_AZ_11H_AKTIVITU' :
                return (int)($bonusZaStandardni3hAz5hAktivitu * 2.5);
            case 'BONUS_ZA_12H_AZ_13H_AKTIVITU' :
                return (int)($bonusZaStandardni3hAz5hAktivitu * 3);
            default :
                throw new \LogicException("Neznámý klíč bonusu vypravěče '$klic'");
        }
    }
}
