<?php

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;
use Endroid\QrCode\Writer\Result\ResultInterface;

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */
class Finance
{

    public const KLIC_ZRUS_NAKUP_POLOZKY = 'zrus-nakup-polozky';

    /** @var \Uzivatel */
    private $u; // uživatel, jehož finance se počítají
    private $stav = 0;  // celkový výsledný stav uživatele na účtu
    private $deltaPozde = 0;      // o kolik se zvýší platba při zaplacení pozdě
    private $soucinitelCenyAKtivit;              // součinitel ceny aktivit
    private $logovat = true;    // ukládat seznam předmětů?
    private $cenik;             // instance ceníku
    // tabulky s přehledy
    private $prehled = [];   // tabulka s detaily o platbách
    private $strukturovanyPrehled = [];
    private $slevyNaAktivity = [];    // pole s textovými popisy slev uživatele na aktivity
    private $slevyO = [];    // pole s textovými popisy obecných slev
    private $proplacenyBonusZaVedeniAktivit = 0; // "sleva" za aktivity; nebo-li bonus vypravěče; nebo-li odměna za vedení hry; převedená na peníze
    private $brigadnickaOdmena = 0.0;  // výplata zaměstnance (který nechce bonus/kredit na útratu; ale tvrdou měnu za tvrdou práci)
    // součásti výsledné ceny
    private $cenaAktivit = 0.0;  // cena aktivit
    private $sumaStorna = 0.0;  // suma storna za aktivity (je součástí ceny za aktivity)
    private $cenaUbytovani = 0.0;  // cena objednaného ubytování
    private $cenaPredmetu = 0.0;  // cena předmětů objednaných z shopu
    private $cenaStravy = 0.0;  // cena jídel objednaných z shopu
    private $cenaVstupne = 0.0;
    private $cenaVstupnePozde = 0.0;
    private $bonusZaVedeniAktivit = 0.0;  // sleva za tech. aktivity a odvedené aktivity
    private $slevaObecna = 0.0;  // sleva získaná z tabulky slev
    private $nevyuzityBonusZaVedeniAktivit = 0.0;  // zbývající sleva za odvedené aktivity (nevyužitá část)
    private $vyuzityBonusZaVedenAktivit = 0.0;  // sleva za odvedené aktivity (využitá část)
    private $zbyvajiciObecnaSleva = 0.0;
    private $vyuzitaSlevaObecna = 0.0;
    private $zustatekZPredchozichRocniku = 0;    // zůstatek z minula
    private $sumyPlatebVRocich = [];  // platby připsané na účet v jednotlivých letech (zatím jen letos; protože máme obskurnost jménem "Uzavření ročníku")
    /** @var string|null */
    private $datumPosledniPlatby;        // datum poslední připsané platby

    private $kategorieNeplatice;
    private $dobrovolneVstupnePrehled;

    private static $maxSlevaAktivit = 100; // v procentech
    private static $bonusZaVedeniAktivity = [ // ve formátu max. délka => sleva
        1  => BONUS_ZA_1H_AKTIVITU,
        2  => BONUS_ZA_2H_AKTIVITU,
        5  => BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU,
        7  => BONUS_ZA_6H_AZ_7H_AKTIVITU,
        9  => BONUS_ZA_8H_AZ_9H_AKTIVITU,
        11 => BONUS_ZA_10H_AZ_11H_AKTIVITU,
        13 => BONUS_ZA_12H_AZ_13H_AKTIVITU,
    ];

    // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    public const AKTIVITY        = -1;
    public const PREDMETY_STRAVA = 1;
    public const UBYTOVANI       = 2;
    // mezera na typy předmětů (1-4? viz db)
    public const BRIGADNICKA_ODMENA         = 10;
    public const ORGSLEVA                   = 11;
    public const PRIPSANE_SLEVY             = 12;
    public const VSTUPNE                    = 13;
    public const CELKOVA                    = 14;
    public const PLATBY_NADPIS              = 15;
    public const ZUSTATEK_Z_PREDCHOZICH_LET = 16;
    public const PLATBA                     = 17;
    public const VYSLEDNY                   = 18;

