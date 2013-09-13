<?php

/**
 * Sleva na nějakou věc platná do financí
 * @todo doplnit jak bude třeba 
 */ 

class Sleva
{

  protected $popis;
  
  /**
   * Konstruktor bere součinitel slevy, typ věcí na který se aplikuje (dle
   * třídy finance) a text.
   */         
  protected function __construct($soucinitel,$absVyska,$typ,$popis)
  {
    $this->popis=$popis;
  }
  
  /**
   * Sleva převedená na řetězec je lidsky čitelný popis slevy
   */     
  function __toString()
  {
    return $this->popis;
  }
  
  /**
   * Vrátí lidsky čitelný popis slevy
   */     
  function popis()
  {
    return $this->popis;
  }
  
  /**
   * Vytvoří a vrátí slevu vyjádřenou procentuálně
   * @param $soucinitel Násobek ceny pro objednané věci daného typu
   * @param $typ Identifikátor typu věci, na který se sleva aplikuje, podle
   *  třídy Finance
   * @param $popis Slovní popis slevy
   * @return Objekt s procentuální slevou   
   */     
  static function procentualni($soucinitel,$typ,$popis)
  {
    return new self($soucinitel,null,$typ,$popis);
  }
  
  /**
   * Vytvoří a vrátí slevu vyjádřenou absolutní výškou peněz (korun)
   * @param $typ Identifikátor typu věci, na který se sleva aplikuje, podle
   *  třídy Finance. Pokud null tak na vše.
   * @todo neimplementováno      
   */     
  static function absolutni($vyska,$typ,$popis)
  {
  }
          
}

?>
