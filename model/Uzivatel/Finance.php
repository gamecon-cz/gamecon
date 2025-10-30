<?php

namespace Gamecon\Uzivatel;

use Endroid\QrCode\Writer\Result\ResultInterface;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSpecSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniStavySqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use Gamecon\Finance\QrPlatba;
use Gamecon\Finance\SqlStruktura\SlevySqlStruktura;
use Gamecon\Objekt\ObnoveniVychozichHodnotTrait;
use Gamecon\Pravo;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as PredmetSql;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Dto\PriceAfterDiscountDto;
use Gamecon\Uzivatel\SqlStruktura\PlatbySqlStruktura;

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */
class Finance
{
    use ObnoveniVychozichHodnotTrait;

    public const KLIC_ZRUS_NAKUP_POLOZKY = 'zrus-nakup-polozky';

    private const MAX_SLEVA_AKTIVIT_PROCENT = 100;
    private const PLNA_SLEVA_PROCENT        = 100;
    private const CASTECNA_SLEVA_PROCENT    = 40;

    private ?float $stav                  = null;  // celkový výsledný stav uživatele na účtu
    private ?float $soucinitelCenyAKtivit = null;              // součinitel ceny aktivit
    private ?Cenik $cenik                 = null;             // instance ceníku
    // tabulky s přehledy
    private ?array $prehled                        = null;   // tabulka s detaily o platbách
    private ?array $strukturovanyPrehled           = null;
    private ?array $polozkyProBfgr                 = null;
    private ?array $slevyNaAktivity                = null;    // pole s textovými popisy slev uživatele na aktivity
    private ?float $proplacenyBonusZaVedeniAktivit = null; // "sleva" za aktivity; nebo-li bonus vypravěče; nebo-li odměna za vedení hry; převedená na peníze
    private ?float $brigadnickaOdmena              = null;  // výplata zaměstnance (který nechce bonus/kredit na útratu; ale tvrdou měnu za tvrdou práci)
    // součásti výsledné ceny
    private ?float                $cenaAktivit                   = null;  // cena aktivit
    private ?float                $sumaStorna                    = null;  // suma storna za aktivity (je součástí ceny za aktivity)
    private ?float                $cenaUbytovani                 = null;  // cena objednaného ubytování
    private ?float                $cenaPredmetu                  = null;  // cena předmětů objednaných z shopu
    private ?float                $cenaStravy                    = null;  // cena jídel objednaných z shopu
    private ?float                $cenaVstupne                   = null;
    private ?float                $cenaVstupnePozde              = null;
    private ?float                $bonusZaVedeniAktivit          = null;  // sleva za tech. aktivity a odvedené aktivity
    private ?float                $slevaObecna                   = null;  // sleva získaná z tabulky slev
    private ?float                $nevyuzityBonusZaVedeniAktivit = null;  // zbývající sleva za odvedené aktivity (nevyužitá část)
    private ?float                $vyuzityBonusZaVedeniAktivit   = null;  // sleva za odvedené aktivity (využitá část)
    private                       $sumyPlatebVRocich             = [];  // platby připsané na účet v jednotlivých letech (zatím jen letos; protože máme obskurnost jménem "Uzavření ročníku")
    private string | false | null $datumPosledniPlatby           = null;        // datum poslední připsané platby

    private ?KategorieNeplatice $kategorieNeplatice       = null;
    private ?array              $dobrovolneVstupnePrehled = null;

    private bool $prepocitavam = false;
    /** @var array<string> */
    private array $zapocteno = [];

    private const PORADI_NADPISU = 1;
    private const PORADI_POLOZKY = 2;
    // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    private const AKTIVITY        = -1;
    private const PREDMETY_STRAVA = 1;
    private const UBYTOVANI       = 2;
    // mezera na typy předmětů (1-4? viz db)
    /** čísla konstant určují pořadí zobrazení v Infopultu */
    private const VSTUPNE                    = 10;
    private const PRIPSANE_SLEVY             = 11;
    private const CELKOVA                    = 12;
    private const ZUSTATEK_Z_PREDCHOZICH_LET = 13;
    private const ORGSLEVA                   = 14; // Bonus za aktivity
    private const BRIGADNICKA_ODMENA         = 15;
    private const PLATBY_NADPIS              = 16;
    private const PLATBA                     = 17;
    private const VYSLEDNY                   = 18;
    private const KATEGORIE_NEPLATICE        = 19;

    /**
     * Vrátí výchozí vygenerovanou slevu za vedení dané aktivity
     * @return int
     */
    public static function bonusZaAktivitu(
        Aktivita           $aktivita,
        SystemoveNastaveni $systemoveNastaveni,
    ): int {
        if ($aktivita->nedavaBonus()) {
            return 0;
        }
        $delka = $aktivita->delka();
        if ($delka == 0) {
            return 0;
        }
        foreach ($systemoveNastaveni->bonusyZaVedeniAktivity() as $tabDelka => $tabSleva) {
            if ($delka <= $tabDelka) {
                return $tabSleva;
            }
        }

        return 0;
    }

    /**
     * @param array|\Uzivatel[] $organizatori
     * @return array|\Uzivatel[]
     */
    public static function nechOrganizatorySBonusemZaVedeniAktivit(array $organizatori): array
    {
        return array_filter($organizatori, static function (
            \Uzivatel $organizator,
        ) {
            return $organizator->maPravoNaPoradaniAktivit()
                   && $organizator->maPravoNaBonusZaVedeniAktivit();
        });
    }

    public static function prumerneVstupneRoku(int $rocnik): float
    {
        $typVstupne = TypPredmetu::VSTUPNE;

        return round(
            (float)dbOneCol(<<<SQL
SELECT SUM(cena_nakupni) / COUNT(*)
FROM shop_nakupy
JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
WHERE shop_predmety.typ = {$typVstupne}
    AND shop_nakupy.rok = {$rocnik}
    AND shop_nakupy.cena_nakupni > 0
SQL,
            ),
            2,
        );
    }