    /**
     * @param \Uzivatel $u uživatel, pro kterého se finance sestavují
     * @param float $zustatek zůstatek na účtu z minulých GC
     */
    public function __construct(\Uzivatel $u, float $zustatek) {
        $this->u                           = $u;
        $this->zustatekZPredchozichRocniku = $zustatek;

        $this->zapoctiVedeniAktivit();
        $this->zapoctiSlevy();

        $this->cenik = new \Cenik($u, $this->bonusZaVedeniAktivit); // musí být načteno, i pokud není přihlášen na GC

        $this->zapoctiAktivity();
        $this->zapoctiShop();
        $this->zapoctiZustatekZPredchozichRocniku();

        $cena =
            $this->cenaPredmetu
            + $this->cenaStravy
            + $this->cenaUbytovani
            + $this->cenaAktivit;

        $cena = $this->aplikujBonusZaVedeniAktivit($cena);
        $cena = $this->aplikujBrigadnickouOdmenu($cena);
        $cena = $this->aplikujSlevy($cena);

        $cena = $cena
            + $this->cenaVstupne
            + $this->cenaVstupnePozde;

        $this->logb('Celková cena', $cena, self::CELKOVA);

        $this->stav = round(
            -$cena
            + $this->sumaPlateb()
            + $this->zustatekZPredchozichRocniku, 2);

        $this->logb('Aktivity', $this->cenaAktivit, self::AKTIVITY);
        $this->logb('Ubytování', $this->cenaUbytovani, self::UBYTOVANI);
        $this->logb('Předměty a strava', $this->cenaPredmetyAStrava(), self::PREDMETY_STRAVA);
        $this->logb('Připsané platby', $this->sumaPlateb() + $this->zustatekZPredchozichRocniku, self::PLATBY_NADPIS);
        $this->logb('Stav financí', $this->stav(), self::VYSLEDNY);
    }

    /** Cena za uživatelovy aktivity */
    public function cenaAktivit() {
        return $this->cenaAktivit;
    }

    public function cenaPredmetyAStrava() {
        return $this->cenaPredmetu() + $this->cenaStravy();
    }

    public function cenaPredmetu() {
        return $this->cenaPredmetu;
    }

    public function cenaStravy() {
        return $this->cenaStravy;
    }

    public function cenaUbytovani() {
        return $this->cenaUbytovani;
    }

    private static function cpm_kategorie_razeni($kategorie) {
        switch ($kategorie) {
            case 2:
                return 4;
            case 3:
                return 2;
            case 4:
                return 3;
            default:
                return $kategorie;
        }
    }

    /** Porovnávání k řazení php 4 style :/ */
    private function cmp($a, $b) {
        // podle typu
        $m = Finance::cpm_kategorie_razeni($a['kategorie']) - Finance::cpm_kategorie_razeni($b['kategorie']);
        if ($m) {
            return $m;
        }
        // podle názvu
        $o = strcmp($a['nazev'], $b['nazev']);
        if ($o) {
            return $o;
        }
        // podle ceny
        return $a['castka'] - $b['castka'];
    }

    private function logStrukturovane(string $nazev, int $pocet, ?float $castka, $typ) {
        if (!$this->logovat) {
            return;
        }
        $this->strukturovanyPrehled[] = [
            'nazev'  => trim($nazev),
            'pocet'  => $pocet,
            'castka' => $castka,
            'typ'    => $typ,
        ];
    }

    /**
     * Zaloguje do seznamu nákupů položku (pokud je logování zapnuto)
     */
    private function log($nazev, $castka, $kategorie = null, $idPolozky = null) {
        if (!$this->logovat) {
            return;
        }
        // přidání
        $this->prehled[] = $this->formatujProLog($nazev, $castka, $kategorie, $idPolozky);
    }

    private function formatujProLog($nazev, $castka, $kategorie = null, $idPolozky = null): array {
        if (is_numeric($castka)) {
            $castka = round($castka);
        }
        return [
            'nazev'      => $nazev,
            'castka'     => $castka,
            'kategorie'  => $kategorie,
            'id_polozky' => $idPolozky,
        ];
    }

    /**
     * Zaloguje zvýrazněný záznam
     */
    private function logb($nazev, $castka, $kategorie = null, $idPolozky = null) {
        $this->log("<b>$nazev</b>", "<b>$castka</b>", $kategorie, $idPolozky);
    }

