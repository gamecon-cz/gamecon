<?php

namespace Gamecon\Uzivatel;

use Endroid\QrCode\Writer\Result\ResultInterface;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use Gamecon\Finance\SqlStruktura\SlevySqlStruktura;
use Gamecon\Objekt\ObnoveniVychozichHodnotTrait;
use Gamecon\Pravo;
use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\PlatbySqlStruktura;
use Cenik;

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */
class Finance
{
    use ObnoveniVychozichHodnotTrait;

    public const KLIC_ZRUS_NAKUP_POLOZKY = 'zrus-nakup-polozky';

    private         $stav       = 0;  // celkový výsledný stav uživatele na účtu
    private         $deltaPozde = 0;      // o kolik se zvýší platba při zaplacení pozdě
    private         $soucinitelCenyAKtivit;              // součinitel ceny aktivit
    private         $logovat    = true;    // ukládat seznam předmětů?
    private ?\Cenik $cenik      = null;             // instance ceníku
    // tabulky s přehledy
    private $prehled                        = [];   // tabulka s detaily o platbách
    private $strukturovanyPrehled           = [];
    private $polozkyProBfgr                 = [];
    private $slevyNaAktivity                = [];    // pole s textovými popisy slev uživatele na aktivity
    private $slevyO                         = [];    // pole s textovými popisy obecných slev
    private $proplacenyBonusZaVedeniAktivit = 0; // "sleva" za aktivity; nebo-li bonus vypravěče; nebo-li odměna za vedení hry; převedená na peníze
    private $brigadnickaOdmena              = 0.0;  // výplata zaměstnance (který nechce bonus/kredit na útratu; ale tvrdou měnu za tvrdou práci)
    // součásti výsledné ceny
    private $cenaAktivit                   = 0.0;  // cena aktivit
    private $sumaStorna                    = 0.0;  // suma storna za aktivity (je součástí ceny za aktivity)
    private $cenaUbytovani                 = 0.0;  // cena objednaného ubytování
    private $cenaPredmetu                  = 0.0;  // cena předmětů objednaných z shopu
    private $cenaStravy                    = 0.0;  // cena jídel objednaných z shopu
    private $cenaVstupne                   = 0.0;
    private $cenaVstupnePozde              = 0.0;
    private $bonusZaVedeniAktivit          = 0.0;  // sleva za tech. aktivity a odvedené aktivity
    private $slevaObecna                   = 0.0;  // sleva získaná z tabulky slev
    private $nevyuzityBonusZaVedeniAktivit = 0.0;  // zbývající sleva za odvedené aktivity (nevyužitá část)
    private $vyuzityBonusZaVedeniAktivit   = 0.0;  // sleva za odvedené aktivity (využitá část)
    private $zbyvajiciObecnaSleva          = 0.0;
    private $vyuzitaSlevaObecna            = 0.0;
    private $sumyPlatebVRocich             = [];  // platby připsané na účet v jednotlivých letech (zatím jen letos; protože máme obskurnost jménem "Uzavření ročníku")
    /** @var string|null */
    private $datumPosledniPlatby;        // datum poslední připsané platby

    private $kategorieNeplatice;
    private $dobrovolneVstupnePrehled;

    private static $maxSlevaAktivit = 100; // v procentech

    private const PORADI_NADPISU = 1;
    private const PORADI_POLOZKY = 2;
    // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    private const AKTIVITY        = -1;
    private const PREDMETY_STRAVA = 1;
    private const UBYTOVANI       = 2;
    // mezera na typy předmětů (1-4? viz db)
    private const VSTUPNE                    = 10;
    private const CELKOVA                    = 11;
    private const ZUSTATEK_Z_PREDCHOZICH_LET = 12;
    private const PRIPSANE_SLEVY             = 13;
    private const ORGSLEVA                   = 14;
    private const BRIGADNICKA_ODMENA         = 15;
    private const PLATBY_NADPIS              = 16;
    private const PLATBA                     = 17;
    private const VYSLEDNY                   = 18;

