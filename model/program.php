<?php

use \Gamecon\Cas\DateTimeCz;

/**
 * Zrychlený výpis programu
 */
class Program
{

    /** @var Uzivatel|null */
    private $u = null; // aktuální uživatel v objektu
    private $posledniVydana = null;
    private $dbPosledni = null;
    private $aktFronta = [];
    private $program; // iterátor aktivit seřazených pro použití v programu
    private $nastaveni = [
        'drdPj' => false, // u DrD explicitně zobrazit jména PJů
        'drdPrihlas' => false, // jestli se zobrazují přihlašovátka pro DrD
        'plusMinus' => false, // jestli jsou v programu '+' a '-' pro změnu kapacity team. aktivit
        'osobni' => false, // jestli se zobrazuje osobní program (jinak se zobrazuje full)
        'tableClass' => 'program', //todo edit
        'teamVyber' => true, // jestli se u teamové aktivity zobrazí full výběr teamu přímo v programu
        'technicke' => false, // jestli jdou vidět i skryté technické aktivity
        'skupiny' => 'linie', // seskupování programu - po místnostech nebo po liniích
        'prazdne' => false, // zobrazovat prázdné skupiny?
        'zpetne' => false, // jestli smí měnit přihlášení zpětně
        'den' => null,  // zobrazit jen konkrétní den
    ];
    private $grpf; // název metody na objektu aktivita, podle které se shlukuje
    private $skupiny; // pole skupin, do kterých se shlukuje program, ve stylu id => název

    private $aktivityUzivatele = []; // aktivity uživatele
    private $maxPocetAktivit = []; // maximální počet souběžných aktivit v daném dni

    /**
     * Konstruktor bere uživatele a specifikaci, jestli je to osobní program
     */
    public function __construct(Uzivatel $u = null, $nastaveni = null) {
        if ($u instanceof Uzivatel) {
            $this->u = $u;
            $this->uid = $this->u->id();
        }
        if (is_array($nastaveni)) {
            $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
        }

        if ($this->nastaveni['osobni']) {
            $this->nastaveni['prazdne'] = true;
        }
    }

    /**
     * @return string url k stylu programu
     */
    function cssUrl() {
        $soubor = 'soubory/blackarrow/program/program-trida.css';
        $verze = substr(filemtime(WWW . '/' . $soubor), -6);
        return URL_WEBU . '/' . $soubor . '?v=' . $verze;
    }

