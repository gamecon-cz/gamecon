<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;

/**
 * Zrychlený výpis programu
 */
class Program
{

    public const DRD_PJ      = 'drd_pj';
    public const DRD_PRIHLAS = 'drd_prihlas';
    public const PLUS_MINUS  = 'plus_minus';
    public const OSOBNI      = 'osobni';
    public const TABLE_CLASS = 'table_class';
    public const TEAM_VYBER  = 'team_vyber';
    public const INTERNI     = 'interni';
    public const SKUPINY     = 'skupiny';
    public const PRAZDNE     = 'prazdne';
    public const ZPETNE      = 'zpetne';
    public const DEN         = 'den';

    public const SKUPINY_LINIE     = 'linie';
    public const SKUPINY_MISTNOSTI = 'mistnosti';

    /** @var Uzivatel|null */
    private $u = null; // aktuální uživatel v objektu
    private $posledniVydana = null;
    private $dbPosledni = null;
    private $aktFronta = [];
    private $program; // iterátor aktivit seřazených pro použití v programu
    private $nastaveni = [
        self::DRD_PJ      => false, // u DrD explicitně zobrazit jména PJů
        self::DRD_PRIHLAS => false, // jestli se zobrazují přihlašovátka pro DrD
        self::PLUS_MINUS  => false, // jestli jsou v programu '+' a '-' pro změnu kapacity team. aktivit
        self::OSOBNI      => false, // jestli se zobrazuje osobní program (jinak se zobrazuje full)
        self::TABLE_CLASS => 'program', //todo edit
        self::TEAM_VYBER  => true, // jestli se u teamové aktivity zobrazí full výběr teamu přímo v programu
        self::INTERNI     => false, // jestli jdou vidět i skryté technické a brigádnické aktivity
        self::SKUPINY     => self::SKUPINY_LINIE, // seskupování programu - po místnostech nebo po liniích
        self::PRAZDNE     => false, // zobrazovat prázdné skupiny?
        self::ZPETNE      => false, // jestli smí měnit přihlášení zpětně
        self::DEN         => null,  // zobrazit jen konkrétní den
    ];
    private $grpf; // název metody na objektu aktivita, podle které se shlukuje
    private $skupiny; // pole skupin, do kterých se shlukuje program, ve stylu id => název

    private $aktivityUzivatele = []; // aktivity uživatele
    private $maxPocetAktivit = []; // maximální počet souběžných aktivit v daném dni

    private const SKUPINY_PODLE_LOKACE_ID  = 'lokaceId';
    private const SKUPINY_PODLE_DEN        = 'den';
    private const SKUPINY_PODLE_TYP_ID     = 'typId';
    private const SKUPINY_PODLE_TYP_PORADI = 'typPoradi';

    /**
     * Konstruktor bere uživatele a specifikaci, jestli je to osobní program
     */
    public function __construct(Uzivatel $u = null, $nastaveni = null) {
        if ($u instanceof Uzivatel) {
            $this->u   = $u;
            $this->uid = $this->u->id();
        }
        if (is_array($nastaveni)) {
            $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
        }

        if ($this->nastaveni[self::OSOBNI]) {
            $this->nastaveni[self::PRAZDNE] = true;
        }
    }

    /**
     * @return string[] urls k stylu programu
     */
    public function cssUrls(): array {
        $soubory = [
            __DIR__ . '/../web/soubory/blackarrow/_spolecne/hint.css',
            __DIR__ . '/../web/soubory/blackarrow/program/program-trida.css',
        ];
        $cssUrls = [];
        foreach ($soubory as $soubor) {
            $verze     = md5_file($soubor);
            $url       = str_replace(__DIR__ . '/../web/', '', $soubor);
            $cssUrls[] = URL_WEBU . '/' . $url . '?version=' . $verze;
        }
        return $cssUrls;
    }

