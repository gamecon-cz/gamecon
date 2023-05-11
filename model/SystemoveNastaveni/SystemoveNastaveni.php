<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;
use Gamecon\SystemoveNastaveni\Exceptions\InvalidSystemSettingsValue;

class SystemoveNastaveni
{

    public const ROCNIK = ROCNIK;

    public static function vytvorZGlobals(): self
    {
        return new static(
            ROCNIK,
            new DateTimeImmutableStrict(),
            parse_url(URL_WEBU, PHP_URL_HOST) === 'beta.gamecon.cz',
            parse_url(URL_WEBU, PHP_URL_HOST) === 'localhost',
            DatabazoveNastaveni::vytvorZGlobals()
        );
    }

    /**
     * @param string $klic
     * @param int $bonusZaStandardni3hAz5hAktivitu Nelze použít konstantu při změně v databázi, protože konstanta se změní až při dalším načtení PHP
     * @return int
     */
    public static function spocitejBonusVypravece(
        string $klic,
        int    $bonusZaStandardni3hAz5hAktivitu = BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU,
    ): int
    {
        return match ($klic) {
            'BONUS_ZA_1H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 4),
            'BONUS_ZA_2H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 2),
            'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU' => $bonusZaStandardni3hAz5hAktivitu,
            'BONUS_ZA_6H_AZ_7H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 1.5),
            'BONUS_ZA_8H_AZ_9H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2),
            'BONUS_ZA_10H_AZ_11H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2.5),
            'BONUS_ZA_12H_AZ_13H_AKTIVITU' => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 3),
            default => throw new \LogicException("Neznámý klíč bonusu vypravěče '$klic'"),
        };
    }

    private static function zakrouhli(float $cislo): int
    {
        return (int)round($cislo, 0);
    }

    public function __construct(
        private int                     $rok,
        private DateTimeImmutableStrict $ted,
        private bool                    $jsmeNaBete,
        private bool                    $jsmeNaLocale,
        private DatabazoveNastaveni     $databazoveNastaveni,
    )
    {
        if ($jsmeNaLocale && $jsmeNaBete) {
            throw new \LogicException('Nemůžeme být na betě a zároveň na locale');
        }
    }

    public function zaznamyDoKonstant()
    {
        try {
            $zaznamy = dbFetchAll(<<<SQL
SELECT systemove_nastaveni.klic,
       systemove_nastaveni.hodnota,
       systemove_nastaveni.datovy_typ,
       systemove_nastaveni.aktivni
FROM systemove_nastaveni
SQL,
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
            $hodnota        = $zaznam['aktivni']
                ? $zaznam['hodnota']
                : $this->dejVychoziHodnotu($nazevKonstanty);
            $hodnota        = $this->zkonvertujHodnotuNaTyp($hodnota, $zaznam['datovy_typ']);
            if (!defined($nazevKonstanty)) {
                define($nazevKonstanty, $hodnota);
            } else if (constant($nazevKonstanty) !== $hodnota) {
                /*throw new InvalidSystemSettingsValue(
                    sprintf(
                        "Konstanta '%s' už je definována, ale s jinou hodnotou '%s' než očekávanou '%s'",
                        $nazevKonstanty,
                        var_export(constant($nazevKonstanty), true),
                        var_export($hodnota, true),
                    )
                );*/
            }
        }
        $this->definujOdvozeneKonstanty();
    }

    private function definujOdvozeneKonstanty()
    {
        @define('MODRE_TRICKO_ZDARMA_OD', 3 * BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU); // hodnota slevy od které má subjekt nárok na modré tričko

        @define('BONUS_ZA_1H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_1H_AKTIVITU'));
        @define('BONUS_ZA_2H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_2H_AKTIVITU'));
        @define('BONUS_ZA_6H_AZ_7H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_6H_AZ_7H_AKTIVITU'));
        @define('BONUS_ZA_8H_AZ_9H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_8H_AZ_9H_AKTIVITU'));
        @define('BONUS_ZA_10H_AZ_11H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_10H_AZ_11H_AKTIVITU'));
        @define('BONUS_ZA_12H_AZ_13H_AKTIVITU', self::spocitejBonusVypravece('BONUS_ZA_12H_AZ_13H_AKTIVITU'));
    }

    public function zkonvertujHodnotuNaTyp($hodnota, string $datovyTyp)
    {
        return match (strtolower(trim($datovyTyp))) {
            'boolean', 'bool' => (bool)$hodnota,
            'integer', 'int' => (int)$hodnota,
            'number', 'float' => (float)$hodnota,
            // když to změníš, rozbiješ JS systemove-nastaveni.js
            'date' => (new DateTimeCz($hodnota))->formatDatumDb(),
            // když to změníš, rozbiješ JS systemove-nastaveni.js
            'datetime' => (new DateTimeCz($hodnota))->formatDb(),
            default => (string)$hodnota,
        };
    }

    public function ulozZmenuHodnoty($hodnota, string $klic, \Uzivatel $editujici): int
    {
        $this->hlidejZakazaneZmeny($klic);
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET hodnota = $1
WHERE klic = $2
SQL,
            [$this->formatujHodnotuProDb($hodnota, $klic), $klic],
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, hodnota)
SELECT $1, id_nastaveni, hodnota
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic],
        );
        return dbNumRows($updateQuery);
    }

    private function hlidejZakazaneZmeny(string $klic)
    {
        if ($klic === 'ROCNIK') {
            throw new \LogicException('Ročník nelze měnit jinak než konstantou ROCNIK přes PHP');
        }
    }

    public function ulozZmenuPlatnosti(bool $aktivni, string $klic, \Uzivatel $editujici): int
    {
        $this->hlidejZakazaneZmeny($klic);
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET aktivni = $1
WHERE klic = $2
SQL,
            [$aktivni ? 1 : 0 /* Aby nás nepotkalo "Incorrect integer value: '' for column aktivni" */, $klic],
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, aktivni)
SELECT $1, id_nastaveni, aktivni
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic],
        );
        return dbNumRows($updateQuery);
    }

    private function formatujHodnotuProDb($hodnota, string $klic)
    {
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
                    $invalidDateTimeFormat->getMessage(),
                )
            );
        }
    }

    private function dejDatovyTyp(string $klic): ?string
    {
        static $datoveTypy;
        if ($datoveTypy === null) {
            $datoveTypy = dbArrayCol(<<<SQL
SELECT klic, datovy_typ
FROM systemove_nastaveni
SQL,
            );
        }
        return $datoveTypy[$klic] ?? null;
    }

    public function dejVsechnyZaznamyNastaveni(): array
    {
        return $this->vlozOstatniBonusyVypravecuDoPopisu(
            $this->pridejVychoziHodnoty(
                dbFetchAll($this->dejSqlNaZaVsechnyZaznamyNastaveni()),
            ),
        );
    }

    private function vlozOstatniBonusyVypravecuDoPopisu(array $zaznamy): array
    {
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

    private function dejSqlNaZaVsechnyZaznamyNastaveni(array $whereArray = ['1']): string
    {
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
       systemove_nastaveni.poradi,
       systemove_nastaveni.pouze_pro_cteni
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

    public function dejZaznamyNastaveniPodleKlicu(array $klice): array
    {
        if (!$klice) {
            return [];
        }
        return $this->vlozOstatniBonusyVypravecuDoPopisu(
            $this->pridejVychoziHodnoty(
                dbFetchAll(
                    $this->dejSqlNaZaVsechnyZaznamyNastaveni(['systemove_nastaveni.klic IN ($0)']),
                    [0 => $klice],
                ),
            ),
        );
    }

    private function pridejVychoziHodnoty(array $zaznamy): array
    {
        return array_map(
            function (array $zaznam) {
                $zaznam['vychozi_hodnota'] = $this->dejVychoziHodnotu($zaznam['klic']);
                $zaznam['popis']           .= '<hr><i>výchozí hodnota</i>: ' . (
                    $zaznam['vychozi_hodnota'] !== ''
                        ? htmlspecialchars($zaznam['vychozi_hodnota'], ENT_QUOTES | ENT_HTML5)
                        : '<i>' . htmlspecialchars('>>>není<<<', ENT_QUOTES | ENT_HTML5) . '</i>'
                    );
                return $zaznam;
            },
            $zaznamy,
        );
    }

    public function dejVychoziHodnotu(string $klic)
    {
        return match ($klic) {
            'GC_BEZI_OD' => DateTimeGamecon::spocitejZacatekGameconu($this->rok())
                ->formatDb(),
            'GC_BEZI_DO', 'REG_GC_DO' => DateTimeGamecon::spocitejKonecGameconu($this->rok())
                ->formatDb(),
            'REG_GC_OD' => DateTimeGamecon::spocitejZacatekRegistraciUcastniku($this->rok())
                ->formatDb(),
            'REG_AKTIVIT_OD' => DateTimeGamecon::spoctejZacatekPrvniVlnyOd($this->rok())
                ->formatDb(),
            'HROMADNE_ODHLASOVANI_1' => DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniOd($this->rok())
                ->formatDb(),
            'HROMADNE_ODHLASOVANI_2' => DateTimeGamecon::spocitejDruheHromadneOdhlasovaniOd($this->rok())
                ->formatDb(),
            'HROMADNE_ODHLASOVANI_3' => DateTimeGamecon::spocitejTretiHromadneOdhlasovaniOd($this->rok())
                ->formatDb(),
            'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE' => DateTimeGamecon::spocitejDruheHromadneOdhlasovaniOd($this->rok())
                ->formatDatumDb(),
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE' => DateTimeGamecon::zacatekProgramu($this->rok())
                ->modify('-1 day')
                ->formatDatumDb(),
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE' => DateTimeGamecon::spocitejPrvniHromadneOdhlasovaniOd($this->rok())
                ->formatDatumDb(),
            default => '',
        };
    }

    /**
     * Pozor, mělo by to odpovídat konstantě ROCNIK, respektive hodnotě v SQL tabulce systemove_nastaveni.
     * Pokud je to jinak, tak za následky neručíme (doporučené pouze pro testy).
     */
    public function rok(): int
    {
        return $this->rok;
    }

    public function ted(): DateTimeImmutableStrict
    {
        return $this->ted;
    }

    public function konecLetosnihoGameconu(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable(DateTimeGamecon::konecGameconu($this->rok()));
    }

    public function ucastniciPridatelniDoNeuzavrenePrezenceDo(): \DateTimeImmutable
    {
        return $this->konecLetosnihoGameconu()
            ->modify($this->ucastnikyLzePridatXDniPoGcDoNeuzavreneAktivity() . ' days');
    }

    public function jsmeNaOstre(): bool
    {
        return !$this->jsmeNaBete() && !$this->jsmeNaLocale();
    }

    public function jsmeNaBete(): bool
    {
        return $this->jsmeNaBete;
    }

    public function jsmeNaLocale(): bool
    {
        return $this->jsmeNaLocale;
    }

    public function aktivitaEditovatelnaXMinutPredJejimZacatkem(): int
    {
        return (int)AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM;
    }

    public function ucastnikyLzePridatXMinutPoUzavreniAktivity(): int
    {
        return (int)UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY;
    }

    public function ucastnikyLzePridatXDniPoGcDoNeuzavreneAktivity(): int
    {
        return (int)UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE;
    }

    public function prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity(): int
    {
        return (int)PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY;
    }

    public function prodejUbytovaniDo(): \DateTimeImmutable
    {
        return (new DateTimeImmutableStrict(UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejUbytovaniUkoncen(): bool
    {
        return $this->prodejUbytovaniDo() < $this->ted();
    }

    public function prodejJidlaDo(): \DateTimeImmutable
    {
        return (new DateTimeImmutableStrict(JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejJidlaUkoncen(): bool
    {
        return $this->prodejJidlaDo() < $this->ted();
    }

    public function prodejTricekDo(): \DateTimeImmutable
    {
        return (new DateTimeImmutableStrict(TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejTricekUkoncen(): bool
    {
        return $this->prodejTricekDo() < $this->ted();
    }

    public function prodejPredmetuDo(): \DateTimeImmutable
    {
        return (new DateTimeImmutableStrict(PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejPredmetuBezTricekUkoncen(): bool
    {
        return $this->prodejPredmetuDo() < $this->ted();
    }

    public function prvniHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_1);
    }

    public function druheHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_2);
    }

    public function tretiHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return new DateTimeImmutableStrict(HROMADNE_ODHLASOVANI_3);
    }

    public function zacatekNejblizsiVlnyOdhlasovani(): \DateTimeImmutable
    {
        return DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($this);
    }

    public function databazoveNastaveni(): DatabazoveNastaveni
    {
        return $this->databazoveNastaveni;
    }

    public function jeApril(): bool
    {
        return $this->ted()->format('j. n.') === '1. 4.';
    }

    public function prihlasovaciUdajeOstreDatabaze(): array
    {
        $souborNastaveniOstra = PROJECT_ROOT_DIR . '/../ostra/nastaveni/nastaveni-produkce.php';
        if (!is_readable($souborNastaveniOstra)) {
            throw new \RuntimeException('Nelze přečíst soubor s nastavením ostré ' . $souborNastaveniOstra);
        }
        $obsahNastaveniOstre = file_get_contents($souborNastaveniOstra);
        $nastaveniOstre      = [
            'DBM_USER' => true,
            'DBM_PASS' => true,
            'DB_NAME'  => true,
            'DB_SERV'  => true,
            'DB_PORT'  => false,
        ];
        foreach ($nastaveniOstre as $klic => $vyzadovana) {
            if (!preg_match("~^\s*@?define\s*\(\s*'$klic'\s*,\s*'(?<hodnota>[^']+)'\s*\)~m", $obsahNastaveniOstre, $matches)) {
                if ($vyzadovana) {
                    throw new \RuntimeException("Nelze z $souborNastaveniOstra přečíst hodnotu $klic");
                }
            }
            $nastaveniOstre[$klic] = $matches['hodnota'] ?? null;
        }
        return $nastaveniOstre;
    }
}
