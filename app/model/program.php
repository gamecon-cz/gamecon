<?php

/**
 * Zrychlený výpis programu
 */
class Program {

  private $u = null; //aktuální uživatel v objektu
  private $posledniVydana = null;
  private $dbPosledni = null;
  private $aktFronta = [];
  private $program;
  private $nastaveni = [
    'drdPj'         => false, // u DrD explicitně zobrazit jména PJů
    'drdPrihlas'    => false, // jestli se zobrazují přihlašovátka pro DrD
    'plusMinus'     => false, // jestli jsou v programu '+' a '-' pro změnu kapacity team. aktivit
    'osobni'        => false, // TODO již se používá jestli se zobrazuje osobní program (jinak full)
    'tableClass'    => 'program', //todo edit
    'teamVyber'     => false, // jestli se u teamové aktivity zobrazí full výběr teamu přímo v programu
    'technicke'     => false, // jestli jdou vidět technické aktivity
    'skupiny'       => 'linie', // seskupování programu - po místnostech nebo po liniích
    'prazdne'       => false, // zobrazovat prázdné skupiny?
    'zpetne'        => false, // jestli smí měnit přihlášení zpětně
  ];
  private $grpf; // název metody na objektu aktivita, podle které se groupuje

  /** Konstruktor bere uživatele a specifikaci, jestli je to osobní program */
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
   * Přímý tisk programu na výstup
   */
  public function tisk() {
    // načtení seznamu pro groupování
    if($this->nastaveni['skupiny'] == 'mistnosti') {
      $this->program = Aktivita::zProgramu('poradi');
      $grp = Lokace::zVsech();
      $this->grpf = 'lokaceId';
      $labelf = 'nazevInterni';
      usort($grp, function($a, $b) {
        return $a->poradi() > $b->poradi();
      });
    } else {
      $this->program = Aktivita::zProgramu('typ');
      $grp = Typ::zVsech();
      $this->grpf = 'typ'; // MAGIC dynamické volání metody dle jména
      $labelf = 'nazev';
      usort($grp, function($a, $b) {
        return $a->id() > $b->id();
      });
    }
    $typy['0'] = 'Ostatní';
    foreach($grp as $t)
      $typy[$t->id()] = ucfirst($t->$labelf());

    ////////// tisk samotného programu //////////

    $aktivita = $this->dalsiAktivita();
    for( $den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen() )
    {
      $denId = (int)$den->format('z');
      echo('<h2>'.mb_ucfirst($den->format('l j.n.Y')).'</h2><table class="'.$this->nastaveni['tableClass'].'"><tr><th></th>');
      for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++)   //výpis hlavičkového řádku s čísly
        echo('<th>'.$cas.'</th>');
      $aktivit=0;
      foreach($typy as $typ => $typNazev)
      {
        if( (!$aktivita || $aktivita['grp'] != $typ) && !$this->nastaveni['prazdne'] ) continue;  //v lokaci není aktivita, přeskočit
        ob_start();  //výstup bufferujeme, pro případ že bude na víc řádků
        $radku=0;
        //ošetření proti kolidujícím aktivitám v místnosti
        while( $aktivita && $typ==$aktivita['grp'] && $denId==$aktivita['den'] )
        {
          for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++)
          {
            if( $aktivita && $typ==$aktivita['grp'] && $cas==$aktivita['zac'] ) //pokud je aktivita už v jiné lokaci, dojedeme stávající řádek
            {
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


  ////////////////////
  // pomocné funkce //
  ////////////////////

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
   * Vrátí následující nekolizní záznam z fronty aktivit a zruší ho, nebo FALSE
   */
  private function popNasledujiciNekolizni(&$fronta) {
    foreach($fronta as $key=>$prvek) {
      if( $prvek['zac'] >= $this->posledniVydana['kon'] ) {
        $t=$prvek;
        unset($fronta[$key]);
        return $t;
      }
    }
    return false;
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

  /** Vytisknutí konkrétní aktivity (formátování atd...) */
  private function tiskAktivity($a) {
    $classes = [];
    if($this->u && $a['obj']->prihlasen($this->u))  $classes[] = 'prihlasen';
    if($this->u && $a['obj']->organizuje($this->u)) $classes[] = 'organizator';
    if($a['obj']->vDalsiVlne())                     $classes[] = 'vDalsiVlne';
    if(!$a['obj']->volnoPro($this->u))              $classes[] = 'plno';
    $classes = $classes ? ' class="'.implode(' ', $classes).'"' : '';
    echo '<td colspan="'.$a['del'].'"'.$classes.'><div>'.$a['obj']->nazev();
    if($this->nastaveni['drdPj'] && $a['obj']->typ() == 9 && $a['obj']->prihlasovatelna()) {
      echo ' ('.$a['obj']->orgJmena().') ';
    }
    echo $a['obj']->obsazenost();
    if(
      ( $a['obj']->typ() != 10 || $this->nastaveni['technicke'] ) && // hack na nezobrazování přihlašovátek pro technické
      ( $a['obj']->typ() != 9 || $this->nastaveni['drdPrihlas'] ) // hack na nezobrazování přihlašovátek pro DrD
    ) {
      $parametry = 0;
      if($this->nastaveni['plusMinus']) $parametry |= Aktivita::PLUSMINUS_KAZDY;
      if($this->nastaveni['zpetne']) $parametry |= Aktivita::ZPETNE;
      echo ' '.$a['obj']->prihlasovatko($this->u, $parametry);
    }
    if($this->nastaveni['teamVyber']) {
      echo $a['obj']->vyberTeamu($this->u);
    }
    echo '</div></td>';
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
    $grpf = $this->grpf; // MAGIC (dynamické volání metody podle jména
    $a = array(
      'grp' => $a->$grpf(),
      'zac' => $zac,
      'kon' => $kon,
      'den' => (int)$a->zacatek()->format('z'),
      'del' => $kon - $zac,
      'obj' => $a
    );
    $iterator->next();
    if($a['obj']->typ() != 10 || $this->nastaveni['technicke'] || $this->u && $a['obj']->prihlasen($this->u)) {
      return $a;
    } else {
      return $this->nactiAktivitu($iterator);
    }
  }

  private function prazdnaMistnost($nazev) {
    $bunky = '';
    for($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++)
      $bunky .= '<td></td>';
    return "<tr><td>$nazev</td>$bunky</tr>";
  }


  ////////////////////////////
  // Default CSSko programu //
  ////////////////////////////

  public static function css() {
    ?><style>
      table.program {
        text-align: center;
        border-top: none;
        border-spacing: 0px;
        margin: 0;
        table-layout: fixed;
        min-width: 800px; }
      table.program td, table.program th {
        width: 5%;
        padding: 3px;
        margin: 0px;
        border-left: 1px solid #fff;
        border-right: 1px solid #000;
        border-top: 1px solid #fff;
        border-bottom: 1px solid #000;
        overflow: hidden;
        vertical-align: middle;
        text-align: center; }
      table.program th { border-top:0; background-color: #700; }
      table.program th:first-child { min-width: 165px; border-top-left-radius: 10px; border-left-color: #888; }
      table.program th:last-child { border-top-right-radius: 10px; }
      table.program tr:nth-child(odd) { background-color: #CAAE99; }
      table.program tr { background-color: #D2C0B2; }
      table.program tr:first-child { background-color: transparent; }
      table.program th { color: #fff; font-weight: normal; }
      table.program td.prihlasen { background-color: #bab2d2; }
      table.program td.organizator { background-color: #bad2b2; }
      table.program td a { color:#a00; text-decoration:none; }
      table.program td form input:hover { text-decoration: underline; }
      table.program td .f { color: #e0d; }
      table.program td .m { color: #0ff; }
      table.program td .neprihlasovatelna { color: #777; }
    </style><?php
  }

  public static function cssRetezec() {
    ob_start();
    self::css();
    return ob_get_clean();
  }

}