    /**
     * Příprava pro tisk programu
     */
    public function tiskToPrint() {
        $this->init();

        require_once __DIR__ . '/../vendor/setasign/tfpdf/tfpdf.php';
        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
        $pdf->SetFont('DejaVu', '', 20);
        $pdf->Cell(0, 10, "Můj program (" . $this->u->nickNeboKrestniJmeno() . ")", 0, 1, 'L');
        $pdf->SetFillColor(202, 204, 206);
        $pdf->SetFont('DejaVu', '', 12);

        for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
            $denId = (int)$den->format('z');
            $this->nactiAktivityDne($denId);

            if ((count($this->aktivityUzivatele) > 0)) {
                $pocetPrihlasenychAktivit = 0;
                foreach ($this->aktivityUzivatele as $key => $akt) {
                    if ($akt['obj']->prihlasen($this->u)) {
                        $pocetPrihlasenychAktivit += 1;
                    }
                }

                if ($pocetPrihlasenychAktivit > 0) {
                    $pdf->Cell(0, 10, mb_ucfirst($den->format('l j.n.Y')), 1, 1, 'L', true);
                    for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++) {

                        foreach ($this->aktivityUzivatele as $key => $akt) {

                            if ($akt && $denId == $akt['den'] && $cas == $akt['zac']) {
                                $start = $cas;
                                $konec = $cas + $akt['del'];

                                if ($this->u->prihlasenJakoSledujici($akt['obj']) ||
                                    $akt['obj']->prihlasen($this->u) || $this->u->organizuje($akt['obj'])) {

                                    $pdf->Cell(30, 10, $start . ":00 - " . $konec . ":00", 1);
                                    if ($this->u->prihlasenJakoSledujici($akt['obj'])) {
                                        $pdf->Cell(100, 10, "(n) " . $akt['obj']->nazev(), 1);
                                    } else if ($akt['obj']->prihlasen($this->u)) {
                                        $pdf->Cell(100, 10, $akt['obj']->nazev(), 1);
                                    } else if ($this->u->organizuje($akt['obj'])) {
                                        $pdf->Cell(100, 10, "(o) " . $akt['obj']->nazev(), 1);
                                    }
                                    $pdf->Cell(60, 10, mb_ucfirst($akt['obj']->typ()->nazev()), 1, 1);
                                }
                            }
                        }
                    }
                }
            }