    /**
     * Vrátí výchozí vygenerovanou slevu za vedení dané aktivity
     * @param Aktivita @a
     * @return int
     */
    public static function bonusZaAktivitu(Aktivita $a, SystemoveNastaveni $systemoveNastaveni): int
    {
        if ($a->nedavaBonus()) {
            return 0;
        }
        $delka = $a->delka();
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
        return array_filter($organizatori, static function (\Uzivatel $organizator) {
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
     */
    public function __construct(
        private readonly \Uzivatel          $u,
        private readonly float              $zustatekZPredchozichRocniku,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    )
    {
        $this->prepocti();
    }

    private function prepocti()
    {
        $this->zapoctiVedeniAktivit();
        $this->zapoctiSlevy();

        $this->cenik = new \Cenik(
            $this->u,
            $this->bonusZaVedeniAktivit,
            $this->systemoveNastaveni
        ); // musí být načteno, i pokud není přihlášen na GC

        $this->zapoctiAktivity();
        $this->zapoctiShop();
        $this->zapoctiZustatekZPredchozichRocniku();

        $cena =
            $this->cenaPredmetu
            + $this->cenaStravy
            + $this->cenaUbytovani
            + $this->cenaAktivit;

        $cena = $cena
            + $this->cenaVstupne
            + $this->cenaVstupnePozde;

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

        $this->logb('Aktivity', $this->cenaAktivit, self::AKTIVITY,);
        $this->logb('Ubytování', $this->cenaUbytovani, self::UBYTOVANI);
        $this->logb('Předměty a strava', $this->cenaPredmetyAStrava(), self::PREDMETY_STRAVA);
        $this->logb('Připsané platby', $this->sumaPlateb(), self::PLATBA);
        $this->logb('Stav financí', $this->stav(), self::VYSLEDNY);
    }

    public function obnovUdaje()
    {
        $this->obnovVychoziHodnotyObjektu();
        $this->prepocti();

        return $this;
    }

    /** Cena za uživatelovy aktivity */
    public function cenaAktivit()
    {
        return $this->cenaAktivit;
    }

    public function cenaPredmetyAStrava()
    {
        return $this->cenaPredmetu() + $this->cenaStravy();
    }

    public function cenaPredmetu()
    {
        return $this->cenaPredmetu;
    }

    public function cenaStravy()
    {
        return $this->cenaStravy;
    }

    public function cenaUbytovani()
    {
        return $this->cenaUbytovani;
    }

    private static function cpm_kategorie_razeni(int $kategorie): int
    {
        return match ($kategorie) {
            2 => 4,
            3 => 2,
            4 => 3,
            default => $kategorie,
        };
    }

    /** Porovnávání k řazení php 4 style :/ */
    private function cmp($a, $b)
    {
        // podle typu
        $podleKategorii = Finance::cpm_kategorie_razeni((int)$a['kategorie']) - Finance::cpm_kategorie_razeni((int)$b['kategorie']);
        if ($podleKategorii !== 0) {
            return $podleKategorii;
        }
        $razeniVKategorii = $a['poradi_v_kategorii'] - $b['poradi_v_kategorii'];
        if ($razeniVKategorii !== 0) {
            return $razeniVKategorii;
        }
        // podle názvu
        $o = strcmp($a['nazev'], $b['nazev']);
        if ($o) {
            return $o;
        }
        // podle ceny
        return $a['castka'] - $b['castka'];
    }

    private function logPolozkaProBfgr(string $nazev, int $pocet, float $castka, int $typ)
    {
        if (!$this->logovat) {
            return;
        }
        $this->polozkyProBfgr[] = [
            'nazev'  => trim($nazev),
            'pocet'  => $pocet,
            'castka' => $castka,
            'typ'    => (int)$typ,
        ];
    }

    private function logStrukturovane(string $nazev, int $pocet, ?float $castka, $typ)
    {
        if (!$this->logovat) {
            return;
        }
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
        string                $nazev,
        null|string|float|int $castka,
        ?int                  $kategorie,
        int                   $poradiVKategorii = self::PORADI_POLOZKY,
        ?int                  $idPolozky = null,
    )
    {
        if (!$this->logovat) {
            return;
        }
        // přidání
        $this->prehled[] = $this->formatujProLog(
            $nazev,
            $castka,
            $kategorie,
            $poradiVKategorii,
            $idPolozky,
        );
    }

    private function formatujProLog(
        string                $nazev,
        null|string|float|int $castka,
        ?int                  $kategorie,
        int                   $poradiVKategorii,
        ?int                  $idPolozky = null,
    ): array
    {
        if (is_numeric($castka)) {
            $castka = self::zaokouhli($castka);
        }
        return [
            'nazev'              => $nazev,
            'castka'             => $castka,
            'kategorie'          => $kategorie,
            'poradi_v_kategorii' => $poradiVKategorii,
            'id_polozky'         => $idPolozky,
        ];
    }

    /**
     * Zaloguje zvýrazněný záznam
     */
    private function logb($nazev, $castka, int $kategorie, int $poradiNadpisu = self::PORADI_NADPISU, ?int $idPolozky = null)
    {
        $castka = self::zaokouhli($castka);
        $this->log("<b>$nazev</b>", "<b>$castka</b>", $kategorie, $poradiNadpisu, $idPolozky);
    }

    /**
     * Vrátí / nastaví datum posledního provedení platby
     *
     * @return string|null datum poslední platby
     */
    public function datumPosledniPlatby()
    {
        if (!isset($this->datumPosledniPlatby)) {
            $uid                       = $this->u->id();
            $this->datumPosledniPlatby = dbOneCol("
        SELECT max(provedeno) as datum
        FROM platby
        WHERE castka > 0 AND id_uzivatele = $1", [$uid],
            );
        }
        return $this->datumPosledniPlatby;
    }

    /**
     * Vrátí html formátovaný přehled financí
     * @param null|int[] $jenKategorieIds
     * @param boolean $vcetneCeny
     * @param boolean $vcetneMazani
     */
    public function prehledHtml(array $jenKategorieIds = null, bool $vcetneCeny = true, bool $vcetneMazani = false)
    {
        $out     = '<table class="objednavky">';
        $prehled = $this->serazenyPrehled();
        if ($jenKategorieIds) {
            if (in_array(TypPredmetu::VSTUPNE, $jenKategorieIds) && $this->dobrovolneVstupnePrehled) {
                $prehled[] = $this->dobrovolneVstupnePrehled;
            }
            $prehled = array_filter($prehled, static function ($radekPrehledu) use ($jenKategorieIds) {
                return in_array($radekPrehledu['kategorie'], $jenKategorieIds);
            });
            // Infopult nechce mikronadpisy, pokud je přehled omezen jen na pár kategorií
            $prehled = array_filter($prehled, static function ($radekPrehledu) {
                // našli jsme nadpis, jediný je tučně
                return strpos($radekPrehledu['nazev'], '<b>') === false;
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
                    $mazaniRow            = <<<HTML
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

    private function serazenyPrehled(): array
    {
        $prehled = $this->prehled;
        usort($prehled, [static::class, 'cmp']);
        return $prehled;
    }

    public function dejStrukturovanyPrehled(): array
    {
        return $this->strukturovanyPrehled;
    }

    public function dejPolozkyProBfgr(): array
    {
        return $this->polozkyProBfgr;
    }

    /**
     * Připíše aktuálnímu uživateli platbu ve výši $castka.
     * @param float|string $castka
     * @param \Uzivatel $provedl
     * @param string|null $poznamka
     * @param string|int|null $idFioPlatby
     * @param null|\DateTimeInterface $kdy
     * @throws \DbDuplicateEntryException
     */
    public function pripis(
        string|float|int    $castka,
        \Uzivatel           $provedl,
        ?string             $poznamka = null,
        string|int|null     $idFioPlatby = null,
        ?\DateTimeInterface $kdy = null,
    )
    {
        $rok = $kdy?->format('Y') ?? $this->systemoveNastaveni->rocnik();
        dbInsert(
            PlatbySqlStruktura::PLATBY_TABULKA,
            [
                PlatbySqlStruktura::ID_UZIVATELE => $this->u->id(),
                PlatbySqlStruktura::FIO_ID       => $idFioPlatby ?: null,
                PlatbySqlStruktura::CASTKA       => prevedNaFloat($castka),
                PlatbySqlStruktura::ROK          => $rok,
                PlatbySqlStruktura::PROVEDL      => $provedl->id(),
                PlatbySqlStruktura::POZNAMKA     => $poznamka ?: null,
                PlatbySqlStruktura::PROVEDENO    => $kdy?->format(DateTimeCz::FORMAT_DB),
            ],
        );
    }

    /**
     * Připíše aktuálnímu uživateli $u slevu ve výši $sleva
     * @param float $sleva
     * @param string|null $poznamka
     * @param \Uzivatel $provedl
     */
    public function pripisSlevu($sleva, $poznamka, \Uzivatel $provedl): float
    {
        $sleva = prevedNaFloat($sleva);
        dbQuery(
            'INSERT INTO slevy(id_uzivatele, castka, rok, provedl, poznamka) VALUES ($1, $2, $3, $4, $5)',
            [$this->u->id(), $sleva, ROCNIK, $provedl->id(), $poznamka ?: null],
        );
        return $sleva;
    }

    /** Vrátí aktuální stav na účtu uživatele pro tento rok */
    public function stav()
    {
        return $this->stav;
    }

    /** Vrátí výši obecné slevy připsané uživateli pro tento rok. */
    public function slevaObecna()
    {
        return $this->slevaObecna;
    }

    /** Vrátí člověkem čitelný stav účtu */
    public function stavHr(bool $vHtmlFormatu = true)
    {
        $mezera = $vHtmlFormatu
            ? '&thinsp;' // thin space
            : ' ';
        return $this->stav() . $mezera . 'Kč';
    }

    /**
     * Vrací součinitel ceny aktivit jako float číslo. Např. 0.0 pro aktivity
     * zdarma a 1.0 pro aktivity za plnou cenu.
     */
    public function slevaAktivity()
    {
        return $this->soucinitelAktivit(); //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
    }

    public function slevaZaAktivityVProcentech()
    {
        return 100 - ($this->soucinitelAktivit() * 100);
    }

    /**
     * Výše vypravěčské slevy (celková)
     */
    public function bonusZaVedeniAktivit(): float
    {
        return $this->bonusZaVedeniAktivit;
    }

    /**
     * Výše zbývající vypravěčské slevy
     */
    public function nevyuzityBonusZaAktivity(): float
    {
        return $this->nevyuzityBonusZaVedeniAktivit;
    }

    /**
     * Výše použitého bonusu za vypravěčství (vyčerpané vypravěčské slevy)
     */
    public function vyuzityBonusZaAktivity(): float
    {
        return $this->vyuzityBonusZaVedeniAktivit;
    }

    /**
     * @todo přesunout do ceníku (viz nutnost počítání součinitele aktivit)
     */
    public function slevyAktivity()
    {
        //return $this->cenik->slevyObecne();
        return $this->slevyNaAktivity;
    }

    public function maximalniPocetPlacekZdarma(): int
    {
        return $this->u->maPravo(Pravo::PLACKA_ZDARMA)
            ? 1
            : 0;
    }

    public function maximalniPocetKostekZdarma(): int
    {
        return $this->u->maPravo(Pravo::KOSTKA_ZDARMA)
            ? 1
            : 0;
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
            : 0;
    }

    public function maximalniPocetModrychTricekZdarma(): int
    {
        return $this->u->maPravo(Pravo::MODRE_TRICKO_ZDARMA) && $this->bonusZaVedeniAktivit() >= MODRE_TRICKO_ZDARMA_OD
            ? 1
            : 0;
    }

    public function muzeObjednavatModreTrickoSeSlevou(): bool
    {
        return $this->u->maPravo(Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA);
    }

    public function muzeObjednavatCerveneTrickoSeSlevou(): bool
    {
        return $this->u->maPravo(Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA);
    }

    /**
     * Viz ceník
     */
    public function slevyVse()
    {
        return $this->cenik->slevySpecialni();
    }

    /**
     * Vrátí součinitel ceny aktivit, tedy slevy uživatele vztahující se k
     * aktivitám. Vrátí hodnotu.
     */
    private function soucinitelAktivit()
    {
        if (!isset($this->soucinitelCenyAKtivit)) {
            // pomocné proměnné
            $sleva = 0; // v procentech
            // výpočet pravidel
            if ($this->u->maPravo(Pravo::AKTIVITY_ZDARMA)) {
                // sleva 100%
                $sleva                   += 100;
                $this->slevyNaAktivity[] = 'sleva 100%';
            } else if ($this->u->maPravo(Pravo::CASTECNA_SLEVA_NA_AKTIVITY)) {
                // sleva 40%
                $sleva                   += 40;
                $this->slevyNaAktivity[] = 'sleva 40%';
            }
            if ($sleva > self::$maxSlevaAktivit) {
                // omezení výše slevy na maximální hodnotu
                $sleva = self::$maxSlevaAktivit;
            }
            $slevaAktivity = (100 - $sleva) / 100;
            // výsledek
            $this->soucinitelCenyAKtivit = $slevaAktivity;
        }
        return $this->soucinitelCenyAKtivit;
    }

    public function vstupne()
    {
        return $this->cenaVstupne;
    }

    public function vstupnePozde()
    {
        return $this->cenaVstupnePozde;
    }

    public function proplacenyBonusZaAktivity(): float
    {
        return $this->proplacenyBonusZaVedeniAktivit;
    }

    public function brigadnickaOdmena(): float
    {
        return $this->brigadnickaOdmena;
    }

    /**
     * Započítá do mezisoučtů aktivity uživatele
     */
    private function zapoctiAktivity()
    {
        $soucinitelAktivit     = $this->soucinitelAktivit();
        $rok                   = ROCNIK;
        $idUcastnika           = $this->u->id();
        $technicka             = TypAktivity::TECHNICKA; // výpomoc, jejíž cena se započítá jako bonus vypravěče, který může použít na nákup na GC
        $brigadnicka           = TypAktivity::BRIGADNICKA; // placený "zaměstnanec"
        $prihlasenAleNedorazil = StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL;
        $pozdeZrusil           = StavPrihlaseni::POZDE_ZRUSIL;

        $o = dbQuery(<<<SQL
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
SQL,
        );

        $a = $this->u->koncovkaDlePohlavi();
        while ($r = mysqli_fetch_assoc($o)) {
            if ($r['typ'] == TypAktivity::TECHNICKA) {
                if ($this->u->maPravoNaBonusZaVedeniAktivit()) {
                    $this->bonusZaVedeniAktivit += $r['cena'];
                }
            } else if ($r['typ'] == TypAktivity::BRIGADNICKA) {
                if ($this->u->jeBrigadnik()) {
                    $this->brigadnickaOdmena += $r['cena'];
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
                $r['nazev'] . $poznamka,
                in_array($r['typ'], TypAktivity::interniTypy())
                    ? 0
                    : $r['cena'],
                self::AKTIVITY,
            );
        }
    }

    public function sumaPlateb(int $rok = ROCNIK): float
    {
        if (!isset($this->sumyPlatebVRocich[$rok])) {
            $uzivatelSystemId = \Uzivatel::SYSTEM;
            $result           = dbQuery(<<<SQL
                SELECT
                    IF(provedl=$uzivatelSystemId,
                      CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' Platba na účet'),
                      CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' ',IFNULL(poznamka,'(bez poznámky)'))
                      ) as nazev,
                    castka as cena
                FROM platby
                WHERE id_uzivatele = {$this->u->id()} AND rok = $rok
                SQL,
            );
            $sumaPlateb       = 0.0;
            while ($row = mysqli_fetch_assoc($result)) {
                $sumaPlateb += (float)$row['cena'];
                $this->log($row['nazev'], $row['cena'], self::PLATBA);
            }
            $this->sumyPlatebVRocich[$rok] = self::zaokouhli($sumaPlateb);
        }
        return $this->sumyPlatebVRocich[$rok];
    }

    /**
     * Započítá do mezisoučtů nákupy v eshopu
     */
    private function zapoctiShop()
    {
        $o = dbQuery('
      SELECT p.id_predmetu, p.nazev, n.cena_nakupni, p.typ, p.ubytovani_den, p.model_rok
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $0 AND n.rok = $1
      ORDER BY n.cena_nakupni -- od nejlevnějších kvůli aplikaci slev na trička
    ', [$this->u->id(), ROCNIK]);

        $soucty = [];
        foreach ($o as $r) {
            $cena = $this->cenik->shop($r);
            // započtení ceny
            if ($r['typ'] == Shop::UBYTOVANI) {
                $this->cenaUbytovani += $cena;
            } else if ($r['typ'] == Shop::VSTUPNE) {
                if (strpos($r['nazev'], 'pozdě') === false) {
                    $this->cenaVstupne = $cena;
                } else {
                    $this->cenaVstupnePozde = $cena;
                }
                $this->dobrovolneVstupnePrehled = $this->formatujProLog("{$r['nazev']} $cena.-", $cena, $r['typ'], $r['id_predmetu']);
            } else if ($r['typ'] == Shop::PROPLACENI_BONUSU) {
                $this->proplacenyBonusZaVedeniAktivit += $cena;
            } else {
                if ($r['typ'] == Shop::JIDLO) {
                    $this->cenaStravy += $cena;
                } else if (in_array($r['typ'], [Shop::PREDMET, Shop::TRICKO])) {
                    $this->cenaPredmetu += $cena;
                } else if ($r['typ'] != Shop::PARCON) {
                    throw new NeznamyTypPredmetu(
                        "Neznámý typ předmětu " . var_export($r['typ'], true) . ': ' . var_export($r, true)
                    );
                }
            }
            // přidání roku do názvu
            if ($r['model_rok'] && $r['model_rok'] != ROCNIK) {
                $r['nazev'] = $r['nazev'] . ' ' . $r['model_rok'];
            }

            $this->logPolozkaProBfgr((string)$r['nazev'], 1, $cena, (int)$r['typ']);

            // logování do výpisu
            if (in_array($r['typ'], [TypPredmetu::PREDMET, TypPredmetu::TRICKO])) {
                $soucty[$r['id_predmetu']]['nazev'] = $r['nazev'];
                $soucty[$r['id_predmetu']]['typ']   = $r['typ'];
                $soucty[$r['id_predmetu']]['pocet'] = ($soucty[$r['id_predmetu']]['pocet'] ?? 0) + 1;
                $soucty[$r['id_predmetu']]['suma']  = ($soucty[$r['id_predmetu']]['suma'] ?? 0) + $cena;
            } else if ($r['typ'] == Shop::VSTUPNE) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, self::VSTUPNE);
                $this->logb($r['nazev'], $cena, self::VSTUPNE);
            } else if ($r['typ'] != Shop::PROPLACENI_BONUSU) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, $r['typ']);
                $this->log($r['nazev'], $cena, $r['typ'] !== null ? (int)$r['typ'] : null);
            }
        }

        foreach ($soucty as $idPredmetu => $predmet) {
            $this->logStrukturovane((string)$predmet['nazev'], (int)$predmet['pocet'], (float)$predmet['suma'], $predmet['typ']);
            // dvojmezera kvůli řazení
            $this->log($predmet['nazev'] . '  ' . $predmet['pocet'] . '×', $predmet['suma'], (int)$predmet['typ'], $idPredmetu);
        }
    }

    /**
     * Započítá ručně zadané slevy z tabulky slev.
     */
    private function zapoctiSlevy()
    {
        $q = dbQuery('
      SELECT castka, poznamka
      FROM slevy
      WHERE id_uzivatele = $0 AND rok = $1
    ', [$this->u->id(), ROCNIK]);

        foreach ($q as $sleva) {
            if (strpos($sleva[SlevySqlStruktura::POZNAMKA], '#kompenzace') !== false) {
                // speciální typ slevy: kompenzace
                // započítává se stejně jako sleva za vedené aktivity
                $this->bonusZaVedeniAktivit += $sleva[SlevySqlStruktura::CASTKA];
            } else {
                // normální sleva
                // započítává se zvlášť
                $this->slevaObecna += $sleva[SlevySqlStruktura::CASTKA];
            }
        }
    }

    /**
     * Započítá do mezisoučtů slevy za organizované aktivity
     */
    private function zapoctiVedeniAktivit()
    {
        if (!$this->u->maPravoNaPoradaniAktivit()) {
            return;
        }
        if ($this->u->nemaPravoNaBonusZaVedeniAktivit()) {
            return;
        }
        foreach (Aktivita::zOrganizatora($this->u) as $a) {
            $this->bonusZaVedeniAktivit += self::bonusZaAktivitu($a, $this->systemoveNastaveni);
        }
    }

    /**
     * Započítá do mezisoučtů zůstatek z minulých let
     */
    private function zapoctiZustatekZPredchozichRocniku()
    {
        $this->log('Zůstatek z minulých let', $this->zustatekZPredchozichRocniku, self::ZUSTATEK_Z_PREDCHOZICH_LET);
    }

    private function aplikujBonusZaVedeniAktivit(float $cena): float
    {
        $bonusZaVedeniAktivit = $this->bonusZaVedeniAktivit;
        $puvodniCena          = $cena;
        ['sleva' => $this->nevyuzityBonusZaVedeniAktivit] = Cenik::aplikujSlevu(
            $puvodniCena,
            $bonusZaVedeniAktivit,
        );
        $this->vyuzityBonusZaVedeniAktivit = $this->bonusZaVedeniAktivit - $this->nevyuzityBonusZaVedeniAktivit;
        /** Do výsledné ceny, respektive celkového stavu, už započítáváme celý bonus za aktivity https://trello.com/c/8SWTdpYl/1069-zobrazen%C3%AD-financ%C3%AD-%C3%BA%C4%8Dastn%C3%ADka */
        $cena -= $this->bonusZaVedeniAktivit;

        if ($this->bonusZaVedeniAktivit) {
            $this->logb(
                "<span class='hinted'>Bonus za aktivity - celkový<span class='hint'>využitý {$this->vyuzityBonusZaVedeniAktivit}, proplacený {$this->proplacenyBonusZaVedeniAktivit}</span></span>",
                $this->bonusZaVedeniAktivit,
                self::ORGSLEVA,
            );
        }

        return $cena;
    }

    private function aplikujBrigadnickouOdmenu(float $cena)
    {
        if ($this->brigadnickaOdmena) {
            $this->logb(
                'Brigádnická odměna',
                $this->brigadnickaOdmena,
                self::BRIGADNICKA_ODMENA,
            );
        }
        return $cena - $this->brigadnickaOdmena;
    }

    private function aplikujObecnouSlevu(float $cena)
    {
        $slevaObecna = $this->slevaObecna;
        ['cena' => $cena, 'sleva' => $this->zbyvajiciObecnaSleva] = \Cenik::aplikujSlevu($cena, $slevaObecna);
        $this->vyuzitaSlevaObecna = $this->slevaObecna - $this->zbyvajiciObecnaSleva;
        if ($this->slevaObecna) {
            $this->log(
                '<b>Sleva</b>',
                '<b>' . $this->slevaObecna . '</b>',
                self::PRIPSANE_SLEVY,
            );
            $this->log(
                '<i>Využitá sleva ' . $this->vyuzitaSlevaObecna . '</i>',
                '&nbsp;',
                self::PRIPSANE_SLEVY,
            );
        }

        return $cena;
    }

    /**
     * @return int zůstatek na účtu z minulých GC
     */
    public function zustatekZPredchozichRocniku(): float
    {
        return $this->zustatekZPredchozichRocniku;
    }

    public function kategorieNeplatice(): KategorieNeplatice
    {
        if (!$this->kategorieNeplatice) {
            $this->kategorieNeplatice = KategorieNeplatice::vytvorProNadchazejiciVlnuZGlobals($this->u);
        }
        return $this->kategorieNeplatice;
    }

    public function dejQrKodProPlatbu(): ResultInterface
    {
        $castkaCzk = $this->stav() >= 0
            ? 0.1 // nulová, respektive dobrovolná platba
            : -$this->stav();

        $qrPlatba = $this->u->stat() === \Gamecon\Stat::CZ
            ? \Gamecon\Finance\QrPlatba::dejQrProTuzemskouPlatbu(
                $castkaCzk,
                $this->u->id(),
            )
            : \Gamecon\Finance\QrPlatba::dejQrProSepaPlatbu(
                $castkaCzk, // SEPA platba je vždy v Eur se splatností do druhého dne
                $this->u->id(),
            );

        return $qrPlatba->dejQrObrazek();
    }

    public function sumaStorna(): float
    {
        return $this->sumaStorna;
    }
}
