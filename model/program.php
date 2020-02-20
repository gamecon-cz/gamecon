<?php

use \Gamecon\Cas\DateTimeCz;

/**
 * Zrychlený výpis programu
 */
class Program {

  private $u = null; //aktuální uživatel v objektu
  private $posledniVydana = null;
  private $dbPosledni = null;
  private $aktFronta = [];
  private $program; // iterátor aktivit seřazených pro použití v programu
  private $nastaveni = [
    'drdPj'         => false, // u DrD explicitně zobrazit jména PJů
    'drdPrihlas'    => false, // jestli se zobrazují přihlašovátka pro DrD
    'plusMinus'     => false, // jestli jsou v programu '+' a '-' pro změnu kapacity team. aktivit
    'osobni'        => false, // jestli se zobrazuje osobní program (jinak se zobrazuje full)
    'tableClass'    => 'program', //todo edit
    'teamVyber'     => false, // jestli se u teamové aktivity zobrazí full výběr teamu přímo v programu
    'technicke'     => false, // jestli jdou vidět i skryté technické aktivity
    'skupiny'       => 'linie', // seskupování programu - po místnostech nebo po liniích
    'prazdne'       => false, // zobrazovat prázdné skupiny?
    'zpetne'        => false, // jestli smí měnit přihlášení zpětně
  ];
  private $grpf; // název metody na objektu aktivita, podle které se shlukuje
  private $skupiny; // pole skupin, do kterých se shlukuje program, ve stylu id => název

  private $aktivityUzivatele = []; // aktivity uživatele
  private $maxPocetAktivit = []; // maximální počet souběžných aktivit v daném dni

  /**
   * Konstruktor bere uživatele a specifikaci, jestli je to osobní program
   */
  function __construct(Uzivatel $u=null, $nastaveni=null) {
    if($u instanceof Uzivatel) {
      $this->u=$u;
      $this->uid=$this->u->id();
    }
    if(is_array($nastaveni)) {
      $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
    }
  }

  /**
   * Vytiskne hlavičkový style tag pro program
   */
  static function css() {
    echo '<style>';
    readfile(__DIR__ . '/program.css');
    echo '</style>';
  }

  /**
   * Vrátí hlavičkový style tag pro program jako řetězec
   */
  static function cssRetezec() {
    ob_start();
    self::css();
    return ob_get_clean();
  }



  /**
   * Příprava pro tisk programu
   */
  function tiskToPrint() {
     $this->init();

     require_once __DIR__ . '/../vendor/setasign/tfpdf/tfpdf.php';
     $pdf = new tFPDF();
     $pdf->AddPage();
     $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
     $pdf->SetFont('DejaVu','',20);
     $pdf->Cell(0,10,"Můj program (" . $this->u->jmeno() . ")",0,1,'L');
     $pdf->SetFillColor(202,204,206);
     $pdf->SetFont('DejaVu','',12);

     for($den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()){
      $denId = (int)$den->format('z');
      $this->nactiAktivityDne($denId);

      if((count($this->aktivityUzivatele)>0)) {
        $pocetPrihlasenychAktivit = 0;
        foreach($this->aktivityUzivatele as $key => $akt) {
          if($akt['obj']->prihlasen($this->u)){
            $pocetPrihlasenychAktivit += 1;
          }
        }

        if($pocetPrihlasenychAktivit > 0){
          $pdf->Cell(0,10,mb_ucfirst($den->format('l j.n.Y')), 1,1,'L',true);
          for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++) {

            foreach($this->aktivityUzivatele as $key => $akt) {

                if( $akt && $denId==$akt['den'] && $cas==$akt['zac']) {
                  $start = $cas;
                  $konec = $cas + $akt['del'];

                  if($this->u->prihlasenJakoNahradnikNa($akt['obj']) ||
                    $akt['obj']->prihlasen($this->u) || $this->u->organizuje($akt['obj'])){

                    $pdf->Cell(30,10,$start . ":00 - " . $konec . ":00", 1);
                    if($this->u->prihlasenJakoNahradnikNa($akt['obj'])){
                      $pdf->Cell(100,10,"(n) " . $akt['obj']->nazev(), 1);
                    } else if($akt['obj']->prihlasen($this->u)){
                      $pdf->Cell(100,10,$akt['obj']->nazev(), 1);
                    }else if($this->u->organizuje($akt['obj'])){
                      $pdf->Cell(100,10,"(o) " . $akt['obj']->nazev(), 1);
                    }
                    $pdf->Cell(60,10,mb_ucfirst($akt['obj']->typ()->nazev()), 1,1);
                }
              }
            }
          }
        }
      }

      $pdf->Cell(0,1,"",0,1);
    }

