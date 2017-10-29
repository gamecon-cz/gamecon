<?php

/**
 * Zrychlený výpis programu
 */
class Program {

  protected $u = null; //aktuální uživatel v objektu
  protected $posledniVydana = null;
  protected $dbPosledni = null;
  protected $aktFronta = [];
  protected $program; // iterátor aktivit seřazených pro použití v programu
  protected $nastaveni = [
    'drdPj'         => false, // u DrD explicitně zobrazit jména PJů
    'drdPrihlas'    => false, // jestli se zobrazují přihlašovátka pro DrD
    'plusMinus'     => false, // jestli jsou v programu '+' a '-' pro změnu kapacity team. aktivit
    'osobni'        => false, // TODO již se používá jestli se zobrazuje osobní program (jinak full)
    'tableClass'    => 'program', //todo edit
    'teamVyber'     => false, // jestli se u teamové aktivity zobrazí full výběr teamu přímo v programu
    'technicke'     => false, // jestli jdou vidět i skryté technické aktivity
    'skupiny'       => 'linie', // seskupování programu - po místnostech nebo po liniích
    'prazdne'       => false, // zobrazovat prázdné skupiny?
    'zpetne'        => false, // jestli smí měnit přihlášení zpětně
  ];
  protected $grpf; // název metody na objektu aktivita, podle které se shlukuje
  protected $skupiny; // pole skupin, do kterých se shlukuje program, ve stylu id => název

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
   * Určení css tříd aktivit v programu a potomcích (osobní program)
   * TODO určení css tříd pro W listy
   * 
   * JanPo
   * 
   * @param Aktivita $ao 
   * @return array $classes
   */
  function cssClass($ao){    
    $classes = [];
    if($this->u && $ao->prihlasen($this->u))  $classes[] = 'prihlasen';
    if($this->u && $this->u->organizuje($ao)) $classes[] = 'organizator';
    if($ao->vDalsiVlne())                     $classes[] = 'vDalsiVlne';
    if(!$ao->volnoPro($this->u))              $classes[] = 'plno';
    if($ao->vBudoucnu())                      $classes[] = 'vBudoucnu';
    $classes = $classes ? ' class="'.implode(' ', $classes).'"' : '';
    return $classes;
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
   * Vypíše nadpis a časovou osu programu
   * 
   * @param String $nadpis 
   */
  function tiskHlavicka($nadpis){
    echo('<h2>'.$nadpis.'</h2><table class="'.$this->nastaveni['tableClass'].'"><tr><th></th>');
    for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++){   //výpis hlavičkového řádku s čísly
      echo('<th>'.$cas.'</th>');
    }
    echo '</tr>';
  }
  
  /**
   * Přímý tisk programu na výstup
   */
  function tisk() {
    $this->init();

    $aktivita = $this->dalsiAktivita();
    for( $den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen() )
    {
      $denId = (int)$den->formatDenVRoce();      
      $this->tiskHlavicka(mb_ucfirst($den->formatProgram()));
      $aktivit=0;
      foreach($this->skupiny as $typ => $typNazev)
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

  /**
   * Inicializuje privátní proměnné skupiny (podle kterých se shlukuje) a
   * program (iterátor aktivit)
   */
  function init() {
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

    $this->skupiny['0'] = 'Ostatní';
    foreach($grp as $t)
      $this->skupiny[$t->id()] = ucfirst($t->$labelf());
  }

  /** detekce kolize dvou aktivit (jsou ve stejné místnosti v kryjícím se čase) */
  static function koliduje($a = null, $b = null) {
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
  static function stejnaSkupina($a = null, $b = null) {
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
  function popNasledujiciNekolizni(&$fronta) {
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
  function dalsiAktivita() {
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
  function tiskAktivity($a) {
    $ao = $a['obj'];

    // určení css tříd
    $classes = $this->cssClass($ao);
    
    // název a url aktivity
    echo '<td colspan="'.$a['del'].'"><div'.$classes.'>';
    echo '<a href="' . $ao->url() . '" target="_blank">' . $ao->nazev() . '</a>';
    if($this->nastaveni['drdPj'] && $ao->typ() == Typ::DRD && $ao->prihlasovatelna()) {
      echo ' ('.$ao->orgJmena().') ';
    }
    echo $ao->obsazenost();

    // přihlašovátko
    if($ao->typ() != Typ::DRD || $this->nastaveni['drdPrihlas']) { // hack na nezobrazování přihlašovátek pro DrD
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
   * Načte jednu aktivitu (objekt) z iterátoru a vrátí vnitřní reprezentaci
   * (s cacheovanými hodnotami) pro program.
   */
  function nactiAktivitu($iterator) {
    if(!$iterator->valid()) return null;
    $a = $iterator->current();
    $zac = (int)$a->zacatek()->format('G');
    $kon = (int)$a->konec()->format('G');
    if($kon == 0) $kon = 24;
    $grpf = $this->grpf; // MAGIC (dynamické volání metody podle jména
    $a = [
      'grp' => $a->$grpf(),
      'zac' => $zac,
      'kon' => $kon,
      'den' => (int)$a->zacatek()->format('z'),
      'del' => $kon - $zac,
      'obj' => $a
    ];
    $iterator->next();
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

  private function prazdnaMistnost($nazev) {
    $bunky = '';
    for($cas = PROGRAM_ZACATEK; $cas < PROGRAM_KONEC; $cas++)
      $bunky .= '<td></td>';
    return "<tr><td rowspan=\"1\">$nazev</td>$bunky</tr>";
  }

}