    /**
     * Příprava pro tisk programu
     */
    function tiskToPrint() {
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

                                if ($this->u->prihlasenJakoNahradnikNa($akt['obj']) ||
                                    $akt['obj']->prihlasen($this->u) || $this->u->organizuje($akt['obj'])) {

                                    $pdf->Cell(30, 10, $start . ":00 - " . $konec . ":00", 1);
                                    if ($this->u->prihlasenJakoNahradnikNa($akt['obj'])) {
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
    function tisk() {
        $this->init();

        $aktivita = $this->dalsiAktivita();
        if ($this->nastaveni['osobni'] || $this->nastaveni['den']) {
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
    function zpracujPost() {
        if (!$this->u) {
            return;
        }

        Aktivita::prihlasovatkoZpracuj($this->u);
        Aktivita::vyberTeamuZpracuj($this->u);
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
        if ($this->nastaveni['skupiny'] == 'mistnosti') {
            $this->program = new ArrayIterator(Aktivita::zProgramu('poradi'));
            $this->grpf = 'lokaceId';

            $this->skupiny['0'] = 'Ostatní';
            $grp = serazenePodle(Lokace::zVsech(), 'poradi');
            foreach ($grp as $t) {
                $this->skupiny[$t->id()] = ucfirst($t->nazev());
            }
        } else if ($this->nastaveni['osobni']) {
            $this->program = new ArrayIterator(Aktivita::zProgramu('zacatek'));
            $this->grpf = 'den';

            foreach ($this->dny() as $den) {
                $this->skupiny[$den->format('z')] = mb_ucfirst($den->format('l'));
            }
        } else {
            $this->program = new ArrayIterator(Aktivita::zProgramu('typ'));
            $this->grpf = 'typId';

            // řazení podle ID nutné proto, že v tomto pořadí je i seznam aktivit
            $grp = serazenePodle(Typ::zVsech(), 'id');
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
            $this->dbPosledni = $this->nactiAktivitu($this->program);
        }

        while ($this->koliduje($this->posledniVydana, $this->dbPosledni)) {
            $this->aktFronta[] = $this->dbPosledni;
            $this->dbPosledni = $this->nactiAktivitu($this->program);
        }

        if ($this->stejnaSkupina($this->dbPosledni, $this->posledniVydana) || !$this->aktFronta) {
            $t = $this->dbPosledni;
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
    private function tiskAktivity(array $a) {
        /** @var Aktivita $aktivitaObjekt */
        $aktivitaObjekt = $a['obj'];

        // určení css tříd
        $classes = [];
        if ($this->u && $aktivitaObjekt->prihlasen($this->u)) {
            $classes[] = 'prihlasen';
        }
        if ($this->u && $this->u->organizuje($aktivitaObjekt)) {
            $classes[] = 'organizator';
        }
        if ($this->u && $this->u->prihlasenJakoNahradnikNa($aktivitaObjekt)) {
            $classes[] = 'nahradnik';
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
        echo '<td colspan="' . $a['del'] . '"><div' . $classes . '>';
        echo '<a href="' . $aktivitaObjekt->url() . '" target="_blank" class="programNahled_odkaz" data-program-nahled-id="' . $aktivitaObjekt->id() . '">' . $aktivitaObjekt->nazev() . '</a>';

        // doplňkové informace (druhý řádek)
        if ($this->nastaveni['drdPj'] && $aktivitaObjekt->typId() == Typ::DRD && $aktivitaObjekt->prihlasovatelna()) {
            echo ' (' . $aktivitaObjekt->orgJmena() . ') ';
        }

        if ($a['del'] > 1) {
            $obsazenost = $aktivitaObjekt->obsazenost();
            if ($obsazenost) echo '<span class="program_obsazenost">' . $obsazenost . '</span>';
        }

        if ($aktivitaObjekt->typId() != Typ::DRD || $this->nastaveni['drdPrihlas']) { // hack na nezobrazování přihlašovátek pro DrD
            $parametry = 0;
            if ($this->nastaveni['plusMinus']) {
                $parametry |= Aktivita::PLUSMINUS_KAZDY;
            }
            if ($this->nastaveni['zpetne']) {
                $parametry |= Aktivita::ZPETNE;
            }
            if ($this->nastaveni['technicke']) {
                $parametry |= Aktivita::TECHNICKE;
            }
            echo ' ' . $aktivitaObjekt->prihlasovatko($this->u, $parametry);
        }

        if ($this->nastaveni['osobni']) {
            echo '<span class="program_osobniTyp">' . mb_ucfirst($aktivitaObjekt->typ()->nazev()) . '</span>';
        }

        // případný formulář pro výběr týmu
        if ($this->nastaveni['teamVyber']) {
            echo $aktivitaObjekt->vyberTeamu($this->u);
        }

        echo '</div></td>';
    }

    /**
     * Vytiskne tabulku programu
     */
    function tiskTabulky(&$aktivita, $denId = null) {
        echo '<table class="' . $this->nastaveni['tableClass'] . '">';

        // tisk hlavičkového řádku s čísly
        echo '<tr><th></th>';
        for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++) {
            echo '<th>' . $cas . ':00</th>';
        }
        echo '</tr>';

        $this->tiskObsahuTabulky($aktivita, $denId);

        echo '</table>';
    }

    /**
     * Vytiskne obsah (vnitřní řádky) tabulky
     */
    function tiskObsahuTabulky(&$aktivita, $denId = null) {
        $aktivit = 0;
        foreach ($this->skupiny as $typ => $typNazev) {
            // pokud v skupině není aktivita a nemají se zobrazit prázdné skupiny, přeskočit
            if (!$this->nastaveni['prazdne'] && (!$aktivita || $aktivita['grp'] != $typ)) {
                continue;
            }

            ob_start(); // výstup bufferujeme, pro případ že bude na víc řádků
            $radku = 0;
            while ($aktivita && $typ == $aktivita['grp']) {
                if ($denId && $aktivita['den'] != $denId) {
                    break;
                }

                for ($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++) {
                    if ($aktivita && $typ == $aktivita['grp'] && $cas == $aktivita['zac']) {
                        $cas += $aktivita['del'] - 1; // na konci cyklu jeste bude ++
                        $this->tiskAktivity($aktivita);
                        $aktivita = $this->dalsiAktivita();
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
            } else if ($this->nastaveni['prazdne'] && $radku == 0) {
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
    private function nactiAktivitu($iterator) {
        if (!$iterator->valid()) return null;
        $a = $iterator->current();
        $zac = (int)$a->zacatek()->format('G');
        $kon = (int)$a->konec()->format('G');
        if ($kon == 0) $kon = 24;
        if ($this->grpf == 'typId') $grp = $a->typId();
        elseif ($this->grpf == 'lokaceId') $grp = $a->lokaceId();
        elseif ($this->grpf == 'den') $grp = $a->zacatek()->format('z');
        else                                throw new Exception('nepodporovaný typ shlukování aktivit');

        $a = [
            'grp' => $grp,
            'zac' => $zac,
            'kon' => $kon,
            'den' => (int)$a->zacatek()->format('z'),
            'del' => $kon - $zac,
            'obj' => $a,
        ];
        $iterator->next();

        // u programu dne přeskočit aktivity, které nejsou daný den
        if ($this->nastaveni['den'] && $this->nastaveni['den'] != $a['den']) {
            return $this->nactiAktivitu($iterator);
        }

        // u osobního programu přeskočit aktivity, kde není přihlášen
        if ($this->nastaveni['osobni']) {
            if (
                !$a['obj']->prihlasen($this->u) &&
                !$this->u->prihlasenJakoNahradnikNa($a['obj']) &&
                !$this->u->organizuje($a['obj'])
            ) {
                return $this->nactiAktivitu($iterator);
            }
        }

        // přeskočit případné speciální (neviditelné) aktivity
        if (
            $a['obj']->viditelnaPro($this->u) ||
            $this->nastaveni['technicke']
        ) {
            return $a;
        } else {
            return $this->nactiAktivitu($iterator);
        }
    }

    /**
     * Vyplní proměnou $this->maxKolize nejvýšším počtem kolizí daného dne
     * Naplní pole a vrátí nevypsané aktivity
     *
     * @param int $denId číslo dne v roce (formát dateTimeCZ->format('z'))
     */
    function nactiAktivityDne($denId) {
        $aktivita = $this->dalsiAktivita();
        $this->maxPocetAktivit [$denId] = 0;
        $this->aktivityUzivatele = new ArrayObject();

        while ($aktivita) {
            if ($denId == $aktivita['den']) {
                $this->aktivityUzivatele->append($aktivita);
            }

            $aktivita = $this->dalsiAktivita();
        }

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