    $pdf->Output();

  }

  /**
   * Přímý tisk programu na výstup
   */
  function tisk() {
    $this->init();

    if($this->nastaveni['osobni'] === true) {
      $this->tiskHlavicka("Můj program");

      for($den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()){
        $denId = (int)$den->format('z');
        $this->nactiAktivityDne($denId);
        $pocetKolizi = $this->getMaximumKolizi($denId);               // pocet kolizí 1 znamená že kolize není
        $this->tiskNadpisRadku($den, $pocetKolizi);

        if((count($this->aktivityUzivatele)==0)) {
          echo('<td colspan="16" bgcolor="black">Žádné aktivity tento den</td></tr>'); //během dne není aktivita
        } else {
          ob_start();  //výstup bufferujeme, pro případ že bude na víc řádků
          $radku=0;

          while(count($this->aktivityUzivatele) > 0) {

            for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++) {
              $prazdnaBunka = true;
              foreach($this->aktivityUzivatele as $key => $akt) {
                if( $akt && $denId==$akt['den'] && $cas==$akt['zac']) {
                  $cas += $akt['del'] -1; //na konci cyklu jeste bude ++
                  $this->aktivityUzivatele->offsetUnset($key);
                  $this->tiskAktivity($akt);
                  $prazdnaBunka = false;
                  break;
                }
              }
              if($prazdnaBunka === true) {
                  echo('<td></td>');
              }
            }

            $radku++;
            if($radku < $pocetKolizi) {
              echo('</tr><tr>');
            }
          }

          $radky=substr(ob_get_clean(),0,-5);
          if($radku>0) {
            echo $radky;
          }
        }
      }

      echo('</table>');
    } else {
      $aktivita = $this->dalsiAktivita();
      for($den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
        $denId = (int)$den->format('z');
        $this->tiskHlavicka(mb_ucfirst($den->format('l j.n.Y')));

        $aktivit=0;
        foreach($this->skupiny as $typ => $typNazev) {
          if( (!$aktivita || $aktivita['grp'] != $typ) && !$this->nastaveni['prazdne']) continue;  //v lokaci není aktivita, přeskočit
          ob_start();  //výstup bufferujeme, pro případ že bude na víc řádků
          $radku=0;
          //ošetření proti kolidujícím aktivitám v místnosti
          while( $aktivita && $typ==$aktivita['grp'] && $denId==$aktivita['den']) {
            for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++) {
              if( $aktivita && $typ==$aktivita['grp'] && $cas==$aktivita['zac']) { //pokud je aktivita už v jiné lokaci, dojedeme stávající řádek
                $cas += $aktivita['del'] - 1; //na konci cyklu jeste bude ++
                $this->tiskAktivity($aktivita);
                $aktivita = $this->dalsiAktivita();
                $aktivit++;
              }
              else
                echo('<td></td>');
            }
            echo('</tr><tr>');
            $radku++;
          }
          $radky=substr(ob_get_clean(),0,-4);
          if($radku>0) echo('<tr><td rowspan="'.$radku.'">'.$typNazev.'</td>'.$radky);
          elseif($this->nastaveni['prazdne'] && $radku == 0) echo $this->prazdnaMistnost($typNazev);
        }
        if($aktivit==0)
          echo('<tr><td colspan="17">Žádné aktivity tento den</td></tr>'); //fixme magická konstanta
        echo('</table>');
      }
    }
  }

  ////////////////////
  // pomocné funkce //
  ////////////////////

  /**
   * Inicializuje privátní proměnné skupiny (podle kterých se shlukuje) a
   * program (iterátor aktivit)
   */
  private function init() {
    $this->skupiny['0'] = 'Ostatní';

    if($this->nastaveni['skupiny'] == 'mistnosti') {
      $this->program = new ArrayIterator(Aktivita::zProgramu('poradi'));
      $grp = Lokace::zVsech();
      $this->grpf = 'lokaceId';
      usort($grp, function($a, $b) {
        return $a->poradi() > $b->poradi();
      });
      foreach($grp as $t) {
        $this->skupiny[$t->id()] = ucfirst($t->nazev());
      }
    } else {
      if($this->nastaveni['osobni']) {
        $this->program = new ArrayIterator(Aktivita::zProgramu('zacatek'));
      } else {
        $this->program = new ArrayIterator(Aktivita::zProgramu('typ'));
      }
      $grp = Typ::zVsech();
      $this->grpf = 'typId';
      usort($grp, function($a, $b) {
        return $a->id() > $b->id();
      });
      foreach($grp as $t) {
        $this->skupiny[$t->id()] = ucfirst($t->nazev());
      }
    }
  }

  /** detekce kolize dvou aktivit (jsou ve stejné místnosti v kryjícím se čase) */
  private static function koliduje($a = null, $b = null) {
    if(  $a===null
      || $b===null
      || $a['grp'] != $b['grp']
      || $a['den'] != $b['den']
      || $a['kon'] <= $b['zac']
      || $b['kon'] <= $a['zac']
    ) return false;
    return true;
  }

  /** Řekne, jestli jsou aktivity v stejné skupině (místnosti a dnu) */
  private static function stejnaSkupina($a = null, $b = null) {
    if(  $a===null
      || $b===null
      || $a['grp'] != $b['grp']
      || $a['den'] != $b['den']
    ) return false;
    return true;
  }

  /**
   * Vrátí následující nekolizní záznam z fronty aktivit a zruší ho, nebo null
   */
  private function popNasledujiciNekolizni(&$fronta) {
    foreach($fronta as $key => $prvek) {
      if( $prvek['zac'] >= $this->posledniVydana['kon'] ) {
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
    if(!$this->dbPosledni) {
      $this->dbPosledni = $this->nactiAktivitu($this->program);
    }

    while($this->koliduje($this->posledniVydana, $this->dbPosledni)) {
      $this->aktFronta[] = $this->dbPosledni;
      $this->dbPosledni = $this->nactiAktivitu($this->program);
    }

    if($this->stejnaSkupina($this->dbPosledni, $this->posledniVydana) || !$this->aktFronta) {
      $t = $this->dbPosledni;
      $this->dbPosledni = null;
      return $this->posledniVydana = $t;
    } else {
      if($t = $this->popNasledujiciNekolizni($this->aktFronta))
        return $this->posledniVydana = $t;
      else
        return $this->posledniVydana = array_shift($this->aktFronta);
    }
  }

  /**
   * Vytisknutí konkrétní aktivity (formátování atd...)
   */
  private function tiskAktivity($a) {
    $ao = $a['obj'];

    // určení css tříd
    $classes = [];
    if($this->u && $ao->prihlasen($this->u))                $classes[] = 'prihlasen';
    if($this->u && $this->u->organizuje($ao))               $classes[] = 'organizator';
    if($this->u && $this->u->prihlasenJakoNahradnikNa($ao)) $classes[] = 'nahradnik';
    if($ao->vDalsiVlne())                                   $classes[] = 'vDalsiVlne';
    if(!$ao->volnoPro($this->u))                            $classes[] = 'plno';
    if($ao->vBudoucnu())                                    $classes[] = 'vBudoucnu';
    $classes = $classes ? ' class="'.implode(' ', $classes).'"' : '';

    // název a url aktivity
    echo '<td colspan="'.$a['del'].'"><div'.$classes.'>';
    if($this->nastaveni['osobni']) {
      echo mb_ucfirst($ao->typ()->nazev()) . ': ';
    }
    echo '<a href="' . $ao->url() . '" target="_blank" class="programNahled_odkaz" data-program-nahled-id="' . $ao->id() . '">' . $ao->nazev() . '</a>';
    if($this->nastaveni['drdPj'] && $ao->typId() == Typ::DRD && $ao->prihlasovatelna()) {
      echo ' ('.$ao->orgJmena().') ';
    }
    echo $ao->obsazenost();

    // přihlašovátko
    if($ao->typId() != Typ::DRD || $this->nastaveni['drdPrihlas']) { // hack na nezobrazování přihlašovátek pro DrD
      $parametry = 0;
      if($this->nastaveni['plusMinus'])   $parametry |= Aktivita::PLUSMINUS_KAZDY;
      if($this->nastaveni['zpetne'])      $parametry |= Aktivita::ZPETNE;
      if($this->nastaveni['technicke'])   $parametry |= Aktivita::TECHNICKE;
      echo ' '.$ao->prihlasovatko($this->u, $parametry);
    }

    // případný formulář pro výběr týmu
    if($this->nastaveni['teamVyber']) {
      echo $ao->vyberTeamu($this->u);
    }

    echo '</div></td>';
  }

  /**
   * Vrátí počet kolizí v daném dni    *
   *
   * @param int $denId číslo dne v roce (formát dateTimeCZ->format('z'))
   * @return int $this->maxKolize[$den] počet kolizí
   */
  function getMaximumKolizi($denId){
    return $this->maxPocetAktivit[$denId];
  }

  /**
   * Načte jednu aktivitu (objekt) z iterátoru a vrátí vnitřní reprezentaci
   * (s cacheovanými hodnotami) pro program.
   */
  private function nactiAktivitu($iterator) {
    if(!$iterator->valid()) return null;
    $a = $iterator->current();
    $zac = (int)$a->zacatek()->format('G');
    $kon = (int)$a->konec()->format('G');
    if($kon == 0) $kon = 24;
    if($this->grpf == 'typId')          $grp = $a->typId();
    elseif($this->grpf == 'lokaceId')   $grp = $a->lokaceId();
    else                                throw new Exception('nepodporovaný typ shlukování aktivit');

    $a = [
      'grp' => $grp,
      'zac' => $zac,
      'kon' => $kon,
      'den' => (int)$a->zacatek()->format('z'),
      'del' => $kon - $zac,
      'obj' => $a
    ];
    $iterator->next();

    // u osobního programu vydat jenom aktivity, kde je přihlášen
    if($this->nastaveni['osobni']) {
      if(!$a['obj']->prihlasen($this->u) && !$this->u->prihlasenJakoNahradnikNa($a['obj']) && !$this->u->organizuje($a['obj'])) {
        return $this->nactiAktivitu($iterator);
      }
    }

    // přeskočit případné speciální (neviditelné) aktivity
    if(
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
    $this->aktivityUzivatele =  new ArrayObject();

    while($aktivita) {
      if($denId == $aktivita['den']) {
        $this->aktivityUzivatele->append($aktivita);
      }

      $aktivita = $this->dalsiAktivita();
    }

    foreach($this->aktivityUzivatele as $key => $value) {
      for($cas = $value['zac']; $cas < $value['zac'] + $value['del']; $cas++) {
        if(isset($pocetKoliziDenCas[$denId][($cas)])){
          $pocetKoliziDenCas[$denId][($cas)]++;
        } else {
          $pocetKoliziDenCas[$denId][($cas)] = 1;
        }
        if($pocetKoliziDenCas[$denId][$cas] > $this->maxPocetAktivit [$denId]) {
          $this->maxPocetAktivit[$denId] = $pocetKoliziDenCas[$denId][$cas];
        }
      }
    }

    $this->program->rewind(); // vrácení iterátoru na začátek pro případ, potřeby projít aktivity znovu pro jiný den
  }

  private function prazdnaMistnost($nazev) {
    $bunky = '';
    for($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++)
      $bunky .= '<td></td>';
    return "<tr><td rowspan=\"1\">$nazev</td>$bunky</tr>";
  }

  /**
   * Vypíše nadpis a časovou osu programu
   *
   * @param String $nadpis
   */
  private function tiskHlavicka($nadpis) {
    echo('<h2>'.$nadpis.'</h2><table class="'.$this->nastaveni['tableClass'].'"><tr><th></th>');
    for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++){   //výpis hlavičkového řádku s čísly
      echo('<th>'.$cas.'</th>');
    }
    echo '</tr>';
  }

  /**
   * Vypíše první buňku programu - den programu
   *
   * @param type $den
   * @param type $pocetKolizi
   */
  private function tiskNadpisRadku ($den, $pocetKolizi=0) {
    $rowspan = '';
    if($pocetKolizi > 0) {
      $rowspan = ' rowspan = '.$pocetKolizi;
    }
    echo '<tr><th'.$rowspan.'>'.mb_ucfirst($den->format('l j.n.Y')).'</th>';
  }
}
