<?php

/**
 * Výpis osobního programu včetně W listů
 * 
 * JanPo
 */
class OsobniProgramNahradnik extends OsobniProgram{ 
  protected $maxKolize; 

  /**
   * Přímý tisk osobního programu na výstup
   */
  function tisk() {
    $start = microtime(true); 
    $this->init();   
    $this->tiskHlavicka("Můj program");     
    $this->aktivita = $this->dalsiAktivita();
    
    for($den=new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()){
      $denId = (int)$den->formatDenVRoce();      
      $nevypsaneAktivity = $this->nevypsaneAktivityDne($denId);  
      $pocetKolizi = $this->getMaximumKolizi($denId);               // pocet kolizí 1 znamená že kolize není      
      $this->tiskNadpisRadku($den, $pocetKolizi);      
      
      if((count($nevypsaneAktivity)==0) && !$this->nastaveni['prazdne'] ){ 
        echo('<td colspan="16" bgcolor="black">Žádné aktivity tento den</td></tr>'); //během dne není aktivita
      }else{        
        ob_start();  //výstup bufferujeme, pro případ že bude na víc řádků
        $radku=0;
        
        while ($radku < $pocetKolizi){          
          $radku++;                
          for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++){  
            if ($nevypsaneAktivity->offsetExists($cas) && count($nevypsaneAktivity->offsetGet($cas)) > 0){
              while ($nevypsaneAktivity->offsetExists($cas)) { 
                foreach ($nevypsaneAktivity->offsetGet($cas) as $index => $akt) {  
                  if( $akt && $denId==$akt['den'] && $cas==$akt['zac'] ){                       
                    $nevypsaneAktivityCas = $nevypsaneAktivity->offsetGet($cas);
                    $nevypsaneAktivityCas->offsetUnset($index);                          
                    $nevypsaneAktivity->offsetSet($cas, $nevypsaneAktivityCas);
                    if (count($nevypsaneAktivity->offsetGet($cas))==0){          
                      $nevypsaneAktivity->offsetUnset($cas);
                    }

                    $this->tiskAktivity($akt);
                    $cas += $akt['del'] - 1; //na konci cyklu jeste bude ++
                    break 2;
                  }
                }
              }
            }else{
              echo('<td></td>');
            }          
          }
          if($radku < $pocetKolizi){
            echo('</tr><tr>');
          } 
        }

        $this->tiskRadky($radku);
      }
    }
    echo('</table>');
    $this->aktivita=null;
    
    
    $end = microtime(true); 
    $time = ($end - $start);

    echo '<b>Skript trval:</b> '.$time.' sekund';
  }
  
  ////////////////////
  // pomocné funkce //
  ////////////////////  
    
  /**
   * Vyplní proměnou $this->maxKolize nejvýšším počtem kolizí daného dne 
   * Naplní pole a vrátí nevypsané aktivity  
   * 
   * @param int $denId číslo dne v roce (formát dateTimeCZ->formatDenVRoce())
   * @return ArrayObject $nevypsaneAktivity pole nevypsaných aktivit daného času obsahující další ArrayObject obsahujicí aktivity
   */
  function nevypsaneAktivityDne($denId){
    $nevypsaneAktivity = new ArrayObject();
    $nevypsaneAktivityCas = new ArrayObject();
    $this->maxKolize [$denId] = 0;

    for($cas=PROGRAM_ZACATEK; $cas<PROGRAM_KONEC; $cas++){ 
      $pocetKoliziDenCas[$denId][$cas] = 0;
      while($this->aktivita && $denId==$this->aktivita['den'] ){    
        if ($denId == $this->aktivita['den'] && $cas >= $this->aktivita['zac'] && $cas < $this->aktivita['kon']){
          $pocetKoliziDenCas[$denId][$cas]++;
          if ($pocetKoliziDenCas[$denId][$cas] > $this->maxKolize [$denId]){              
            $this->maxKolize[$denId] = $pocetKoliziDenCas[$denId][$cas]; 
          }
        }

        if($denId == $this->aktivita['den'] && $cas==$this->aktivita['zac'] ){         
          $nevypsaneAktivityCas->append($this->aktivita) ;
          $this->aktivita = $this->dalsiAktivita();
        }else{
          break;
        }      
      }
      if (count($nevypsaneAktivityCas)>0){
        $nevypsaneAktivity->offsetSet($cas,$nevypsaneAktivityCas);
        $nevypsaneAktivityCas = new ArrayObject();   
      }
    } 
    return $nevypsaneAktivity;
  }  

  /**
   * Vrátí počet kolizí v daném dni    * 
   * 
   * @param int $denId číslo dne v roce (formát dateTimeCZ->formatDenVRoce())
   * @return int $this->maxKolize[$den] počet kolizí
   */
  function getMaximumKolizi($denId){ 
    return $this->maxKolize[$denId]; 
  }  
}