            $pdf->Cell(0, 1, "", 0, 1);
        }

        $pdf->Output();
    }

    /**
     * Přímý tisk programu na výstup
     */
    public function tisk() {
        $this->init();

        $aktivita = $this->dalsiAktivita();
        if ($this->nastaveni[self::OSOBNI] || $this->nastaveni[self::DEN]) {
            $this->tiskTabulky($aktivita);
        } else {
            foreach ($this->dny() as $den) {
                $datum = mb_ucfirst($den->format('l j.n.Y'));
                echo "<h2>$datum</h2>";
                $this->tiskTabulky($aktivita, $den->format('z'));
            }
        }
    }

    /**
     * Zpracuje POST data nastavená odesláním nějakého formuláře v programu.
     * Pokud je očekávaná POST proměnná nastavena, přesměruje a ukončí skript.
     */
    public function zpracujPost(?Uzivatel $prihlasujici) {
        if (!$this->u) {
            return;
        }

        Aktivita::prihlasovatkoZpracuj($this->u, $prihlasujici);
        Aktivita::vyberTeamuZpracuj($this->u, $prihlasujici);
    }

    ////////////////////
    // pomocné funkce //
    ////////////////////

    private function dny() {
        $dny = [];
        for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
            $dny[] = clone $den;
        }
        return $dny;
    }

    /**
     * Inicializuje privátní proměnné skupiny (podle kterých se shlukuje) a
     * program (iterátor aktivit)
     */
    private function init() {
        if ($this->nastaveni[self::SKUPINY] === self::SKUPINY_MISTNOSTI) {
            $this->program = new ArrayIterator(Aktivita::zProgramu('poradi'));
            $this->grpf    = self::SKUPINY_PODLE_LOKACE_ID;

            $this->skupiny['0'] = 'Ostatní';
            $grp                = serazenePodle(Lokace::zVsech(), 'poradi');
            foreach ($grp as $t) {
                $this->skupiny[$t->id()] = ucfirst($t->nazev());
            }
        } else if ($this->nastaveni[self::OSOBNI]) {
            $this->program = new ArrayIterator(Aktivita::zProgramu('zacatek'));
            $this->grpf    = self::SKUPINY_PODLE_DEN;

            foreach ($this->dny() as $den) {
                $this->skupiny[$den->format('z')] = mb_ucfirst($den->format('l'));
            }
        } else {
            $this->program = new ArrayIterator(Aktivita::zProgramu('poradi_typu'));
            $this->grpf    = self::SKUPINY_PODLE_TYP_PORADI;

            // řazení podle poradi typu je nutné proto, že v tomto pořadí je i seznam aktivit
            $grp = serazenePodle(\Gamecon\Aktivita\TypAktivity::zVsech(), 'poradi');
            foreach ($grp as $t) {
                $this->skupiny[$t->id()] = mb_ucfirst($t->nazev());
            }
        }
    }

    /** detekce kolize dvou aktivit (jsou ve stejné místnosti v kryjícím se čase) */
    private static function koliduje($a = null, $b = null) {
        if ($a === null
            || $b === null
            || $a['grp'] != $b['grp']
            || $a['den'] != $b['den']
            || $a['kon'] <= $b['zac']
            || $b['kon'] <= $a['zac']
        ) return false;
        return true;
    }

    /** Řekne, jestli jsou aktivity v stejné skupině (místnosti a dnu) */
    private static function stejnaSkupina($a = null, $b = null) {
        if ($a === null
            || $b === null
            || $a['grp'] != $b['grp']
            || $a['den'] != $b['den']
        ) return false;
        return true;
    }

    /**
     * Vrátí následující nekolizní záznam z fronty aktivit a zruší ho, nebo null
     */
    private function popNasledujiciNekolizni(&$fronta) {
        foreach ($fronta as $key => $prvek) {
            if ($prvek['zac'] >= $this->posledniVydana['kon']) {
                $t = $prvek;
                unset($fronta[$key]);
                return $t;
            }
        }
        return null;
    }

    /**
     * Pomocná funkce pro načítání další aktivity z DB nebo z lokálního stacku
     * aktivit (globální proměnné se používají)
     */
    private function dalsiAktivita() {
        if (!$this->dbPosledni) {
            $this->dbPosledni = $this->nactiDalsiAktivitu($this->program);
        }

        while ($this->koliduje($this->posledniVydana, $this->dbPosledni)) {
            $this->aktFronta[] = $this->dbPosledni;
            $this->dbPosledni  = $this->nactiDalsiAktivitu($this->program);
        }

        if ($this->stejnaSkupina($this->dbPosledni, $this->posledniVydana) || !$this->aktFronta) {
            $t                = $this->dbPosledni;
            $this->dbPosledni = null;
            return $this->posledniVydana = $t;
        } else {
            if ($t = $this->popNasledujiciNekolizni($this->aktFronta))
                return $this->posledniVydana = $t;
            else
                return $this->posledniVydana = array_shift($this->aktFronta);
        }
    }

    /**
     * Vytisknutí konkrétní aktivity (formátování atd...)
     */
    private function tiskAktivity(array $aktivitaRaw) {
        /** @var Aktivita $aktivitaObjekt */
        $aktivitaObjekt = $aktivitaRaw['obj'];

        // určení css tříd
        $classes = [];
        if ($this->u && $aktivitaObjekt->prihlasen($this->u)) {
            $classes[] = 'prihlasen';
        }
        if ($this->u && $this->u->organizuje($aktivitaObjekt)) {
            $classes[] = 'organizator';
        }
        if ($this->u && $this->u->prihlasenJakoSledujici($aktivitaObjekt)) {
            $classes[] = 'sledujici';
        }
        if ($aktivitaObjekt->vDalsiVlne()) {
            $classes[] = 'vDalsiVlne';
        }
        if (!$aktivitaObjekt->volnoPro($this->u)) {
            $classes[] = 'plno';
        }
        if ($aktivitaObjekt->vBudoucnu()) {
            $classes[] = 'vBudoucnu';
        }
        $classes = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

        // název a url aktivity
        echo '<td colspan="' . $aktivitaRaw['del'] . '"><div' . $classes . '>';
        echo '<a href="' . $aktivitaObjekt->url() . '" target="_blank" class="programNahled_odkaz" data-program-nahled-id="' . $aktivitaObjekt->id() . '" title="' . $aktivitaObjekt->nazev() . '">' . $aktivitaObjekt->nazev() . '</a>';

        // doplňkové informace (druhý řádek)
        if ($this->nastaveni[self::DRD_PJ] && $aktivitaObjekt->typId() == TypAktivity::DRD && $aktivitaObjekt->prihlasovatelna()) {
            echo ' (' . $aktivitaObjekt->orgJmena() . ') ';
        }

        if ($aktivitaRaw['del'] > 1) {
            $obsazenost = $aktivitaObjekt->obsazenost();
            if ($obsazenost) {
                echo '<span class="program_obsazenost">' . $obsazenost . '</span>';
            }
        }

        if ($aktivitaObjekt->typId() != TypAktivity::DRD || $this->nastaveni[self::DRD_PRIHLAS]) { // hack na nezobrazování přihlašovátek pro DrD
            $parametry = 0;
            if ($this->nastaveni[self::PLUS_MINUS]) {
                $parametry |= Aktivita::PLUSMINUS_KAZDY;
            }
            if ($this->nastaveni[self::ZPETNE]) {
                $parametry |= Aktivita::ZPETNE;
            }
            if ($this->nastaveni[self::INTERNI]) {
                $parametry |= Aktivita::INTERNI;
            }
            echo ' ' . $aktivitaObjekt->prihlasovatko($this->u, $parametry);
        } elseif (defined('TESTING') && TESTING) {
            echo $aktivitaObjekt::formatujDuvodProTesting('DrD nemá povolené přihlašování');
        }

        if ($this->nastaveni[self::OSOBNI]) {
            echo '<span class="program_osobniTyp">' . mb_ucfirst($aktivitaObjekt->typ()->nazev()) . '</span>';
        }

        // případný formulář pro výběr týmu
        if ($this->nastaveni[self::TEAM_VYBER]) {
            echo $aktivitaObjekt->vyberTeamu($this->u);
        }

        // Místnost v programu pro orgy
        // if ($this->u && ($this->u->maZidli(ROLE_INFO) || $this->u->maPravo(P_TITUL_ORG))) {
        //     $lokace = $aktivitaObjekt->lokace();
        //     if ($lokace) {
        //         echo '<div class="program_lokace">' . $lokace . '</div>';
        //     }
        // }

        echo '</div></td>';
    }

    /**
     * Vytiskne tabulku programu
     */
    private function tiskTabulky(?array &$aktivitaRaw, $denId = null) {
        echo '<table class="' . $this->nastaveni[self::TABLE_CLASS] . '">';

        // tisk hlavičkového řádku s čísly
        echo '<tr><th></th>';
        for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++) {
            echo '<th>' . $cas . ':00</th>';
        }
        echo '</tr>';

        $this->tiskObsahuTabulky($aktivitaRaw, $denId);

        echo '</table>';
    }

    /**
     * Vytiskne obsah (vnitřní řádky) tabulky
     */
    private function tiskObsahuTabulky(?array &$aktivitaRaw, $denId = null) {
        $aktivit = 0;
        foreach ($this->skupiny as $typId => $typNazev) {
            // pokud v skupině není aktivita a nemají se zobrazit prázdné skupiny, přeskočit
            if (!$this->nastaveni[self::PRAZDNE] && (!$aktivitaRaw || $aktivitaRaw['grp'] != $typId)) {
                continue;
            }

            ob_start(); // výstup bufferujeme, pro případ že bude na víc řádků
            $radku = 0;
            while ($aktivitaRaw && $typId == $aktivitaRaw['grp']) {
                if ($denId && $aktivitaRaw['den'] != $denId) {
                    break;
                }

                for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++) {
                    if ($aktivitaRaw && $typId == $aktivitaRaw['grp'] && $cas == $aktivitaRaw['zac']) {
                        $cas += $aktivitaRaw['del'] - 1; // na konci cyklu jeste bude ++
                        $this->tiskAktivity($aktivitaRaw);
                        $aktivitaRaw = $this->dalsiAktivita();
                        $aktivit++;
                    } else {
                        echo '<td></td>';
                    }
                }
                echo '</tr><tr>';
                $radku++;
            }
            $radky = substr(ob_get_clean(), 0, -4);

            if ($radku > 0) {
                echo '<tr><td rowspan="' . $radku . '"><div class="program_nazevLinie">' . $typNazev . '</div></td>';
                echo $radky;
            } else if ($this->nastaveni[self::PRAZDNE] && $radku == 0) {
                echo $this->prazdnaMistnost($typNazev);
            }
        }

        if ($aktivit == 0) {
            $sloupcu = PROGRAM_KONEC - PROGRAM_ZACATEK + 1;
            echo "<tr><td colspan=\"$sloupcu\">Žádné aktivity tento den</td></tr>";
        }
    }

    /**
     * Načte jednu aktivitu (objekt) z iterátoru a vrátí vnitřní reprezentaci
     * (s cacheovanými hodnotami) pro program.
     */
    private function nactiDalsiAktivitu($iterator) {
        if (!$iterator->valid()) {
            return null;
        }
        /** @var Aktivita $aktivita */
        $aktivita = $iterator->current();
        $zac      = (int)$aktivita->zacatek()->format('G');
        $kon      = (int)$aktivita->konec()->format('G');
        if ($kon == 0) {
            $kon = 24;
        }
        switch ($this->grpf) {
            case self::SKUPINY_PODLE_TYP_ID :
            case self::SKUPINY_PODLE_TYP_PORADI :
                $grp = $aktivita->typId();
                break;
            case self::SKUPINY_PODLE_LOKACE_ID :
                $grp = $aktivita->lokaceId();
                break;
            case self::SKUPINY_PODLE_DEN :
                $grp = $aktivita->zacatek()->format('z');
                break;
            default :
                throw new Exception('nepodporovaný typ shlukování aktivit ' . $this->grpf);
        }

        $a = [
            'grp' => $grp,
            'zac' => $zac,
            'kon' => $kon,
            'den' => (int)$aktivita->zacatek()->format('z'),
            'del' => $kon - $zac,
            'obj' => $aktivita,
        ];
        $iterator->next();

        // u programu dne přeskočit aktivity, které nejsou daný den
        if ($this->nastaveni[self::DEN] && $this->nastaveni[self::DEN] != $a['den']) {
            return $this->nactiDalsiAktivitu($iterator);
        }

        // u osobního programu přeskočit aktivity, kde není přihlášen
        if ($this->nastaveni[self::OSOBNI]) {
            if (
                !$aktivita->prihlasen($this->u) &&
                !$this->u->prihlasenJakoSledujici($aktivita) &&
                !$this->u->organizuje($aktivita)
            ) {
                return $this->nactiDalsiAktivitu($iterator);
            }
        }

        // přeskočit případné speciální (neviditelné) aktivity
        if ($aktivita->viditelnaPro($this->u) || $this->nastaveni[self::INTERNI]) {
            return $a;
        } else {
            return $this->nactiDalsiAktivitu($iterator);
        }
    }

    /**
     * Vyplní proměnou $this->maxKolize nejvýšším počtem kolizí daného dne
     * Naplní pole a vrátí nevypsané aktivity
     *
     * @param int $denId číslo dne v roce (formát dateTimeCZ->format('z'))
     */
    public function nactiAktivityDne($denId) {
        $aktivita                       = $this->dalsiAktivita();
        $this->maxPocetAktivit [$denId] = 0;
        $this->aktivityUzivatele        = new ArrayObject();

        while ($aktivita) {
            if ($denId == $aktivita['den']) {
                $this->aktivityUzivatele->append($aktivita);
            }

            $aktivita = $this->dalsiAktivita();
        }

        $pocetKoliziDenCas = [];
        foreach ($this->aktivityUzivatele as $key => $value) {
            for ($cas = $value['zac']; $cas < $value['zac'] + $value['del']; $cas++) {
                if (isset($pocetKoliziDenCas[$denId][($cas)])) {
                    $pocetKoliziDenCas[$denId][($cas)]++;
                } else {
                    $pocetKoliziDenCas[$denId][($cas)] = 1;
                }
                if ($pocetKoliziDenCas[$denId][$cas] > $this->maxPocetAktivit [$denId]) {
                    $this->maxPocetAktivit[$denId] = $pocetKoliziDenCas[$denId][$cas];
                }
            }
        }

        $this->program->rewind(); // vrácení iterátoru na začátek pro případ, potřeby projít aktivity znovu pro jiný den
    }

    private function prazdnaMistnost($nazev) {
        $bunky = '';
        for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++)
            $bunky .= '<td></td>';
        return "<tr><td rowspan=\"1\"><div class=\"program_nazevLinie\">$nazev</div></td>$bunky</tr>";
    }
}