    /** Vrátí sumu plateb (připsaných peněz) */
    public function platby() {
        return $this->platby;
    }

    /**
     * Vrátí / nastaví datum posledního provedení platby
     *
     * @return string|null datum poslední platby
     */
    public function datumPosledniPlatby() {
        if (!isset($this->datumPosledniPlatby)) {
            $uid                       = $this->u->id();
            $this->datumPosledniPlatby = dbOneCol("
        SELECT max(provedeno) as datum
        FROM platby
        WHERE castka > 0 AND id_uzivatele = $1", [$uid]
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
    public function prehledHtml(array $jenKategorieIds = null, bool $vcetneCeny = true, bool $vcetneMazani = false) {
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

    public function prehledPopis(): string {
        $out = [];
        foreach ($this->serazenyPrehled() as $r) {
            $out[] = $r['nazev'] . ' ' . $r['castka'];
        }
        return implode(', ', $out);
    }

    private function serazenyPrehled(): array {
        $prehled = $this->prehled;
        usort($prehled, [static::class, 'cmp']);
        return $prehled;
    }

    public function dejStrukturovanyPrehled(): array {
        return $this->strukturovanyPrehled;
    }

    /**
     * Připíše aktuálnímu uživateli platbu ve výši $castka.
     * @param float $castka
     * @param \Uzivatel $provedl
     * @param string|null $poznamka
     * @param string|int|null $idFioPlatby
     * @throws \DbDuplicateEntryException
     */
    public function pripis($castka, \Uzivatel $provedl, $poznamka = null, $idFioPlatby = null) {
        dbInsert(
            'platby',
            [
                'id_uzivatele' => $this->u->id(),
                'fio_id'       => $idFioPlatby ?: null,
                'castka'       => $castka,
                'rok'          => ROK,
                'provedl'      => $provedl->id(),
                'poznamka'     => $poznamka ?: null,
            ]
        );
    }

    /**
     * Připíše aktuálnímu uživateli $u slevu ve výši $sleva
     * @param float $sleva
     * @param string|null $poznamka
     * @param \Uzivatel $provedl
     */
    public function pripisSlevu($sleva, $poznamka, \Uzivatel $provedl) {
        dbQuery(
            'INSERT INTO slevy(id_uzivatele, castka, rok, provedl, poznamka) VALUES ($1, $2, $3, $4, $5)',
            [$this->u->id(), $sleva, ROK, $provedl->id(), $poznamka ?: null]
        );
    }

    /** Vrátí aktuální stav na účtu uživatele pro tento rok */
    public function stav() {
        return $this->stav;
    }

    /** Vrátí výši obecné slevy připsané uživateli pro tento rok. */
    public function slevaObecna() {
        return $this->slevaObecna;
    }

    /** Vrátí člověkem čitelný stav účtu */
    public function stavHr(bool $vHtmlFormatu = true) {
        $mezera = $vHtmlFormatu
            ? '&thinsp;' // thin space
            : ' ';
        return $this->stav() . $mezera . 'Kč';
    }

    /**
     * Vrací součinitel ceny aktivit jako float číslo. Např. 0.0 pro aktivity
     * zdarma a 1.0 pro aktivity za plnou cenu.
     */
    public function slevaAktivity() {
        return $this->soucinitelAktivit(); //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
    }

    public function slevaZaAktivityVProcentech() {
        return 100 - ($this->soucinitelAktivit() * 100);
    }

    /**
     * Vrátí výchozí vygenerovanou slevu za vedení dané aktivity
     * @param Aktivita @a
     * @return int
     */
    static function bonusZaAktivitu(Aktivita $a): int {
        if ($a->nedavaBonus()) {
            return 0;
        }
        $delka = $a->delka();
        if ($delka == 0) {
            return 0;
        }
        foreach (self::$bonusZaVedeniAktivity as $tabDelka => $tabSleva) {
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
    public static function nechOrganizatorySBonusemZaVedeniAktivit(array $organizatori): array {
        return array_filter($organizatori, static function (\Uzivatel $organizator) {
            return $organizator->maPravoNaPoradaniAktivit()
                && $organizator->maPravoNaBonusZaVedeniAktivit();
        });
    }

    /**
     * Výše vypravěčské slevy (celková)
     */
    public function bonusZaVedeniAktivit(): float {
        return $this->bonusZaVedeniAktivit;
    }

    /**
     * Výše zbývající vypravěčské slevy
     */
    public function nevyuzityBonusZaAktivity(): float {
        return $this->nevyuzityBonusZaVedeniAktivit;
    }

    /**
     * Výše použitého bonusu za vypravěčství (vyčerpané vypravěčské slevy)
     */
    public function vyuzityBonusZaAktivity(): float {
        return $this->vyuzityBonusZaVedenAktivit;
    }

    /**
     * @todo přesunout do ceníku (viz nutnost počítání součinitele aktivit)
     */
    public function slevyAktivity() {
        //return $this->cenik->slevyObecne();
        return $this->slevyNaAktivity;
    }

    public function maximalniPocetPlacekZdarma(): int {
        return $this->u->maPravo(P_PLACKA_ZDARMA)
            ? 1
            : 0;
    }

    public function maximalniPocetKostekZdarma(): int {
        return $this->u->maPravo(P_KOSTKA_ZDARMA)
            ? 1
            : 0;
    }

    /**
     * Modré tričko zdarma je ještě navíc k tomuto. Takže totální maximální počet triček zdarma je
     * @see maximalniPocetLibovolnychTricekZdarmaBezModrychZdarma
     * + @see maximalniPocetModrychTricekZdarma
     */
    public function maximalniPocetLibovolnychTricekZdarmaBezModrychZdarma(): int {
        return $this->u->maPravo(P_DVE_TRICKA_ZDARMA)
            ? 2
            : 0;
    }

    public function maximalniPocetModrychTricekZdarma(): int {
        return $this->u->maPravo(P_TRICKO_ZA_SLEVU_MODRE) && $this->bonusZaVedeniAktivit() >= MODRE_TRICKO_ZDARMA_OD
            ? 1
            : 0;
    }

    public function muzeObjednavatModreTrickoSeSlevou(): bool {
        return $this->u->maPravo(P_TRICKO_MODRA_BARVA);
    }

    public function muzeObjednavatCerveneTrickoSeSlevou(): bool {
        return $this->u->maPravo(P_TRICKO_CERVENA_BARVA);
    }

    /**
     * Viz ceník
     */
    public function slevyVse() {
        return $this->cenik->slevySpecialni();
    }

    /**
     * Vrátí součinitel ceny aktivit, tedy slevy uživatele vztahující se k
     * aktivitám. Vrátí hodnotu.
     */
    private function soucinitelAktivit() {
        if (!isset($this->soucinitelCenyAKtivit)) {
            // pomocné proměnné
            $sleva = 0; // v procentech
            // výpočet pravidel
            if ($this->u->maPravo(P_AKTIVITY_ZDARMA)) {
                // sleva 100%
                $sleva                   += 100;
                $this->slevyNaAktivity[] = 'sleva 100%';
            } elseif ($this->u->maPravo(P_AKTIVITY_SLEVA)) {
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

    public function vstupne() {
        return $this->cenaVstupne;
    }

    public function vstupnePozde() {
        return $this->cenaVstupnePozde;
    }

    public function proplacenyBonusZaAktivity(): float {
        return $this->proplacenyBonusZaVedeniAktivit;
    }

    /**
     * Započítá do mezisoučtů aktivity uživatele
     */
    private function zapoctiAktivity() {
        $soucinitelAktivit     = $this->soucinitelAktivit();
        $rok                   = ROK;
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
SQL
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
                self::AKTIVITY
            );
        }
    }

    public function sumaPlateb(int $rok = ROK): float {
        if (!isset($this->sumyPlatebVRocich[$rok])) {
            $result     = dbQuery(<<<SQL
SELECT
    IF(provedl=1,
      CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' Platba na účet'),
      CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' ',IFNULL(poznamka,'(bez poznámky)'))
      ) as nazev,
    castka as cena
FROM platby
WHERE id_uzivatele = $1 AND rok = $2
SQL
                , [$this->u->id(), $rok]);
            $sumaPlateb = 0.0;
            while ($row = mysqli_fetch_assoc($result)) {
                $sumaPlateb += (float)$row['cena'];
                $this->log($row['nazev'], $row['cena'], self::PLATBA);
            }
            $this->sumyPlatebVRocich[$rok] = round($sumaPlateb, 2);
        }
        return $this->sumyPlatebVRocich[$rok];
    }

    /**
     * Započítá do mezisoučtů nákupy v eshopu
     */
    private function zapoctiShop() {
        $o = dbQuery('
      SELECT p.id_predmetu, p.nazev, n.cena_nakupni, p.typ, p.ubytovani_den, p.model_rok
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $0 AND n.rok = $1
      ORDER BY n.cena_nakupni -- od nejlevnějších kvůli aplikaci slev na trička
    ', [$this->u->id(), ROK]);

        $soucty = [];
        foreach ($o as $r) {
            $cena = $this->cenik->shop($r);
            // započtení ceny
            if ($r['typ'] == Shop::UBYTOVANI) {
                $this->cenaUbytovani += $cena;
            } elseif ($r['typ'] == Shop::VSTUPNE) {
                if (strpos($r['nazev'], 'pozdě') === false) {
                    $this->cenaVstupne = $cena;
                } else {
                    $this->cenaVstupnePozde = $cena;
                }
                $this->dobrovolneVstupnePrehled = $this->formatujProLog("{$r['nazev']} $cena.-", $cena, $r['typ'], $r['id_predmetu']);
            } else {
                if ($r['typ'] == Shop::JIDLO) {
                    $this->cenaStravy += $cena;
                } else {
                    $this->cenaPredmetu = $cena;
                }
            }
            // přidání roku do názvu
            if ($r['model_rok'] && $r['model_rok'] != ROK) {
                $r['nazev'] = $r['nazev'] . ' ' . $r['model_rok'];
            }
            // logování do výpisu
            if (in_array($r['typ'], [TypPredmetu::PREDMET, TypPredmetu::TRICKO])) {
                $soucty[$r['id_predmetu']]['nazev'] = $r['nazev'];
                $soucty[$r['id_predmetu']]['typ']   = $r['typ'];
                $soucty[$r['id_predmetu']]['pocet'] = ($soucty[$r['id_predmetu']]['pocet'] ?? 0) + 1;
                $soucty[$r['id_predmetu']]['suma']  = ($soucty[$r['id_predmetu']]['suma'] ?? 0) + $cena;
            } elseif ($r['typ'] == Shop::VSTUPNE) {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, self::VSTUPNE);
                $this->logb($r['nazev'], $cena, self::VSTUPNE);
            } elseif ($r['typ'] == Shop::PROPLACENI_BONUSU) {
                $this->proplacenyBonusZaVedeniAktivit += $cena;
            } else {
                $this->logStrukturovane((string)$r['nazev'], 1, $cena, $r['typ']);
                $this->log($r['nazev'], $cena, $r['typ']);
            }
        }

        foreach ($soucty as $idPredmetu => $predmet) {
            $this->logStrukturovane((string)$predmet['nazev'], (int)$predmet['pocet'], (float)$predmet['suma'], $predmet['typ']);
            // dvojmezera kvůli řazení
            $this->log($predmet['nazev'] . '  ' . $predmet['pocet'] . '×', $predmet['suma'], $predmet['typ'], $idPredmetu);
        }
    }

    /**
     * Započítá ručně zadané slevy z tabulky slev.
     */
    private function zapoctiSlevy() {
        $q = dbQuery('
      SELECT castka, poznamka
      FROM slevy
      WHERE id_uzivatele = $0 AND rok = $1
    ', [$this->u->id(), ROK]);

        foreach ($q as $sleva) {
            if (strpos($sleva['poznamka'], '#kompenzace') !== false) {
                // speciální typ slevy: kompenzace
                // započítává se stejně jako sleva za vedené aktivity
                $this->bonusZaVedeniAktivit += $sleva['castka'];
            } else {
                // normální sleva
                // započítává se zvlášť
                $this->slevaObecna += $sleva['castka'];
            }
        }
    }

    /**
     * Započítá do mezisoučtů slevy za organizované aktivity
     */
    private function zapoctiVedeniAktivit() {
        if (!$this->u->maPravoNaPoradaniAktivit()) {
            return;
        }
        if ($this->u->nemaPravoNaBonusZaVedeniAktivit()) {
            return;
        }
        foreach (Aktivita::zOrganizatora($this->u) as $a) {
            $this->bonusZaVedeniAktivit += self::bonusZaAktivitu($a);
        }
    }

    /**
     * Započítá do mezisoučtů zůstatek z minulých let
     */
    private function zapoctiZustatekZPredchozichRocniku() {
        $this->log('Zůstatek z minulých let', $this->zustatekZPredchozichRocniku, self::ZUSTATEK_Z_PREDCHOZICH_LET);
    }

    private function aplikujBonusZaVedeniAktivit(float $cena): float {
        $bonusZaVedeniAktivit = $this->bonusZaVedeniAktivit;
        ['cena' => $cena, 'sleva' => $this->nevyuzityBonusZaVedeniAktivit] = \Cenik::aplikujSlevu($cena, $bonusZaVedeniAktivit);
        $this->vyuzityBonusZaVedenAktivit = $this->bonusZaVedeniAktivit - $this->nevyuzityBonusZaVedeniAktivit;
        if ($this->bonusZaVedeniAktivit) {
            $this->logb(
                'Bonus za aktivity - využitý',
                $this->vyuzityBonusZaVedenAktivit,
                self::ORGSLEVA
            );
            $this->log(
                '<i>(z toho proplacený bonus ' . $this->proplacenyBonusZaVedeniAktivit . ')</i>',
                '&nbsp;',
                self::ORGSLEVA
            );
            $this->log(
                '<i>Bonus za aktivity - celkový ' . $this->bonusZaVedeniAktivit . '</i>',
                '&nbsp;',
                self::ORGSLEVA
            );
        }

        return $cena;
    }

    private function aplikujBrigadnickouOdmenu(float $cena) {
        if ($this->brigadnickaOdmena) {
            $this->logb(
                'Brigádnická odměna',
                $this->brigadnickaOdmena,
                self::BRIGADNICKA_ODMENA
            );
        }
        return $cena; // žádná změna, peníze chceme vyplácet
    }

    private function aplikujSlevy(float $cena) {
        $slevaObecna = $this->slevaObecna;
        ['cena' => $cena, 'sleva' => $this->zbyvajiciObecnaSleva] = \Cenik::aplikujSlevu($cena, $slevaObecna);
        $this->vyuzitaSlevaObecna = $this->slevaObecna - $this->zbyvajiciObecnaSleva;
        if ($this->slevaObecna) {
            $this->log(
                '<b>Sleva</b>',
                '<b>' . $this->slevaObecna . '</b>',
                self::PRIPSANE_SLEVY
            );
            $this->log(
                '<i>Využitá sleva ' . $this->vyuzitaSlevaObecna . '</i>',
                '&nbsp;',
                self::PRIPSANE_SLEVY
            );
        }

        return $cena;
    }

    /**
     * @return int zůstatek na účtu z minulých GC
     */
    public function zustatekZPredchozichRocniku(): float {
        return $this->zustatekZPredchozichRocniku;
    }

    public function kategorieNeplatice(): KategorieNeplatice {
        if (!$this->kategorieNeplatice) {
            $this->kategorieNeplatice = KategorieNeplatice::vytvorProNadchazejiciVlnuZGlobals($this->u);
        }
        return $this->kategorieNeplatice;
    }

    public function dejQrKodProPlatbu(): ResultInterface {
        $castkaCzk = $this->stav() >= 0
            ? 0.1 // nulová, respektive dobrovolná platba
            : -$this->stav();

        $qrPlatba = $this->u->stat() === \Gamecon\Stat::CZ
            ? \Gamecon\Finance\QrPlatba::dejQrProTuzemskouPlatbu(
                $castkaCzk,
                $this->u->id()
            )
            : \Gamecon\Finance\QrPlatba::dejQrProSepaPlatbu(
                $castkaCzk, // SEPA platba je vždy v Eur se splatností do druhého dne
                $this->u->id()
            );

        return $qrPlatba->dejQrObrazek();
    }

    public function sumaStorna(): float {
        return $this->sumaStorna;
    }
}
