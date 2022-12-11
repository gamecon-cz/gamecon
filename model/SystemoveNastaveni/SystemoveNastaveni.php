<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;
use Gamecon\SystemoveNastaveni\Exceptions\InvalidSystemSettingsValue;

class SystemoveNastaveni
{

    public static function vytvorZGlobals(): self {
        return new static(
            ROK,
            new \DateTimeImmutable(),
            parse_url(URL_WEBU, PHP_URL_HOST) === 'beta.gamecon.cz',
            parse_url(URL_WEBU, PHP_URL_HOST) === 'localhost'
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

    /**
     * @var int
     */
    private $rok;
    /**
     * @var \DateTimeImmutable
     */
    private $ted;
    private bool $jsmeNaBete;
    private bool $jsmeNaLocale;

    public function __construct(
        int                $rok,
        \DateTimeImmutable $ted,
        bool               $jsmeNaBete,
        bool               $jsmeNaLocale
    ) {
        $this->rok          = $rok;
        $this->ted          = $ted;
        $this->jsmeNaBete   = $jsmeNaBete;
        $this->jsmeNaLocale = $jsmeNaLocale;
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
        } catch (\mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1049) { // Unknown database
                return;
            }
            throw $exception;
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
        $this->definujOdvozeneKonstanty();
    }

    private function definujOdvozeneKonstanty() {
        @define('MODRE_TRICKO_ZDARMA_OD', 3 * BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU); // hodnota slevy od které má subjekt nárok na modré tričko

        @define('BONUS_ZA_1H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_1H_AKTIVITU'));
        @define('BONUS_ZA_2H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_2H_AKTIVITU'));
        @define('BONUS_ZA_6H_AZ_7H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_6H_AZ_7H_AKTIVITU'));
        @define('BONUS_ZA_8H_AZ_9H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_8H_AZ_9H_AKTIVITU'));
        @define('BONUS_ZA_10H_AZ_11H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_10H_AZ_11H_AKTIVITU'));
        @define('BONUS_ZA_12H_AZ_13H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_12H_AZ_13H_AKTIVITU'));
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
        try {
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
        } catch (InvalidDateTimeFormat $invalidDateTimeFormat) {
            throw new InvalidSystemSettingsValue(
                sprintf(
                    "Can not convert %s (%s) into DB format: %s",
                    var_export($hodnota, true),
                    var_export($klic, true),
                    $invalidDateTimeFormat->getMessage()
                )
            );
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
            $popis                           = &$zaznam['popis'];
            $popis                           .= '<hr><i>vypočtené bonusy</i>:<br>'
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
       systemove_nastaveni.skupina,
       systemove_nastaveni.poradi
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
            function (array $zaznam) {
                $zaznam['vychozi_hodnota'] = $this->dejVychoziHodnotu($zaznam['klic']);
                $zaznam['popis']           .= '<hr><i>výchozí hodnota</i>: ' . (
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
            case 'REG_GC_DO' :
                return DateTimeGamecon::spocitejKonecGameconu($this->rok)->formatDb();
            case 'REG_GC_OD' :
                return DateTimeGamecon::spocitejZacatekRegistraciUcastniku($this->rok)->formatDb();
            case 'REG_AKTIVIT_OD' :
                return DateTimeGamecon::spoctejZacatekPrvniVlnyOd($this->rok)->formatDb();
            case 'HROMADNE_ODHLASOVANI' :
                return DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniOd($this->rok)->formatDb();
            case 'HROMADNE_ODHLASOVANI_2' :
                return DateTimeGamecon::spocitejDruheHromadneOdhlasovaniOd($this->rok)->formatDb();
            case 'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE' :
                return DateTimeGamecon::spocitejDruheHromadneOdhlasovaniOd($this->rok)->formatDatumDb();
            case 'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE' :
                return DateTimeGamecon::zacatekProgramu($this->rok)->modify('-1 day')->formatDatumDb();
            case 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE' :
                return DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniOd($this->rok)->formatDatumDb();
            default :
                return '';
        }
    }

    public function rok(): int {
        return $this->rok;
    }

    public function ted(): \DateTimeImmutable {
        return $this->ted;
    }

    public function konecLetosnihoGameconu(): \DateTimeImmutable {
        return \DateTimeImmutable::createFromMutable(DateTimeGamecon::konecGameconu($this->rok()));
    }

    public function ucastniciPridatelniDoNeuzavrenePrezenceDo(): \DateTimeImmutable {
        return $this->konecLetosnihoGameconu()
            ->modify($this->ucastnikyLzePridatXDniPoGcDoNeuzavreneAktivity() . ' days');
    }

    public function jsmeNaBete(): bool {
        return $this->jsmeNaBete;
    }

    public function jsmeNaLocale(): bool {
        return $this->jsmeNaLocale;
    }

    public function aktivitaEditovatelnaXMinutPredJejimZacatkem(): int {
        return (int)AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM;
    }

    public function ucastnikyLzePridatXMinutPoUzavreniAktivity(): int {
        return (int)UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY;
    }

    public function ucastnikyLzePridatXDniPoGcDoNeuzavreneAktivity(): int {
        return (int)UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE;
    }

    public function prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity(): int {
        return (int)PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY;
    }

    public function prodejUbytovaniDo(): \DateTimeImmutable {
        return (new \DateTimeImmutable(UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejUbytovaniUkoncen(): bool {
        return $this->prodejUbytovaniDo() < $this->ted();
    }

    public function prodejJidlaDo(): \DateTimeImmutable {
        return (new \DateTimeImmutable(JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejJidlaUkoncen(): bool {
        return $this->prodejJidlaDo() < $this->ted();
    }

    public function prodejTricekDo(): \DateTimeImmutable {
        return (new \DateTimeImmutable(TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejTricekUkoncen(): bool {
        return $this->prodejTricekDo() < $this->ted();
    }

    public function prodejPredmetuDo(): \DateTimeImmutable {
        return (new \DateTimeImmutable(PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejPredmetuBezTricekUkoncen(): bool {
        return $this->prodejPredmetuDo() < $this->ted();
    }
}
