<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Composer\Autoload\ClassLoader;
use Gamecon\Cache\CachedDb;
use Gamecon\Cache\DbInterface;
use Gamecon\Cache\QueryCache;
use Gamecon\Cache\RawDb;
use Gamecon\Cache\TableDataDependentCache;
use Gamecon\Cache\TableDataVersionsRepository;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Cas\Exceptions\ChybnaZpetnaPlatnost;
use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;
use Gamecon\SystemoveNastaveni\Exceptions\ChybnaHodnotaSystemovehoNastaveni;
use Gamecon\SystemoveNastaveni\Exceptions\NeznamyKlicSystemovehoNastaveni;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura as Sql;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice as Klic;
use Gamecon\Uzivatel\Finance;
use App\Kernel;

class SystemoveNastaveni implements ZdrojRocniku, ZdrojVlnAktivit, ZdrojTed
{

    public const JAKYKOLI_ROCNIK = -1;

    public static function zGlobals(
        int                      $rocnik = ROCNIK,
        ?DateTimeImmutableStrict $ted = null,
        ?bool                    $jsmeNaBete = null,
        ?bool                    $jsmeNaLocale = null,
        ?bool                    $databazoveNastaveni = null,
        ?string                  $projectRootDir = null,
        ?string                  $cacheDir = null,
        ?Kernel                  $kernel = null,
    ): self {
        global $systemoveNastaveni;

        if ($systemoveNastaveni && $systemoveNastaveni->rocnik() === $rocnik) {
            $argumenty = array_values(array_filter(func_get_args()));
            if ($argumenty === [] || $argumenty === [$rocnik]) {
                return $systemoveNastaveni;
            }
        }

        $createKernel = function (): Kernel {
            $appEnv = try_constant('APP_ENV') ?? getenv('APP_ENV')
                ?: 'dev';
            $debug  = (bool)(try_constant('APP_DEBUG') ?? getenv('APP_DEBUG')
                ?: $appEnv === 'dev');

            return new Kernel(environment: $appEnv, debug: $debug);
        };

        $noveSystemoveNastaveni = new static(
            $rocnik,
            $ted ?? new DateTimeImmutableStrict(),
            $jsmeNaBete ?? jsmeNaBete(),
            $jsmeNaLocale ?? jsmeNaLocale(),
            $databazoveNastaveni ?? DatabazoveNastaveni::vytvorZGlobals(),
            $projectRootDir
            ?? try_constant('PROJECT_ROOT_DIR')
               ?? dirname((new \ReflectionClass(ClassLoader::class))->getFileName()) . '/../..',
            $cacheDir ?? SPEC,
            $kernel ?? $createKernel(),
        );

        if ($rocnik === ROCNIK) {
            $systemoveNastaveni = $noveSystemoveNastaveni;
        }

        return $noveSystemoveNastaveni;
    }

    private static function zakrouhli(float $cislo): int
    {
        return (int)round($cislo, 0);
    }

    private ?array                       $vsechnyZaznamyNastaveni     = null;
    private ?array                       $odvozeneHodnoty             = null;
    private ?array                       $vychoziHodnoty              = null;
    private ?DbInterface                 $db                          = null;
    private ?QueryCache                  $queryCache                  = null;
    private ?TableDataDependentCache     $tableDataDependentCache     = null;
    private ?TableDataVersionsRepository $tableDataVersionsRepository = null;

    public function __construct(
        private readonly int                     $rocnik,
        private readonly DateTimeImmutableStrict $ted,
        private readonly bool                    $jsmeNaBete,
        private readonly bool                    $jsmeNaLocale,
        private readonly DatabazoveNastaveni     $databazoveNastaveni,
        private readonly string                  $rootAdresarProjektu,
        private readonly string                  $cacheDir,
        private readonly Kernel                  $kernel,
    ) {
        if ($jsmeNaLocale && $jsmeNaBete) {
            throw new \LogicException('Nemůžeme být na betě a zároveň na locale');
        }
        if (!$cacheDir) {
            throw new \LogicException('Cache dir musí být nastaven');
        }
    }

    /**
     * @return array<int, int>
     */
    public function bonusyZaVedeniAktivity(): array
    {
        static $bonusyZaVedeniAktivity = null;
        if ($bonusyZaVedeniAktivity === null) {
            $casAKlic = [
                1  => 'BONUS_ZA_1H_AKTIVITU',
                2  => 'BONUS_ZA_2H_AKTIVITU',
                5  => 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU',
                7  => 'BONUS_ZA_6H_AZ_7H_AKTIVITU',
                9  => 'BONUS_ZA_8H_AZ_9H_AKTIVITU',
                11 => 'BONUS_ZA_10H_AZ_11H_AKTIVITU',
                13 => 'BONUS_ZA_12H_AZ_13H_AKTIVITU',
            ];
            foreach ($casAKlic as $hodin => $klic) {
                // ve formátu max. délka => sleva
                $bonusyZaVedeniAktivity[$hodin] = $this->spocitejBonusZaVedeniAktivity($klic);
            }
        }

        return $bonusyZaVedeniAktivity;
    }