    public static function zaokouhli($cena): float
    {
        return round((float)$cena, 2);
    }

    /**
     * @param \Uzivatel $u uživatel, pro kterého se finance sestavují
     * @param float $zustatek zůstatek na účtu z minulých GC
     * @param bool $logovat ukládat seznam předmětů?
     */
    public function __construct(
        private readonly \Uzivatel          $u,
        private readonly float              $zustatekZPredchozichRocniku,
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly bool               $logovat = true,
    ) {
    }

    public function obnovUdaje(): void
    {
        $this->obnovVychoziHodnotyObjektu();
    }

    /** Cena za uživatelovy aktivity */
    public function cenaAktivit(): float
    {
        if ($this->cenaAktivit === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiAktivity();
            }
        }

        return $this->cenaAktivit;
    }

    public function cenaPredmetyAStrava(): float
    {
        return $this->cenaPredmetu() + $this->cenaStravy();
    }

    public function cenaPredmetu(): float
    {
        if ($this->cenaPredmetu === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->cenaPredmetu;
    }

    public function cenaStravy(): float
    {
        if ($this->cenaStravy === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->cenaStravy;
    }

    public function cenaUbytovani(): float
    {
        if ($this->cenaUbytovani === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->cenaUbytovani;
    }

    /**
     * Vrátí / nastaví datum posledního provedení platby
     *
     * @return string|null datum poslední platby
     */
    public function datumPosledniPlatby(): ?string
    {
        if ($this->datumPosledniPlatby === null) {
            $this->datumPosledniPlatby = dbOneCol(<<<SQL
                SELECT MAX(provedeno) AS datum
                FROM platby
                WHERE castka > 0 AND id_uzivatele = {$this->u->id()}
                SQL,
                                         ) ?? false;
        }

        return $this->datumPosledniPlatby !== false
            ? $this->datumPosledniPlatby
            : null;
    }

    /**
     * Vrátí html formátovaný přehled financí
     * @param null|int[] $jenKategorieIds
     * @param boolean $vcetneCeny
     * @param boolean $vcetneMazani
     */
    public function prehledHtml(
        array $jenKategorieIds = null,
        bool  $vcetneCeny = true,
        bool  $vcetneMazani = false,
    ): string {
        $out = '<table class="objednavky">';
        $prehled = $this->serazenyPrehled();
        if ($jenKategorieIds) {
            if (in_array(TypPredmetu::VSTUPNE, $jenKategorieIds) && $this->dobrovolneVstupnePrehled()) {
                $prehled[] = $this->dobrovolneVstupnePrehled();
            }
            $prehled = array_filter($prehled, static function (
                $radekPrehledu,
            ) use
            (
                $jenKategorieIds,
            ) {
                return in_array($radekPrehledu['kategorie'], $jenKategorieIds);
            });
            // Infopult nechce mikronadpisy, pokud je přehled omezen jen na pár kategorií
            $prehled = array_filter($prehled, static function (
                $radekPrehledu,
            ) {
                // našli jsme nadpis, jediný je tučně
                return !str_contains($radekPrehledu['nazev'], '<b>');
            });
        }

        foreach ($prehled as $radekPrehledu) {
            $castkaRow = '';
            if ($vcetneCeny) {
                $castkaRow = "<td>{$radekPrehledu['castka']}</td>";
            }
            $mazaniRow = '';
            if ($vcetneMazani) {
                if (!empty($radekPrehledu['id_polozky'])) {
                    $klicZrusNakuppolozky = self::KLIC_ZRUS_NAKUP_POLOZKY;
                    $mazaniRow = <<<HTML
                        <td xmlns="http://www.w3.org/1999/html">
                            <form method="post" onsubmit="return confirm('Opravdu zrušit objednávku {$radekPrehledu['nazev']}?')">
                                <input type="hidden" name="$klicZrusNakuppolozky" value="{$radekPrehledu['id_polozky']}">
                                <button type="submit">
                                    <i class='fa fa-trash' aria-hidden='true'></i>
                                </button>
                            </form>
                        </td>
                    HTML;
                } else {
                    $mazaniRow = '<td></td>';
                }
            }
            $out .= <<<HTML
              <tr>
                <td>{$radekPrehledu['nazev']}</td>
                $castkaRow
                $mazaniRow
              </tr>
              HTML;
        }
        $out .= '</table>';

        return $out;
    }

    public function prehledPopis(): string
    {
        $out = [];
        foreach ($this->serazenyPrehled() as $r) {
            $out[] = $r['nazev'] . ' ' . $r['castka'];
        }

        return implode(', ', $out);
    }

    private function prehled(): array
    {
        if ($this->prehled === null) {
            $this->prehled = [];
            if ($this->logovat) {
                $this->prepocti();
            }
        }

        return $this->prehled;
    }

    private function serazenyPrehled(): array
    {
        $prehled = $this->prehled();
        usort($prehled, [static::class, 'cmp']);

        return $prehled;
    }

    public function dejStrukturovanyPrehled(): array
    {
        if ($this->strukturovanyPrehled === null) {
            if ($this->logovat) {
                $this->prepocti();
            }
        }

        return $this->strukturovanyPrehled ?? [];
    }

    public function dejPolozkyProBfgr(): array
    {
        if ($this->polozkyProBfgr === null) {
            if ($this->logovat) {
                $this->prepocti();
            }
        }

        return $this->polozkyProBfgr ?? [];
    }

    /**
     * Připíše aktuálnímu uživateli platbu ve výši $castka.
     * @throws \DbDuplicateEntryException
     */
    public function pripis(
        string | float | int $castka,
        \Uzivatel            $provedl,
        ?string              $poznamka = null,
        string | int | null  $idFioPlatby = null,
        ?\DateTimeInterface  $kdy = null,
    ): void {
        $rok = $kdy?->format('Y') ?? $this->systemoveNastaveni->rocnik();
        dbInsert(
            PlatbySqlStruktura::PLATBY_TABULKA,
            [
                PlatbySqlStruktura::ID_UZIVATELE => $this->u->id(),
                PlatbySqlStruktura::FIO_ID       => $idFioPlatby
                    ?: null,
                PlatbySqlStruktura::CASTKA       => prevedNaFloat($castka),
                PlatbySqlStruktura::ROK          => $rok,
                PlatbySqlStruktura::PROVEDL      => $provedl->id(),
                PlatbySqlStruktura::POZNAMKA     => $poznamka
                    ?: null,
                PlatbySqlStruktura::PROVEDENO    => $kdy?->format(DateTimeCz::FORMAT_DB),
            ],
        );
    }

    /**
     * Připíše aktuálnímu uživateli $u slevu ve výši $sleva
     */
    public function pripisSlevu(
        string | float | int $sleva,
        ?string              $poznamka,
        \Uzivatel            $provedl,
    ): float {
        $sleva = prevedNaFloat($sleva);
        dbQuery(
            'INSERT INTO slevy(id_uzivatele, castka, rok, provedl, poznamka) VALUES ($1, $2, $3, $4, $5)',
            [$this->u->id(), $sleva, ROCNIK, $provedl->id(), $poznamka
                ?: null],
        );

        return $sleva;
    }

    /** Vrátí aktuální stav na účtu uživatele pro tento rok */
    public function stav(): float
    {
        if ($this->stav === null) {
            $this->prepocti();
        }

        return $this->stav;
    }

    /** Vrátí výši obecné slevy připsané uživateli pro tento rok. */
    public function slevaObecna(): float
    {
        if ($this->slevaObecna === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiSlevy();
            }
        }

        return $this->slevaObecna;
    }

    /** Vrátí člověkem čitelný stav účtu */
    public function formatovanyStav(bool $vHtmlFormatu = true)
    {
        $mezera = $vHtmlFormatu
            ? '&thinsp;'
            // thin space
            : ' ';

        return $this->stav() . $mezera . $this->mena();
    }

    public function mena(): string
    {
        return 'Kč';
    }

    /**
     * Vrací součinitel ceny aktivit jako float číslo. Např. 0.0 pro aktivity
     * zdarma a 1.0 pro aktivity za plnou cenu.
     */
    public function slevaAktivity(?DataSourcesCollector $dataSourcesCollector = null): float
    {
        return $this->soucinitelCenyAktivit($dataSourcesCollector); //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
    }

    public static function slevaAktivityDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::soucinitelCenyAktivitDSC($dataSourcesCollector);
    }

    public function slevaZaAktivityVProcentech(): float
    {
        return 100 - ($this->soucinitelCenyAktivit() * 100);
    }

    /**
     * Výše vypravěčské slevy (celková)
     */
    public function bonusZaVedeniAktivit(): float
    {
        if ($this->bonusZaVedeniAktivit === null) {
            $this->bonusZaVedeniAktivit = 0.0;
            $this->zapoctiAktivity();
            $this->zapoctiSlevy();
            $this->zapoctiVedeniAktivit();
        }

        return $this->bonusZaVedeniAktivit;
    }

    /**
     * Výše zbývající vypravěčské slevy
     */
    public function nevyuzityBonusZaAktivity(): float
    {
        if ($this->nevyuzityBonusZaVedeniAktivit === null) {
            $this->nevyuzityBonusZaVedeniAktivit = 0.0;
            $this->prepocti();
        }

        return $this->nevyuzityBonusZaVedeniAktivit;
    }

    /**
     * Výše použitého bonusu za vypravěčství (vyčerpané vypravěčské slevy)
     */
    public function vyuzityBonusZaAktivity(): float
    {
        if ($this->vyuzityBonusZaVedeniAktivit === null) {
            $this->prepocti();
        }

        return $this->vyuzityBonusZaVedeniAktivit;
    }

    /**
     * @return array<string>
     */
    public function slevyNaAktivity(): array
    {
        if ($this->slevyNaAktivity === null) {
            $this->slevyNaAktivity = [];
            if ($this->u->maPravo(Pravo::AKTIVITY_ZDARMA)) {
                $this->slevyNaAktivity[] = 'sleva ' . self::PLNA_SLEVA_PROCENT . '%';
            } elseif ($this->u->maPravo(Pravo::CASTECNA_SLEVA_NA_AKTIVITY)) {
                $this->slevyNaAktivity[] = 'sleva ' . self::CASTECNA_SLEVA_PROCENT . '%';
            }
        }

        return $this->slevyNaAktivity;
    }

    /**
     * Modré tričko zdarma je ještě navíc k tomuto. Takže totální maximální počet triček zdarma je
     * @see maximalniPocetLibovolnychTricekZdarmaBezModrychZdarma
     * + @see maximalniPocetModrychTricekZdarma
     */
    public function maximalniPocetLibovolnychTricekZdarmaBezModrychZdarma(): int
    {
        return $this->u->maPravo(Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA)
            ? 2
            : (
            $this->u->maPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA)
                ? 1
                : 0
            );
    }

    public function maximalniPocetModrychTricekZdarma(): int
    {
        return $this->u->maPravo(Pravo::MODRE_TRICKO_ZDARMA) && $this->bonusZaVedeniAktivit() >= $this->systemoveNastaveni->modreTrickoZdarmaOd()
            ? 1
            : 0;
    }

    /**
     * Viz ceník
     * @return array<string>
     */
    public function slevyVse(): array
    {
        return $this->cenik()->slevySpecialni();
    }

    /**
     * Vrátí součinitel ceny aktivit, tedy slevy uživatele vztahující se k
     * aktivitám. Vrátí hodnotu.
     */
    private function soucinitelCenyAktivit(
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): float {
        self::soucinitelCenyAktivitDSC($dataSourcesCollector);

        if ($this->soucinitelCenyAKtivit === null) {
            // pomocné proměnné
            $sleva = 0; // v procentech
            // výpočet pravidel
            if ($this->u->maPravo(Pravo::AKTIVITY_ZDARMA, $dataSourcesCollector)) {
                $sleva += self::PLNA_SLEVA_PROCENT;
                $this->slevyNaAktivity[] = 'aktivity zdarma';
            } elseif ($this->u->maPravo(Pravo::CASTECNA_SLEVA_NA_AKTIVITY, $dataSourcesCollector)) {
                $sleva += self::CASTECNA_SLEVA_PROCENT;
                $this->slevyNaAktivity[] = 'aktivity se slevou ' . $sleva . ' %';
            }
            if ($sleva > self::MAX_SLEVA_AKTIVIT_PROCENT) {
                // omezení výše slevy na maximální hodnotu
                $sleva = self::MAX_SLEVA_AKTIVIT_PROCENT;
            }
            $slevaAktivity = (100 - $sleva) / 100;
            // výsledek
            $this->soucinitelCenyAKtivit = (float)$slevaAktivity;
        }

        return $this->soucinitelCenyAKtivit;
    }

    private static function soucinitelCenyAktivitDSC(
        ?DataSourcesCollector $dataSourcesCollector,
    ): void {
        \Uzivatel::maPravoDSC($dataSourcesCollector);
    }

    public function cenaVstupne(): float
    {
        if ($this->cenaVstupne === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->cenaVstupne;
    }

    public function cenaVstupnePozde(): float
    {
        if ($this->cenaVstupnePozde === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->cenaVstupnePozde;
    }

    public function proplacenyBonusZaAktivity(): float
    {
        if ($this->proplacenyBonusZaVedeniAktivit === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->proplacenyBonusZaVedeniAktivit;
    }

    public function brigadnickaOdmena(): float
    {
        if ($this->brigadnickaOdmena === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiAktivity();
            }
        }

        return $this->brigadnickaOdmena;
    }

    /**
     * Započítá do mezisoučtů aktivity uživatele
     */
    private function zapoctiAktivity(): void
    {
        if (!empty($this->zapocteno[__FUNCTION__])) {
            throw new \RuntimeException(
                sprintf('Započítání %s již proběhlo.', __FUNCTION__),
            );
        }
        $this->cenaAktivit = 0.0;
        $this->brigadnickaOdmena = 0.0;
        $this->sumaStorna = 0.0;
        $this->bonusZaVedeniAktivit ??= 0.0;

        $soucinitelAktivit = $this->soucinitelCenyAktivit();
        $rok = ROCNIK;
        $idUcastnika = $this->u->id();
        $technicka = TypAktivity::TECHNICKA; // výpomoc, jejíž cena se započítá jako bonus vypravěče, který může použít na nákup na GC
        $brigadnicka = TypAktivity::BRIGADNICKA; // placený "zaměstnanec"
        $prihlasenAleNedorazil = StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL;
        $pozdeZrusil = StavPrihlaseni::POZDE_ZRUSIL;

        $sql = <<<SQL
SELECT
    aktivita.nazev_akce AS nazev,
    (
        aktivita.cena
        * (stav_prihlaseni.platba_procent/100)
        * IF(aktivita.bez_slevy OR aktivita.typ IN ($technicka, $brigadnicka), 1.0, $soucinitelAktivit)
        * IF(aktivita.typ IN ($technicka, $brigadnicka) AND prihlaseni.id_stavu_prihlaseni IN ($prihlasenAleNedorazil, $pozdeZrusil), 0.0, 1.0) -- zrušit 'storno' pro pozdě odhlášené technické a brigádnické aktivity
     ) AS cena,
    aktivita.typ,
    stav_prihlaseni.id_stavu_prihlaseni
FROM (
    SELECT * FROM akce_prihlaseni WHERE id_uzivatele = $idUcastnika
    UNION
    SELECT * FROM akce_prihlaseni_spec WHERE id_uzivatele = $idUcastnika
) AS prihlaseni
JOIN akce_seznam AS aktivita
    ON prihlaseni.id_akce = aktivita.id_akce
JOIN akce_prihlaseni_stavy AS stav_prihlaseni
    ON prihlaseni.id_stavu_prihlaseni = stav_prihlaseni.id_stavu_prihlaseni
WHERE rok = $rok
SQL;

        $result = $this->systemoveNastaveni->db()->dbFetchAll(
            [
                AkceSeznamSqlStruktura::AKCE_SEZNAM_TABULKA,
                AkcePrihlaseniSqlStruktura::AKCE_PRIHLASENI_TABULKA,
                AkcePrihlaseniSpecSqlStruktura::AKCE_PRIHLASENI_SPEC_TABULKA,
                AkcePrihlaseniStavySqlStruktura::AKCE_PRIHLASENI_STAVY_TABULKA,
            ],
            $sql,
        );

        $a = $this->u->koncovkaDlePohlavi();
        foreach ($result as $r) {
            if ($r['typ'] == TypAktivity::TECHNICKA) {
                if ($this->u->maPravoNaBonusZaVedeniAktivit()) {
                    $this->bonusZaVedeniAktivit += (float)$r['cena'];
                }
            } elseif ($r['typ'] == TypAktivity::BRIGADNICKA) {
                if ($this->u->jeBrigadnik()) {
                    $this->brigadnickaOdmena += (float)$r['cena'];
                }
            } else {
                $this->cenaAktivit += $r['cena'];
                if (StavPrihlaseni::platiStorno((int)$r['id_stavu_prihlaseni'])) {
                    $this->sumaStorna += $r['cena'];
                }
            }

            $poznamka = '';
            if ($r['id_stavu_prihlaseni'] == StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL) {
                $poznamka = " <i>(nedorazil$a)</i>";
            }
            if ($r['id_stavu_prihlaseni'] == StavPrihlaseni::POZDE_ZRUSIL) {
                $poznamka = " <i>(odhlášen$a pozdě)</i>";
            }
            if ($r['id_stavu_prihlaseni'] == StavPrihlaseni::SLEDUJICI) {
                continue;
            }
            $this->log(
                nazev: $r['nazev'] . $poznamka,
                castka: in_array($r['typ'], TypAktivity::interniTypy())
                    ? 0
                    : $r['cena'],
                kategorie: self::AKTIVITY,
                idPolozky: null,
            );
        }
        $this->zapocteno[__FUNCTION__] = true;
    }

    public function sumaPlateb(
        ?int $rocnik = null,
        bool $prepocti = false,
    ): float {
        $rocnik ??= $this->systemoveNastaveni->rocnik();
        if (!isset($this->sumyPlatebVRocich[$rocnik]) || $prepocti) {
            $uzivatelSystemId = \Uzivatel::SYSTEM;
            $result = dbQuery(<<<SQL
                SELECT
                    IF(provedl=$uzivatelSystemId,
                      CONCAT(DATE_FORMAT(COALESCE(pripsano_na_ucet_banky, provedeno),'%e.%c.'),' Platba na účet'),
                      CONCAT(DATE_FORMAT(COALESCE(pripsano_na_ucet_banky, provedeno),'%e.%c.'),' ',IFNULL(poznamka,'(bez poznámky)'))
                      ) as nazev,
                    castka as cena
                FROM platby
                WHERE id_uzivatele = {$this->u->id()} AND rok = $rocnik
                SQL,
            );
            $sumaPlateb = 0.0;
            while ($row = mysqli_fetch_assoc($result)) {
                $sumaPlateb += (float)$row['cena'];
                $this->log(
                    nazev: $row['nazev'],
                    castka: $row['cena'],
                    kategorie: self::PLATBA,
                    idPolozky: null,
                );
            }
            $this->sumyPlatebVRocich[$rocnik] = self::zaokouhli($sumaPlateb);
        }

        return $this->sumyPlatebVRocich[$rocnik];
    }

    private function dobrovolneVstupnePrehled(): array
    {
        if ($this->dobrovolneVstupnePrehled === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiShop();
            }
        }

        return $this->dobrovolneVstupnePrehled;
    }

    /**
     * Započítá do mezisoučtů nákupy v eshopu
     */
    private function zapoctiShop(): void
    {
        if (!empty($this->zapocteno[__FUNCTION__])) {
            throw new \RuntimeException(
                sprintf('Započítání %s již proběhlo.', __FUNCTION__),
            );
        }
        $this->cenaUbytovani = 0.0;
        $this->cenaVstupne = 0.0;
        $this->cenaVstupnePozde = 0.0;
        $this->cenaPredmetu = 0.0;
        $this->cenaStravy = 0.0;
        $this->proplacenyBonusZaVedeniAktivit = 0.0;
        $this->dobrovolneVstupnePrehled = [];
        $this->polozkyProBfgr = [];

        $o = dbQuery('
      SELECT predmety.id_predmetu, predmety.nazev, nakupy.cena_nakupni, predmety.typ, predmety.ubytovani_den, predmety.model_rok
      FROM shop_nakupy AS nakupy
      JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
      WHERE nakupy.id_uzivatele = $0 AND nakupy.rok = $1
      ORDER BY nakupy.cena_nakupni -- od nejlevnějších kvůli aplikaci slev na trička
    ', [$this->u->id(), ROCNIK]);

        $soucty = [];
        foreach ($o as $r) {
            $priceAfterDiscountDto = $this->cenik()->cena($r);
            $cena = $priceAfterDiscountDto->finalPrice;
            // započtení ceny
            if ($r['typ'] == TypPredmetu::UBYTOVANI) {
                $this->cenaUbytovani += $cena;
            } elseif ($r['typ'] == TypPredmetu::VSTUPNE) {
                if (!str_contains($r['nazev'], 'pozdě')) {
                    assert($this->cenaVstupne === 0.0);
                    $this->cenaVstupne = $cena;
                } else {
                    assert($this->cenaVstupnePozde === 0.0);
                    $this->cenaVstupnePozde = $cena;
                }
                $this->dobrovolneVstupnePrehled = $this->formatujProLog(
                    nazev: "{$r[PredmetSql::NAZEV]} $cena.-",
                    castka: $cena,
                    kategorie: (int)$r[PredmetSql::TYP],
                    poradiVKategorii: self::PORADI_POLOZKY,
                    poradiVPodkategorii: 0,
                    idPolozky: (int)$r[PredmetSql::ID_PREDMETU],
                );
            } elseif ($r['typ'] == TypPredmetu::PROPLACENI_BONUSU) {
                $this->proplacenyBonusZaVedeniAktivit += $cena;
            } else {
                if ($r['typ'] == TypPredmetu::JIDLO) {
                    $this->cenaStravy += $cena;
                } elseif (in_array($r['typ'], [TypPredmetu::PREDMET, TypPredmetu::TRICKO])) {
                    $this->cenaPredmetu += $cena;
                } elseif ($r['typ'] != TypPredmetu::PARCON) {
                    throw new NeznamyTypPredmetu(
                        "Neznámý typ předmětu " . var_export($r['typ'], true) . ': ' . var_export($r, true),
                    );
                }
            }
            // přidání roku do názvu
            if ($r['model_rok'] && $r['model_rok'] != ROCNIK) {
                $r['nazev'] = $r['nazev'] . ' ' . $r['model_rok'];
            }

            $this->logPolozkaProBfgr((string)$r['nazev'], 1, $priceAfterDiscountDto, (int)$r['typ']);

            // logování do výpisu
            if (in_array($r['typ'], [TypPredmetu::PREDMET, TypPredmetu::TRICKO])) {
                $soucty[$r['id_predmetu']]['nazev'] = $r['nazev'];
                $soucty[$r['id_predmetu']]['typ'] = $r['typ'];
                $soucty[$r['id_predmetu']]['pocet'] = ($soucty[$r['id_predmetu']]['pocet'] ?? 0) + 1;
                $soucty[$r['id_predmetu']]['suma'] = ($soucty[$r['id_predmetu']]['suma'] ?? 0) + $cena;
            } elseif ($r['typ'] == TypPredmetu::VSTUPNE) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, self::VSTUPNE);
                $this->logb($r['nazev'], $cena, self::VSTUPNE);
            } elseif ($r['typ'] == TypPredmetu::UBYTOVANI) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, $r['typ']);
                $this->log(
                    nazev: $r['nazev'],
                    castka: $cena,
                    kategorie: $r['typ'] !== null
                        ?
                        (int)$r['typ']
                        : null,
                    idPolozky: $r[PredmetSql::ID_PREDMETU],
                    poradiVPodkategorii: $r[PredmetSql::UBYTOVANI_DEN],
                );
            } elseif ($r['typ'] != TypPredmetu::PROPLACENI_BONUSU) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, $r['typ']);
                $this->log(
                    nazev: $r['nazev'],
                    castka: $cena,
                    kategorie: $r['typ'] !== null
                        ?
                        (int)$r['typ']
                        : null,
                    idPolozky: $r[PredmetSql::ID_PREDMETU],
                );
            }
        }

        foreach ($soucty as $idPredmetu => $predmet) {
            $this->logStrukturovane((string)$predmet['nazev'], (int)$predmet['pocet'], (float)$predmet['suma'], $predmet['typ']);
            // dvojmezera kvůli řazení
            $this->log(
                nazev: $predmet['nazev'] . '  ' . $predmet['pocet'] . '×',
                castka: $predmet['suma'],
                kategorie: (int)$predmet['typ'],
                idPolozky: $idPredmetu,
            );
        }
        $this->zapocteno[__FUNCTION__] = true;
    }

    /**
     * Započítá ručně zadané slevy z tabulky slev.
     */
    private function zapoctiSlevy(): void
    {
        if (!empty($this->zapocteno[__FUNCTION__])) {
            throw new \RuntimeException(
                sprintf('Započítání %s již proběhlo.', __FUNCTION__),
            );
        }
        $this->slevaObecna = 0.0;
        $this->bonusZaVedeniAktivit ??= 0.0;

        $q = dbQuery('
            SELECT castka, poznamka
            FROM slevy
            WHERE id_uzivatele = $0 AND rok = $1
            ', [$this->u->id(), ROCNIK],
        );

        foreach ($q as $sleva) {
            if (str_contains($sleva[SlevySqlStruktura::POZNAMKA], '#kompenzace')) {
                // speciální typ slevy: kompenzace
                // započítává se stejně jako sleva za vedené aktivity
                $this->bonusZaVedeniAktivit += $sleva[SlevySqlStruktura::CASTKA];
            } else {
                // normální sleva
                // započítává se zvlášť
                $this->slevaObecna += (float)$sleva[SlevySqlStruktura::CASTKA];
            }
        }
        $this->zapocteno[__FUNCTION__] = true;
    }

    /**
     * Započítá do mezisoučtů slevy za organizované aktivity
     */
    private function zapoctiVedeniAktivit(): void
    {
        if (!empty($this->zapocteno[__FUNCTION__])) {
            throw new \RuntimeException(
                sprintf('Započítání %s již proběhlo.', __FUNCTION__),
            );
        }
        $this->bonusZaVedeniAktivit ??= 0.0;
        if (!$this->u->maPravoNaPoradaniAktivit()) {
            return;
        }
        if ($this->u->nemaPravoNaBonusZaVedeniAktivit()) {
            return;
        }
        if (!$this->u->gcPrihlasen()) {
            return; // pokud se například odhlásí těsně před GC
        }
        foreach (Aktivita::zOrganizatora($this->u, $this->systemoveNastaveni) as $a) {
            $this->bonusZaVedeniAktivit += self::bonusZaAktivitu($a, $this->systemoveNastaveni);
        }
        $this->zapocteno[__FUNCTION__] = true;
    }

    /**
     * Započítá do mezisoučtů zůstatek z minulých let
     */
    private function zapoctiZustatekZPredchozichRocniku(): void
    {
        if (!empty($this->zapocteno[__FUNCTION__])) {
            throw new \RuntimeException(
                sprintf('Započítání %s již proběhlo.', __FUNCTION__),
            );
        }
        $this->logb(
            'Zůstatek z minulých let',
            $this->zustatekZPredchozichRocniku(),
            self::ZUSTATEK_Z_PREDCHOZICH_LET,
        );
        $this->zapocteno[__FUNCTION__] = true;
    }

    private function aplikujBonusZaVedeniAktivit(float $cena): float
    {
        $puvodniBonusZaVedeniAktivit = $this->bonusZaVedeniAktivit();
        $zbyvajiciBonusZaVedeniAktivit = $puvodniBonusZaVedeniAktivit;
        $zbyvajiciCena = $cena;
        ['sleva' => $nevyuzityBonusZaVedeniAktivit] = Cenik::aplikujSlevu(
            cena: $zbyvajiciCena, // ovlivněno zpětně přes referenci !
            sleva: $zbyvajiciBonusZaVedeniAktivit, // ovlivněno zpětně přes referenci !
        );
        $this->nevyuzityBonusZaVedeniAktivit = $nevyuzityBonusZaVedeniAktivit;
        $this->vyuzityBonusZaVedeniAktivit = $zbyvajiciBonusZaVedeniAktivit - $nevyuzityBonusZaVedeniAktivit;
        /** Do výsledné ceny, respektive celkového stavu, už započítáváme celý bonus za aktivity https://trello.com/c/8SWTdpYl/1069-zobrazen%C3%AD-financ%C3%AD-%C3%BA%C4%8Dastn%C3%ADka */
        $cena -= $this->bonusZaVedeniAktivit();

        if ($puvodniBonusZaVedeniAktivit) {
            $this->logb(
                'Bonus za aktivity',
                $puvodniBonusZaVedeniAktivit,
                self::ORGSLEVA,
            );
        }

        return $cena;
    }

    private function aplikujBrigadnickouOdmenu(float $cena): float
    {
        if ($this->brigadnickaOdmena()) {
            $this->logb(
                'Brigádnická odměna',
                $this->brigadnickaOdmena(),
                self::BRIGADNICKA_ODMENA,
            );
        }

        return $cena - $this->brigadnickaOdmena();
    }

    private function aplikujObecnouSlevu(float $cena): float
    {
        $puvodniObecnaSleva = $this->slevaObecna();
        $zbyvajiciObecnaSleva = $puvodniObecnaSleva;
        ['cena' => $cena, 'sleva' => $nevyuzitaObecnaSleva] = Cenik::aplikujSlevu(
            cena: $cena, // ovlivněno zpětně přes referenci !
            sleva: $zbyvajiciObecnaSleva, // ovlivněno zpětně přes referenci !
        );

        if ($puvodniObecnaSleva > 0) {
            $this->logb('Obecné slevy', $puvodniObecnaSleva, self::PRIPSANE_SLEVY);
        }

        if ((float)$nevyuzitaObecnaSleva !== 0.0) {
            $vyuzitaSlevaObecna = $zbyvajiciObecnaSleva - $nevyuzitaObecnaSleva;
            $this->logb(
                'Sleva',
                $vyuzitaSlevaObecna,
                self::PRIPSANE_SLEVY,
            );
            $this->log(
                nazev: '<i>Nevyužitá sleva ' . $nevyuzitaObecnaSleva . '</i>',
                castka: '&nbsp;',
                kategorie: self::PRIPSANE_SLEVY,
                idPolozky: null,
            );
        }

        return $cena;
    }

    /**
     * @return float zůstatek na účtu z minulých GC
     */
    public function zustatekZPredchozichRocniku(): float
    {
        return $this->zustatekZPredchozichRocniku;
    }

    public function kategorieNeplatice(): KategorieNeplatice
    {
        if ($this->kategorieNeplatice === null) {
            $this->kategorieNeplatice = KategorieNeplatice::vytvorProNadchazejiciVlnuZGlobals(
                $this->u,
                $this->systemoveNastaveni,
            );
        }

        return $this->kategorieNeplatice;
    }

    public function dejQrKodProPlatbu(): ?ResultInterface
    {
        $castkaCzk = $this->stav() >= 0
            ? 0.1
            // nulová, respektive dobrovolná platba
            : -$this->stav();

        $qrPlatba = QrPlatba::dejQrProTuzemskouPlatbu(
            $castkaCzk,
            $this->u->id(),
        );

        // SEPA platbu přes QR kód neumí zřejmě žádná slovenská banka, takže pro mimočeské nezobrazíme nic

        return $qrPlatba->dejQrObrazek();
    }

    public function sumaStorna(): float
    {
        if ($this->sumaStorna === null) {
            if (!$this->prepocitavam) {
                $this->prepocti();
            } else {
                $this->zapoctiAktivity();
            }
        }

        return $this->sumaStorna;
    }

    private function cenik(): Cenik
    {
        if ($this->cenik === null) {
            $this->cenik = new Cenik(
                $this->u,
                $this,
                $this->systemoveNastaveni,
            );
        }

        return $this->cenik;
    }

    private function prepocti(): void
    {
        $this->prepocitavam = true;

        try {
            if (!empty($this->zapocteno[__FUNCTION__])) {
                throw new \RuntimeException(
                    sprintf('Započítání %s již proběhlo.', __FUNCTION__),
                );
            }
            $this->zapoctiVedeniAktivit();
            $this->zapoctiSlevy();

            $this->zapoctiAktivity();
            $this->zapoctiShop();
            $this->zapoctiZustatekZPredchozichRocniku();

            $cena =
                $this->cenaPredmetu()
                + $this->cenaStravy()
                + $this->cenaUbytovani()
                + $this->cenaAktivit();

            $cena = $cena
                    + $this->cenaVstupne()
                    + $this->cenaVstupnePozde();

            $cena = $this->aplikujObecnouSlevu($cena);

            $this->logb('Celková cena', $cena, self::CELKOVA);

            /** bonusy a odměny nechceme v zobrazované Celkové ceně https://trello.com/c/8SWTdpYl/1069-zobrazen%C3%AD-financ%C3%AD-%C3%BA%C4%8Dastn%C3%ADka */
            $cena = $this->aplikujBonusZaVedeniAktivit($cena);
            $cena = $this->aplikujBrigadnickouOdmenu($cena);

            $this->stav = self::zaokouhli(
                -$cena
                + $this->sumaPlateb()
                + $this->zustatekZPredchozichRocniku,
            );

            $this->logb('Aktivity', $this->cenaAktivit(), self::AKTIVITY);
            $this->logb('Ubytování', $this->cenaUbytovani(), self::UBYTOVANI);
            $this->logb('Předměty a strava', $this->cenaPredmetyAStrava(), self::PREDMETY_STRAVA);
            $this->logb('Připsané platby', $this->sumaPlateb(), self::PLATBA);
            $this->logb('Stav financí', $this->stav(), self::VYSLEDNY);
            if ($this->kategorieNeplatice()?->melByBytOdhlasen()) {
                $this->logb(
                    '<span style="color: darkred">Bude odhlášen jako neplatič kategorie</span>',
                    $this->kategorieNeplatice()->ciselnaKategoriiNeplatice(),
                    self::KATEGORIE_NEPLATICE,
                );
            }

            $this->zapocteno[__FUNCTION__] = true;
        } finally {
            $this->prepocitavam = false;
        }
    }

    private static function cpm_kategorie_razeni(int $kategorie): int
    {
        return match ($kategorie) {
            2       => 4,
            3       => 2,
            4       => 3,
            default => $kategorie,
        };
    }

    /** Porovnávání k řazení php 4 style :/ */
    private function cmp(
        array $a,
        array $b,
    ): int | float {
        // podle typu
        if ($a['kategorie'] !== $b['kategorie']) {
            $podleKategorii = Finance::cpm_kategorie_razeni((int)$a['kategorie'])
                              - Finance::cpm_kategorie_razeni((int)$b['kategorie']);
            if ($podleKategorii !== 0) {
                return $podleKategorii;
            }
        }
        if ($a['poradi_v_kategorii'] !== $b['poradi_v_kategorii']) {
            $razeniVKategorii = (int)$a['poradi_v_kategorii'] - (int)$b['poradi_v_kategorii'];
            if ($razeniVKategorii !== 0) {
                return $razeniVKategorii;
            }
        }
        if ($a['poradi_v_podkategorii'] !== $b['poradi_v_podkategorii']) {
            $razeniVPodkategorii = (int)$a['poradi_v_podkategorii'] - (int)$b['poradi_v_podkategorii'];
            if ($razeniVPodkategorii !== 0) {
                return $razeniVPodkategorii;
            }
        }
        // podle názvu
        if ($a['nazev'] !== $b['nazev']) {
            $o = strcmp(strip_tags((string)$a['nazev']), strip_tags((string)$b['nazev']));
            if ($o) {
                return $o;
            }
        }
        if ($a['castka'] !== $b['castka']) {
            // podle ceny (může obsahovat HTML, například '<b>0</b>')
            $dleCastky = (float)strip_tags($a['castka']) <=> (float)strip_tags($b['castka']);
            if ($dleCastky !== 0) {
                return $dleCastky;
            }
        }

        return $a['id_polozky'] <=> $b['id_polozky'];
    }

    private function logPolozkaProBfgr(
        string                $nazev,
        int                   $pocet,
        PriceAfterDiscountDto $priceAfterDiscountDto,
        int                   $typ,
    ): void {
        if (!$this->logovat) {
            return;
        }
        $this->polozkyProBfgr ??= [];
        $this->polozkyProBfgr[] = [
            'nazev'  => trim($nazev),
            'pocet'  => $pocet,
            'castka' => $priceAfterDiscountDto->finalPrice,
            'sleva' => $priceAfterDiscountDto->discount,
            'typ'    => $typ,
        ];
    }

    private function logStrukturovane(
        string       $nazev,
        int          $pocet,
        ?float       $castka,
        int | string $typ,
    ): void {
        if (!$this->logovat) {
            return;
        }
        $this->strukturovanyPrehled ??= [];
        $this->strukturovanyPrehled[] = [
            'nazev'  => trim($nazev),
            'pocet'  => $pocet,
            'castka' => $castka,
            'typ'    => (int)$typ,
        ];
    }

    /**
     * Zaloguje do seznamu nákupů položku (pokud je logování zapnuto)
     */
    private function log(
        string                      $nazev,
        null | string | float | int $castka,
        ?int                        $kategorie,
        ?int                        $idPolozky,
        int                         $poradiVKategorii = self::PORADI_POLOZKY,
        ?int                        $poradiVPodkategorii = 0,
    ): void {
        if (!$this->logovat) {
            return;
        }
        $this->prehled ??= [];
        // přidání
        $this->prehled[] = $this->formatujProLog(
            $nazev,
            $castka,
            $kategorie,
            $poradiVKategorii,
            $poradiVPodkategorii,
            $idPolozky,
        );
    }

    private function formatujProLog(
        string                      $nazev,
        null | string | float | int $castka,
        ?int                        $kategorie,
        int                         $poradiVKategorii,
        ?int                        $poradiVPodkategorii,
        ?int                        $idPolozky,
    ): array {
        if (is_numeric($castka)) {
            $castka = self::zaokouhli($castka);
        }

        return [
            'nazev'                 => $nazev,
            'castka'                => $castka,
            'kategorie'             => $kategorie,
            'poradi_v_kategorii'    => $poradiVKategorii,
            'poradi_v_podkategorii' => $poradiVPodkategorii,
            'id_polozky'            => $idPolozky,
        ];
    }

    /**
     * Zaloguje zvýrazněný záznam
     */
    private function logb(
        $nazev,
        $castka,
        int $kategorie,
        int $poradiNadpisu = self::PORADI_NADPISU,
        ?int $idPolozky = null,
    ): void {
        $castka = self::zaokouhli($castka);

        $this->log(
            nazev: "<b>$nazev</b>",
            castka: "<b>$castka</b>",
            kategorie: $kategorie,
            idPolozky: $idPolozky,
            poradiVKategorii: $poradiNadpisu,
        );
    }
}
