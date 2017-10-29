<?php

/**
 * Výpis osobního programu - nefunguje více aktivit v jeden čas 
 * 
 * JanPo
 */
class OsobniProgram extends Program{
  protected $aktivita; 
  
  /**
   * Přímý tisk osobního programu na výstup
   */
  function tisk() {
    $start = microtime(true);
    $this->init();
    $this->tiskHlavicka("Můj program");    
    $aktivita = $this->dalsiAktivita();
    
    for($den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()){
      $denId = (int)$den->formatDenVRoce(); 
      $this->tiskNadpisRadku($den);  
      $aktivit=0;
                  
      if( (!$aktivita ) && !$this->nastaveni['prazdne'] ){         
        //během dne není aktivita, přeskočit
      }else{
        ob_start();  //výstup bufferujeme, pro případ že bude na víc řádků
        $radku=0;        
        
        while($aktivita && $denId==$aktivita['den'] ){    
          for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++){  
            if( $aktivita && $denId==$aktivita['den'] && $cas==$aktivita['zac'] ){ 
                $cas += $aktivita['del'] - 1; //na konci cyklu jeste bude ++
                $this->tiskAktivity($aktivita);
                $aktivita = $this->dalsiAktivita();
                $aktivit++;
            }else{
              echo('<td></td>');
            }            
          }
        }
        $radku++;
        $this->tiskRadky($radku);        
      }

      if($aktivit==0){
        echo('<td colspan="16" bgcolor="black">Žádné aktivity tento den</td></tr>'); //fixme magická konstanta
      }
    }
    echo('</table>');    
    
    $end = microtime(true); 
    $time = ($end - $start);

    echo '<b>Skript trval:</b> '.$time.' sekund';
  }
 
  ////////////////////
  // pomocné funkce //
  ////////////////////  
    
  /**
   * Inicializuje privátní proměnné skupiny (podle kterých se shlukuje) a
   * program (iterátor aktivit)
   * 
   * @param iterátor aktivit $this->program obsahuje aktivity z databáze na které je uživatel přihlášen 
   * @param $this->grpf parametr obsahující id_akce v podstatě nepotřebný
   */
  function init() {    
      $this->program = OsobniAktivita::getAktivityUzivatele($this->uid);
      $this->grpf = 'id';
  }
  
  /**
   * Vypíše aktivitu
   * 
   * @param array $a pole vytvořené z iterátoru obsahující odkaz na aktivitu (a['obj'])
   */
  function tiskAktivity($a) {
    $ao = $a['obj'];

    // určení css tříd
    $classes = $this->cssClass($ao);

    // název a url aktivity
    echo '<td colspan="'.$a['del'].'"><div'.$classes.'>';
    echo '<a href="' . $ao->url() . '" target="_blank">' . mb_ucfirst($ao->typText()).': <br>'. $ao->nazev() . '</a>';
    if($this->nastaveni['drdPj'] && $ao->typ() == Typ::DRD && $ao->prihlasovatelna()) {
      echo ' ('.$ao->orgJmena().') ';
    }
    
//    není potřeba
//    echo $ao->obsazenost();

    /**
     * nevím jestli je potřeba
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
*/
    echo '</div></td>';
  }
  
  
  /**
   * Vypíše řádek programu
   * 
   * @param int $radku
   */
  function tiskRadky ($radku){
    $radky=substr(ob_get_clean(),0,-4);
    if($radku>0) {
      echo $radky;
//    }elseif($this->nastaveni['prazdne'] && $radku == 0){
//      echo $this->prazdnaMistnost($typNazev); 
    }
  }    
  
  /**
   * Vypíše první buňku programu - den programu
   * 
   * @param type $den
   * @param type $pocetKolizi
   */
  function tiskNadpisRadku ($den, $pocetKolizi=0){    
    $rowspan = '';
    if ($pocetKolizi > 0) {
      $rowspan = ' rowspan = '.$pocetKolizi;
    }
    echo '<tr><th'.$rowspan.'>'.mb_ucfirst($den->formatProgram()).'</th>';
  }
}