    /**
     * @param string $klic
     * @param ?int $bonusZaStandardni3hAz5hAktivitu Nelze použít konstantu při změně v databázi, protože konstanta se změní až při dalším načtení PHP
     * @return int
     */
    public function spocitejBonusZaVedeniAktivity(
        string $klic,
        ?int   $bonusZaStandardni3hAz5hAktivitu = null,
    ): int {
        $bonusZaStandardni3hAz5hAktivitu ??= $this->dejHodnotuZeZaznamuNastaveni(SystemoveNastaveniKlice::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU);

        return match ($klic) {
            'BONUS_ZA_1H_AKTIVITU'                  => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 4),
            'BONUS_ZA_2H_AKTIVITU'                  => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu / 2),
            'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU' => $bonusZaStandardni3hAz5hAktivitu,
            'BONUS_ZA_6H_AZ_7H_AKTIVITU'            => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 1.5),
            'BONUS_ZA_8H_AZ_9H_AKTIVITU'            => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2),
            'BONUS_ZA_10H_AZ_11H_AKTIVITU'          => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 2.5),
            'BONUS_ZA_12H_AZ_13H_AKTIVITU'          => self::zakrouhli($bonusZaStandardni3hAz5hAktivitu * 3),
            default                                 => throw new \LogicException("Neznámý klíč bonusu vypravěče '$klic'"),
        };
    }

    public function zaznamyDoKonstant()
    {
        try {
            $jakykoliRocnik = self::JAKYKOLI_ROCNIK;
            $soucasnyRocnik = $this->rocnik();
            $zaznamy        = dbFetchAll(<<<SQL
SELECT klic, hodnota, datovy_typ, vlastni
FROM systemove_nastaveni
WHERE rocnik_nastaveni IN ($jakykoliRocnik, $soucasnyRocnik)
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
                if ((new SqlMigrace($this))->nejakeMigraceKeSpusteni()) {
                    return; // tabulka či sloupec musí vzniknout SQL migrací
                }
                // else například jsme si na lokál stáhli příliš novou databázi
            }
            throw $dbException;
        }
        foreach ($zaznamy as $zaznam) {
            $nazevKonstanty = trim(strtoupper($zaznam[SystemoveNastaveniStruktura::KLIC]));
            $hodnota        = $zaznam[SystemoveNastaveniStruktura::VLASTNI]
                ? $zaznam[SystemoveNastaveniStruktura::HODNOTA]
                : $this->dejVychoziHodnotu($nazevKonstanty);
            $hodnota        = $this->zkonvertujHodnotuNaTyp($hodnota, $zaznam[SystemoveNastaveniStruktura::DATOVY_TYP]);
            if (!defined($nazevKonstanty)) {
                define($nazevKonstanty, $hodnota);
            } elseif (constant($nazevKonstanty) !== $hodnota && $this->jsmeNaOstre()) {
                throw new ChybnaHodnotaSystemovehoNastaveni(
                    sprintf(
                        "Konstanta '%s' už je definována, ale s jinou hodnotou '%s' než očekávanou '%s'",
                        $nazevKonstanty,
                        var_export(constant($nazevKonstanty), true),
                        var_export($hodnota, true),
                    ),
                );
            }
        }
        $this->definujOdvozeneKonstanty();
    }

    private function definujOdvozeneKonstanty()
    {
        foreach ($this->dejOdvozeneHodnoty() as $klic => $hodnota) {
            try_define($klic, $hodnota);
        }
    }

    private function dejOdvozeneHodnoty(): array
    {
        if ($this->odvozeneHodnoty === null) {
            $this->odvozeneHodnoty = [
                // hodnota slevy od které má subjekt nárok na modré tričko
                Klic::MODRE_TRICKO_ZDARMA_OD       => defined('MODRE_TRICKO_ZDARMA_OD')
                    ? MODRE_TRICKO_ZDARMA_OD
                    : 3 * $this->dejHodnotu(Klic::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU, false /* radši, abychom neskončili v nekonečné smyčce */),
                Klic::BONUS_ZA_1H_AKTIVITU         => defined('BONUS_ZA_1H_AKTIVITU')
                    ? BONUS_ZA_1H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_1H_AKTIVITU),
                Klic::BONUS_ZA_2H_AKTIVITU         => defined('BONUS_ZA_2H_AKTIVITU')
                    ? BONUS_ZA_2H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_2H_AKTIVITU),
                Klic::BONUS_ZA_6H_AZ_7H_AKTIVITU   => defined('BONUS_ZA_6H_AZ_7H_AKTIVITU')
                    ? BONUS_ZA_6H_AZ_7H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_6H_AZ_7H_AKTIVITU),
                Klic::BONUS_ZA_8H_AZ_9H_AKTIVITU   => defined('BONUS_ZA_8H_AZ_9H_AKTIVITU')
                    ? BONUS_ZA_8H_AZ_9H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_8H_AZ_9H_AKTIVITU),
                Klic::BONUS_ZA_10H_AZ_11H_AKTIVITU => defined('BONUS_ZA_10H_AZ_11H_AKTIVITU')
                    ? BONUS_ZA_10H_AZ_11H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_10H_AZ_11H_AKTIVITU),
                Klic::BONUS_ZA_12H_AZ_13H_AKTIVITU => defined('BONUS_ZA_12H_AZ_13H_AKTIVITU')
                    ? BONUS_ZA_12H_AZ_13H_AKTIVITU
                    : $this->spocitejBonusZaVedeniAktivity(Klic::BONUS_ZA_12H_AZ_13H_AKTIVITU),
                Klic::PRISTI_VLNA_KDY              => defined('PRISTI_VLNA_KDY')
                    ? PRISTI_VLNA_KDY
                    : self::pristiVlnaKdy()?->formatDb(),
            ];
        }

        return $this->odvozeneHodnoty;
    }

    public function zkonvertujHodnotuNaTyp(
        $hodnota,
        string $datovyTyp,
    ): bool | int | float | string {
        return match (strtolower(trim($datovyTyp))) {
            'boolean', 'bool' => (bool)$hodnota,
            'integer', 'int'  => (int)$hodnota,
            'number', 'float' => (float)$hodnota,
            // když to změníš, rozbiješ JS systemove-nastaveni.js
            'date'            => $this->vytvorDateTime($hodnota)->formatDatumDb(),
            // když to změníš, rozbiješ JS systemove-nastaveni.js
            'datetime'        => $this->vytvorDateTime($hodnota)->formatDb(),
            default           => (string)$hodnota,
        };
    }

    private function vytvorDateTime(string $hodnota): DateTimeCz
    {
        return new DateTimeCz(preg_replace('~\s~', '', $hodnota));
    }

    public function ulozZmenuHodnoty(
        string    $hodnota,
        string    $klic,
        \Uzivatel $editujici,
    ): int {
        $this->hlidejZakazaneZmeny($klic, $hodnota);
        $hodnotaProDb   = $this->formatujHodnotuProDb($hodnota, $klic);
        $updateQuery    = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET hodnota = $1
WHERE klic = $2
SQL,
            [$hodnotaProDb, $klic],
        );
        $zmenenoZaznamu = dbAffectedOrNumRows($updateQuery);
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, hodnota)
SELECT $1, id_nastaveni, hodnota
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic],
        );
        if ($zmenenoZaznamu > 0) {
            $this->aktualizujZaznamVLokalniCache($hodnotaProDb, $klic, $editujici->id());
        }

        return $zmenenoZaznamu;
    }

    private function aktualizujZaznamVLokalniCache(
        $novaHodnota,
        string $klic,
        int $idEditujicihoUzivatele,
    ) {
        if ($this->vsechnyZaznamyNastaveni === null) {
            return;
        }

        $puvodniHodnota = $this->dejHodnotu($klic, false);
        if ($puvodniHodnota === $novaHodnota) {
            return;
        }

        $puvodniZaznam        = $this->vsechnyZaznamyNastaveni[$klic];
        $zkonvertovanaHodnota = $this->zkonvertujHodnotuNaTyp($novaHodnota, $puvodniZaznam[Sql::DATOVY_TYP]);

        $this->vsechnyZaznamyNastaveni[$klic][Sql::HODNOTA]   = $zkonvertovanaHodnota;
        $this->vsechnyZaznamyNastaveni[$klic]['id_uzivatele'] = (string)$idEditujicihoUzivatele;
    }

    private function aktualizujPriznakVlastniVLokalniCache(
        bool   $vlastni,
        string $klic,
        int    $idEditujicihoUzivatele,
    ) {
        if ($this->vsechnyZaznamyNastaveni === null) {
            return;
        }

        $this->vsechnyZaznamyNastaveni[$klic][Sql::VLASTNI]   = (string)(int)$vlastni;
        $this->vsechnyZaznamyNastaveni[$klic]['id_uzivatele'] = (string)$idEditujicihoUzivatele;
    }

    private function hlidejZakazaneZmeny(
        string $klic,
               $hodnota,
    ) {
        if ($klic === Klic::ROCNIK) {
            throw new ChybnaHodnotaSystemovehoNastaveni('Ročník nelze měnit jinak než konstantou ROCNIK přes PHP');
        } elseif ($klic === Klic::REG_GC_DO && $hodnota) {
            try {
                $regGcDo = $this->vytvorDateTimeZCeskehoFormatu($hodnota);
            } catch (InvalidDateTimeFormat) {
                throw new ChybnaHodnotaSystemovehoNastaveni("Neplatné datum a čas '$hodnota'");
            }
            $konecLetosnihoGameconu = $this->spocitanyKonecLetosnihoGameconu();
            if ($regGcDo->getTimestamp() > $konecLetosnihoGameconu->getTimestamp()) {
                throw new ChybnaHodnotaSystemovehoNastaveni(
                    "Konec registrace účastníků musí být nejpozději ke konci GC{$this->rocnik()}: '{$konecLetosnihoGameconu->formatCasStandard()}'",
                );
            }
        }
    }

    public function ulozZmenuPriznakuVlastni(
        bool      $vlastni,
        string    $klic,
        \Uzivatel $editujici,
    ): int {
        $this->hlidejZakazaneZmeny($klic, null);
        $updateQuery = dbQuery(<<<SQL
UPDATE systemove_nastaveni
SET vlastni = $1
WHERE klic = $2
SQL,
            [$vlastni
                 ? 1
                 : 0 /* Aby nás nepotkalo "Incorrect integer value: '' for column vlastni" */, $klic],
        );
        dbQuery(<<<SQL
INSERT INTO systemove_nastaveni_log(id_uzivatele, id_nastaveni, vlastni)
SELECT $1, id_nastaveni, vlastni
FROM systemove_nastaveni
WHERE klic = $2
SQL,
            [$editujici->id(), $klic],
        );
        $zmenenoZaznamu = dbAffectedOrNumRows($updateQuery);
        if ($zmenenoZaznamu > 0) {
            $this->aktualizujPriznakVlastniVLokalniCache($vlastni, $klic, $editujici->id());
        }

        return $zmenenoZaznamu;
    }

    private function formatujHodnotuProDb(
        string $hodnota,
        string $klic,
    ) {
        try {
            return match ($this->dejDatovyTyp($klic)) {
                'date'            => $hodnota
                    ? $this->vytvorDateZCeskehoFormatu($this->sanitizujDatumZHtml($hodnota))->formatDatumDb()
                    : $hodnota,
                'datetime'        => $hodnota
                    ? $this->vytvorDateTimeZCeskehoFormatu($this->sanitizujDatumZHtml($hodnota))->formatDb()
                    : $hodnota,
                'bool', 'boolean' => (int)filter_var($hodnota, FILTER_VALIDATE_BOOLEAN),
                default           => $hodnota,
            };
        } catch (InvalidDateTimeFormat $invalidDateTimeFormat) {
            throw new ChybnaHodnotaSystemovehoNastaveni(
                sprintf(
                    "Chybný formát času - nepodařilo se čas %s (%s) převést do formátu databáze: %s",
                    var_export($hodnota, true),
                    var_export($klic, true),
                    $invalidDateTimeFormat->getMessage(),
                ),
            );
        }
    }

    private function sanitizujDatumZHtml(string $hodnota): string
    {
        return DateTimeCz::trimovatZHtml($hodnota);
    }

    private function vytvorDateZCeskehoFormatu(string $hodnota): DateTimeCz
    {
        return DateTimeCz::createFromFormat('j. n. Y', $hodnota);
    }

    private function vytvorDateTimeZCeskehoFormatu(string $hodnota): DateTimeCz
    {
        return DateTimeCz::createFromFormat('j. n. Y H:i:s', $hodnota);
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

    public function dejVsechnyZaznamyNastaveni(bool $prenacti = false): array
    {
        if ($prenacti) {
            $this->vsechnyZaznamyNastaveni = null;
        }
        if ($this->vsechnyZaznamyNastaveni === null) {
            $this->vsechnyZaznamyNastaveni = $this->nactiZaznamyNastaveni();
        }

        return $this->vsechnyZaznamyNastaveni;
    }

    private function vlozOstatniBonusyVypravecuDoPopisu(array $zaznamy): array
    {
        foreach ($zaznamy as &$zaznam) {
            if ($zaznam['klic'] !== Klic::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU) {
                continue;
            }
            $bonusZaStandardni3hAz5hAktivitu = (int)$zaznam['hodnota'];
            $popis                           = &$zaznam['popis'];
            $popis                           .= '<hr><i>vypočtené bonusy</i>:<br>'
                                                . 'BONUS_ZA_1H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_1H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                                                . 'BONUS_ZA_2H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_2H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                                                . '•••<br>'
                                                . 'BONUS_ZA_6H_AZ_7H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_6H_AZ_7H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                                                . 'BONUS_ZA_8H_AZ_9H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_8H_AZ_9H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                                                . 'BONUS_ZA_10H_AZ_11H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_10H_AZ_11H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>'
                                                . 'BONUS_ZA_12H_AZ_13H_AKTIVITU = ' . $this->spocitejBonusZaVedeniAktivity('BONUS_ZA_12H_AZ_13H_AKTIVITU', $bonusZaStandardni3hAz5hAktivitu) . '<br>';
        }

        return $zaznamy;
    }

    private function dejSqlNaZaznamyNastaveni(array $whereArray = ['1']): string
    {
        $where          = implode(' AND ', $whereArray);
        $jakykoliRocnik = self::JAKYKOLI_ROCNIK;

        return <<<SQL
SELECT systemove_nastaveni.klic,
       systemove_nastaveni.hodnota,
       systemove_nastaveni.datovy_typ,
       systemove_nastaveni.vlastni,
       systemove_nastaveni.nazev,
       systemove_nastaveni.popis,
       COALESCE(naposledy, systemove_nastaveni.zmena_kdy) AS zmena_kdy,
       posledni_s_uzivatelem.id_uzivatele,
       systemove_nastaveni.skupina,
       systemove_nastaveni.poradi,
       systemove_nastaveni.pouze_pro_cteni,
       systemove_nastaveni.rocnik_nastaveni
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
    AND rocnik_nastaveni IN ({$jakykoliRocnik}, {$this->rocnik()})
ORDER BY systemove_nastaveni.poradi
SQL;
    }

    public function dejZaznamyNastaveniPodleKlicu(
        array $klice,
        bool  $prenacti = false,
    ): array {
        if (!$klice) {
            return [];
        }

        return array_intersect_key(
            $this->dejVsechnyZaznamyNastaveni($prenacti),
            array_fill_keys($klice, ''),
        );
    }

    private function nactiZaznamyNastaveni(
        array $whereArray = ['1'],
        array $parametryDotazu = [],
    ): array {
        $zaznamyNastaveni = $this->vlozOstatniBonusyVypravecuDoPopisu(
            $this->pridejVychoziHodnoty(
                dbFetchAll(
                    $this->dejSqlNaZaznamyNastaveni($whereArray),
                    $parametryDotazu,
                ),
            ),
        );

        $zaznamyNastaveniPodleKlicu = [];
        foreach ($zaznamyNastaveni as $zaznam) {
            $zaznamyNastaveniPodleKlicu[$zaznam[Sql::KLIC]] = $zaznam;
        }

        return $zaznamyNastaveniPodleKlicu;
    }

    private function pridejVychoziHodnoty(array $zaznamy): array
    {
        return array_map(
            function (
                array $zaznam,
            ) {
                $zaznam['vychozi_hodnota'] = $this->dejVychoziHodnotu($zaznam[Sql::KLIC]);
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

    public function dejHodnotu(
        string $klic,
        bool   $hledatTakeVOdvozenych = true,
    ) {
        if (defined($klic)) {
            /**
             * POZOR tohle může prozradit i heslo do databáze a další. Pro veřejné honoty použij @see dejVerejnouHodnotu
             */
            return constant($klic);
        }

        return $this->dejVerejnouHodnotu($klic, $hledatTakeVOdvozenych);
    }

    public function dejVerejnouHodnotu(
        string $klic,
        bool   $hledatTakeVOdvozenych = true,
    ) {
        $hodnota = $this->dejHodnotuZeZaznamuNastaveni($klic);
        if ($hodnota !== null) {
            if (defined($klic)) {
                // POZOR, nejdříve si musíme potvrdit, že hodnota pochází z nastavení, teprve potom můžeme použít hodnotu z konstanty - jinak bychom taky mohli prozradit heslo do databáze a podobně
                return constant($klic);
            }

            return $hodnota;
        }

        if ($hledatTakeVOdvozenych) {
            $odvozeneHodnoty = $this->dejOdvozeneHodnoty();
            if (array_key_exists($klic, $odvozeneHodnoty)) {
                return $odvozeneHodnoty[$klic];
            }
        }

        throw new NeznamyKlicSystemovehoNastaveni("Klíč '$klic' nemáme v záznamech nastavení");
    }

    /**
     * Pozor, toto vrací hodnotu z tabulky systemove_nastaveni, ale to nemusí být finální hodnota!
     * Finální hodnotu určuje přiznak "vlastní" a pokud není zvolený, tak se použije výchozí hodnota,
     * @see zaznamyDoKonstant
     */
    private function dejHodnotuZeZaznamuNastaveni(string $klic)
    {
        $vsechnyZaznamy = $this->dejVsechnyZaznamyNastaveni();
        if (!array_key_exists($klic, $vsechnyZaznamy)) {
            return null;
        }

        return $this->zkonvertujHodnotuNaTyp(
            $vsechnyZaznamy[$klic][Sql::HODNOTA],
            $vsechnyZaznamy[$klic][Sql::DATOVY_TYP],
        );
    }

    public function dejVychoziHodnotu(string $klic): string
    {
        return $this->dejVychoziHodnoty()[$klic] ?? '';
    }

    /**
     * @return array<string>
     */
    public function dejVychoziHodnoty(): array
    {
        if ($this->vychoziHodnoty === null) {
            $tretiHromadneOdhlasovaniKdy = DateTimeGamecon::spocitejTretiHromadneOdhlasovani($this->rocnik())
                                                          ->modify('-1 day') // například 17. 7. 2023 00:00 -> 16. 7. 2023 myšleno včetně
                                                          ->formatDatumDb();
            $konecGameconuKdy            = DateTimeGamecon::spocitejKonecGameconu($this->rocnik())->formatDb();

            $this->vychoziHodnoty = [
                Klic::GC_BEZI_OD                                      => DateTimeGamecon::spocitejZacatekGameconu($this->rocnik())->formatDb(),
                Klic::GC_BEZI_DO                                      => $konecGameconuKdy,
                Klic::REG_GC_OD                                       => DateTimeGamecon::spocitejPrihlasovaniUcastnikuOd($this->rocnik())->formatDb(),
                Klic::REG_GC_DO                                       => $konecGameconuKdy,
                Klic::PRVNI_VLNA_KDY                                  => DateTimeGamecon::spoctejKdyJePrvniVlna($this->rocnik())
                                                                                        ->formatDb(),
                Klic::DRUHA_VLNA_KDY                                  => DateTimeGamecon::spocitejKdyJeDruhaVlna($this->rocnik())
                                                                                        ->formatDb(),
                Klic::TRETI_VLNA_KDY                                  => DateTimeGamecon::spocitejKdyJeTretiVlna($this->rocnik())
                                                                                        ->formatDb(),
                Klic::UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE           => $tretiHromadneOdhlasovaniKdy,
                Klic::JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE               => $tretiHromadneOdhlasovaniKdy,
                Klic::PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE => DateTimeGamecon::spocitejDruheHromadneOdhlasovani($this->rocnik())
                                                                                        ->modify('-1 day') // například 10. 7. 2023 00:00 -> 9. 7. 2023 myšleno včetně
                                                                                        ->formatDatumDb(),
                Klic::TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE              => $this->rocnik() === 2023
                    ? '2023-06-23'
                    : DateTimeGamecon::spocitejPrvniHromadneOdhlasovani($this->rocnik())
                                     ->formatDatumDb(),
                Klic::TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY               => 'vraceni zustatku GC ID:',
                Klic::HROMADNE_ODHLASOVANI_1                          => DateTimeGamecon::spocitejPrvniHromadneOdhlasovani($this->rocnik())
                                                                                        ->formatDb(),
                Klic::HROMADNE_ODHLASOVANI_2                          => DateTimeGamecon::spocitejDruheHromadneOdhlasovani($this->rocnik())
                                                                                        ->formatDb(),
                Klic::HROMADNE_ODHLASOVANI_3                          => DateTimeGamecon::spocitejTretiHromadneOdhlasovani($this->rocnik())
                                                                                        ->formatDb(),
            ];
        }

        return $this->vychoziHodnoty;
    }

    public function spocitejHodnotu(string $klic): string
    {
        return match ($klic) {
            Klic::PRUMERNE_LONSKE_VSTUPNE => (string)Finance::prumerneVstupneRoku($this->rocnik() - 1),
            default                       => $this->dejVychoziHodnotu($klic),
        };
    }

    /**
     * Pozor, mělo by to odpovídat konstantě ROCNIK, respektive hodnotě v SQL tabulce systemove_nastaveni.
     * Pokud je to jinak, tak za následky neručíme (doporučené pouze pro testy).
     */
    public function rocnik(): int
    {
        return $this->rocnik;
    }

    public function ted(): DateTimeImmutableStrict
    {
        return $this->ted;
    }

    public function spocitanyZacatekLetosnihoGameconu(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(DateTimeGamecon::zacatekGameconu($this->rocnik()));
    }

    public function spocitanyKonecLetosnihoGameconu(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(DateTimeGamecon::konecGameconu($this->rocnik()));
    }

    public function ucastniciPridatelniDoNeuzavrenePrezenceDo(): DateTimeImmutableStrict
    {
        return $this->spocitanyKonecLetosnihoGameconu()
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

    public function prodejUbytovaniDo(): DateTimeImmutableStrict
    {
        return (new DateTimeImmutableStrict(UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejUbytovaniUkoncen(): bool
    {
        return $this->prodejUbytovaniDo() < $this->ted();
    }

    public function prodejJidlaDo(): DateTimeImmutableStrict
    {
        return (new DateTimeImmutableStrict(JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejJidlaUkoncen(): bool
    {
        return $this->prodejJidlaDo() < $this->ted();
    }

    public function prodejTricekDo(): DateTimeImmutableStrict
    {
        return (new DateTimeImmutableStrict(TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejTricekUkoncen(): bool
    {
        return $this->prodejTricekDo() < $this->ted();
    }

    public function prodejPredmetuBezTricekDo(): DateTimeImmutableStrict
    {
        return (new DateTimeImmutableStrict(PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE))
            ->setTime(23, 59, 59);
    }

    public function prodejPredmetuBezTricekUkoncen(): bool
    {
        return $this->prodejPredmetuBezTricekDo() < $this->ted();
    }

    public function prvniHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(
            DateTimeGamecon::prvniHromadneOdhlasovani($this->rocnik()),
        );
    }

    public function druheHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(
            DateTimeGamecon::druheHromadneOdhlasovani($this->rocnik()),
        );
    }

    public function tretiHromadneOdhlasovani(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(
            DateTimeGamecon::tretiHromadneOdhlasovani($this->rocnik()),
        );
    }

    /**
     * @throws ChybnaZpetnaPlatnost
     */
    public function nejblizsiHromadneOdhlasovaniKdy(\DateTimeInterface $platnostZpetneKDatu = null,
    ): DateTimeImmutableStrict {
        return DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($this, $platnostZpetneKDatu);
    }

    public function nejpozdejiZaplatitDo(\DateTimeInterface $platnostZpetneKDatu = null): DateTimeImmutableStrict
    {
        $hromadneOdhlasovaniKdy = $this->nejblizsiHromadneOdhlasovaniKdy($platnostZpetneKDatu);

        return $hromadneOdhlasovaniKdy;
    }

    public function pristiVlnaKdy(): ?DateTimeGamecon
    {
        $nejblizsiVlnaKdy = $this->nejblizsiVlnaKdy($this->ted());

        return $nejblizsiVlnaKdy >= $this->ted()
            ? $nejblizsiVlnaKdy
            : null;
    }

    public function nejblizsiVlnaKdy(
        \DateTimeInterface $platnostZpetneKDatu = null,
        bool               $overovatDatumZpetne = true,
    ): DateTimeGamecon {
        return DateTimeGamecon::nejblizsiVlnaKdy($this, $platnostZpetneKDatu, $overovatDatumZpetne);
    }

    public function databazoveNastaveni(): DatabazoveNastaveni
    {
        return $this->databazoveNastaveni;
    }

    public function prvniVlnaKdy(): DateTimeGamecon
    {
        return DateTimeGamecon::prvniVlnaKdy($this->rocnik());
    }

    public function druhaVlnaKdy(): DateTimeGamecon
    {
        return DateTimeGamecon::druhaVlnaKdy($this->rocnik());
    }

    public function tretiVlnaKdy(): DateTimeGamecon
    {
        return DateTimeGamecon::tretiVlnaKdy($this->rocnik());
    }

    public function gcBeziOd(): DateTimeGamecon
    {
        return DateTimeGamecon::createFromMysql(defined('GC_BEZI_OD')
            ? GC_BEZI_OD
            : $this->dejVychoziHodnotu('GC_BEZI_OD'),
        );
    }

    public function gcBeziDo(): DateTimeGamecon
    {
        return DateTimeGamecon::createFromMysql(defined('GC_BEZI_DO')
            ? GC_BEZI_DO
            : $this->dejVychoziHodnotu('GC_BEZI_DO'),
        );
    }

    public function gcBezi(): bool
    {
        return mezi($this->gcBeziOd(), $this->gcBeziDo());
    }

    public function prihlasovaniUcastnikuOd(): DateTimeGamecon
    {
        return DateTimeGamecon::prihlasovaniUcastnikuOd($this->rocnik());
    }

    public function prihlasovaniUcastnikuDo(): DateTimeGamecon
    {
        return DateTimeGamecon::prihlasovaniUcastnikuDo($this->rocnik());
    }

    public function prihlasovaniUcastnikuSpusteno(): bool
    {
        return mezi($this->prihlasovaniUcastnikuOd(), $this->prihlasovaniUcastnikuDo());
    }

    public function predPrihlasovanimUcastniku(): bool
    {
        return pred($this->prihlasovaniUcastnikuOd());
    }

    public function poPrihlasovaniUcastniku(): bool
    {
        return po($this->prihlasovaniUcastnikuDo());
    }

    public function slevaOrguNaJidloCastka(): float
    {
        return defined('SLEVA_ORGU_NA_JIDLO_CASTKA')
            ? SLEVA_ORGU_NA_JIDLO_CASTKA
            : $this->dejHodnotu('SLEVA_ORGU_NA_JIDLO_CASTKA');
    }

    public function neplaticCastkaVelkyDluh(): float
    {
        return defined('NEPLATIC_CASTKA_VELKY_DLUH')
            ? NEPLATIC_CASTKA_VELKY_DLUH
            : $this->dejHodnotu('NEPLATIC_CASTKA_VELKY_DLUH');
    }

    public function neplaticCastkaPoslalDost(): float
    {
        return defined('NEPLATIC_CASTKA_POSLAL_DOST')
            ? NEPLATIC_CASTKA_POSLAL_DOST
            : $this->dejHodnotu('NEPLATIC_CASTKA_POSLAL_DOST');
    }

    public function neplaticPocetDnuPredVlnouKdyJeChranen(): int
    {
        return defined('NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN')
            ? NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN
            : $this->dejHodnotu('NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN');
    }

    public function probihaRegistraceAktivit(): bool
    {
        return mezi($this->prvniVlnaKdy(), $this->gcBeziDo());
    }

    public function jeApril(): bool
    {
        return $this->ted()->format('j. n.') === '1. 4.';
    }

    public function nacitatPlatbyXDniZpet(): int
    {
        return 90; // kolik dní zpět se mají načítat platby při kontrole nově došlých plateb
    }

    public function modreTrickoZdarmaOd(): float
    {
        return (float)$this->dejHodnotu(Klic::MODRE_TRICKO_ZDARMA_OD);
    }

    public function prumerneLonskeVstupne(): float
    {
        return (float)$this->dejHodnotu(Klic::PRUMERNE_LONSKE_VSTUPNE);
    }

    public function rootAdresarProjektu(): string
    {
        return $this->rootAdresarProjektu;
    }

    public function cacheDir(): string
    {
        return $this->cacheDir;
    }

    public function databaseDataDependentCacheDir(): string
    {
        return $this->cacheDir . '/' . DB_NAME;
    }

    public function tableDataDependentCacheDir(): string
    {
        return $this->databaseDataDependentCacheDir() . '/table_data_dependent';
    }

    public function prihlasovaciUdajeOstreDatabaze(): array
    {
        $souborNastaveniOstra = $this->rootAdresarProjektu . '/../ostra/nastaveni/nastaveni-produkce.php';
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

    public function poslatMailZeBylOdhlasenAMelUbytovani(): bool
    {
        return (bool)(defined('POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI')
            ? POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI
            : $this->dejHodnotu(Klic::POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI));
    }

    public function prihlasovaciUdajeSoucasneDatabaze(): array
    {
        $dbPort = try_constant('DB_PORT')
            ?: null;
        if ($dbPort !== null) {
            $dbPort = (int)$dbPort;
        }

        return [
            'DBM_USER' => try_constant('DBM_USER'),
            'DBM_PASS' => try_constant('DBM_PASS'),
            'DB_USER'  => try_constant('DB_USER'),
            'DB_PASS'  => try_constant('DB_PASS'),
            'DB_NAME'  => try_constant('DB_NAME'),
            'DB_SERV'  => try_constant('DB_SERV'),
            'DB_PORT'  => $dbPort,
        ];
    }

    public function kontaktniEmailGc(): string
    {
        return 'gamecon.fallback@seznam.cz';
    }

    public function prefixPodleProstredi(): string
    {
        if ($this->jsmeNaOstre()) {
            return '';
        }
        if ($this->jsmeNaBete()) {
            return 'β';
        }
        if ($this->jsmeNaLocale()) {
            return 'άλφα';
        }

        return 'δ'; // gamu přeskočíme, je nevýrazná
    }

    public function kolikMinutJeOdhlaseniBezPokuty(): int
    {
        return defined('KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY')
            ? (int)KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY
            : (int)$this->dejHodnotu(Klic::KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY);
    }

    public function kontrolovatPokutuZaOdhlaseni(): bool
    {
        return (bool)ODHLASENI_POKUTA_KONTROLA;
    }

    public function kolikHodinPredAktivitouUzJePokutaZaOdhlaseni(): int
    {
        return (int)ODHLASENI_POKUTA1_H;
    }

    public function jeOmezeniUbytovaniPouzeNaSpacaky(): bool
    {
        return (bool)UBYTOVANI_POUZE_SPACAKY;
    }

    public function jeZapnuteCachovaniSqlDotazu(): bool
    {
        // may not yet be defined during SQL migrations
        return (bool)try_constant('CACHOVAT_SQL_DOTAZY');
    }

    public function jeZapnuteCachovaniApiOdpovedi(): bool
    {
        return (bool)CACHOVAT_API_ODPOVEDI;
    }

    public function db(): DbInterface
    {
        if (!$this->db) {
            $this->db = $this->jeZapnuteCachovaniSqlDotazu()
                ? new CachedDb($this->queryCache())
                : new RawDb();
        }

        return $this->db;
    }

    public function queryCache(): QueryCache
    {
        if (!$this->queryCache) {
            $this->queryCache = new QueryCache($this->databaseDataDependentCacheDir());
        }

        return $this->queryCache;
    }

    public function tableDataDependentCache(): TableDataDependentCache
    {
        if (!$this->tableDataDependentCache) {
            $this->tableDataDependentCache = new TableDataDependentCache(
                $this->tableDataDependentCacheDir(),
                $this->tableDataVersionsRepository(),
            );
        }

        return $this->tableDataDependentCache;
    }

    public function tableDataVersionsRepository(): TableDataVersionsRepository
    {
        if (!$this->tableDataVersionsRepository) {
            $this->tableDataVersionsRepository = new TableDataVersionsRepository();
        }

        return $this->tableDataVersionsRepository;
    }

    public function kernel(): Kernel
    {
        try {
            $this->kernel->boot();
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Can not boot Symfony kernel. Current APP_ENV is %s. Details: %s',
                    $this->kernel->getEnvironment(),
                    $exception->getMessage(),
                ),
            );
        }

        return $this->kernel;
    }
}
