<?php

/**
 * Třída pro zpracování url
 */ 

class Url
{

  protected $surova, $cista, $casti;
  
  /** Konstruktor bere na vstupu řetězec s názvem GET proměnné, z které zkusí
   *  vycucnout URL */  
  public function __construct($getName)
  {
    $this->surova=isset($_GET[$getName])?$_GET[$getName]:'';
    if(!preg_match('@^[A-Za-z0-9\-/]*$@',$this->surova))
      throw new Exception('Nepovolené znaky v URL.');
    else
      $this->cista=$this->surova;
    $this->casti=explode('/',$this->cista);
  }
  
  /** Vrací část url na daném pořadím (od 0) */ 
  function cast($i)
  {
    return $this->casti[$i];
  }
  
  /** Vrací celou url */
  function cela()
  {
    return $this->cista;
  }
  
  /** Vrací počet zadaných částí url */
  function delka()
  {
    return $this->casti[0]?count($this->casti):0;
  }
        
}

?>
