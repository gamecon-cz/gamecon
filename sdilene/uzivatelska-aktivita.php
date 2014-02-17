<?php

/**
 * Třída aktivity vztažené ke konkrétnímu uživateli (implementuje zjišťování
 * stavu, přihlašování, odhlašování, ...).
 * 
 * V případě nezadání konkrétního uživatele se operace k němu vztažené ignorují
 * (funkce vracejí false).     
 */ 
class UzivatelskaAktivita extends Aktivita
{
  
  protected $u=null;
  
  function __construct($db,$u)
  {
    $this->u=$u;
    parent::__construct($db);
  }
  
  /**
   * Vrátí člověkem čitelnou cenu aktivity pro konkrétního uživatele. Obecnou
   * cenu lze vynutit pomocí parametru.   
   */
  function cena($specificka=true)
  {
    if($specificka)
      return parent::cena($this->u);
    else
      return parent::cena();
  }      
  
  /** Načte skupinu záznamů z databáze pro konstruktor. */
  static function nactiSkupinu($sqlWhere,$u,$sqlOrderBy=null)
  {
    return parent::nactiSkupinuRucne($sqlWhere,$u,$sqlOrderBy);
  }
  
  /**
   * Pseudokonstruktor načte aktivitu včetně údajů z DB
   * @param $aid id aktivity
   * @param $u Uzivatel Volitelně uživatel, u kterého se zjišťuje přihlášení
   */
  protected function nactiIdRucne($aid,$u=null)
  {
    $o=self::nactiSkupinuRucne('a.id_akce='.(int)$aid,$u);
    if(mysql_num_rows($o)!==1) 
      throw new Exception('Nelze načíst aktivitu s ID: '.$aid);
    return new UzivatelskaAktivita(mysql_fetch_assoc($o),$u);
  }
  
  /**
   * Vrátí násobek platby závislý na stavu přihlášení
   */
  public function nasobekPlatby()
  {
    if(isset($this->a['platba_procent']))
      return $this->a['platba_procent']/100.0;
    elseif(!$this->prihlasen()) //načtena jen jednoduchá vazba přihlášení
      return 1.0;
    else
      throw new Exception('Nenačteny stavy přihlášení. Zkuste joinnout při tvorbě akce_prihlaseni_stavy'); 
  }
  
  /** 
   * Zpracuje POST údaje tak jak jsou nastavené v přihlašovátku, následně
   * přesměruje zpět na původní stránku.
   * 
   * @param $u Uzivatel uživatel, který se při/odhlašuje *
   */  
  static function postPrihlasOdhlas($u)
  {
    if(!($u instanceof Uzivatel)) return false;
    if(isset($_POST['prihlasit']))
    {
      $a=self::nactiIdRucne($_POST['prihlasit'],$u);
      try { $a->prihlas($u); } 
      catch(Chyba $e) { $e->zpet(); }
      back();
    }
    if(isset($_POST['odhlasit']))
    {
      aktivitaOdhlas($u->id(),$_POST['odhlasit']);
      back();
    }
  }
  
  /** 
   * Přihlásí uživatele na aktivitu 
   */
  function prihlas()
  {
    $u=$this->u;
    if(!($u instanceof Uzivatel)) return false;
    if($this->prihlasen()) return true;
    if(!maVolno($u->id(),$this->a)) throw new Chyba(hlaska('kolizeAktivit'));
    if(!$u->gcPrihlasen()) throw new Exception('Nemáš aktivní přihlášku na GameCon.');
    if(!REG_AKTIVIT) throw new Exception('Přihlašování není spuštěno.');
    if(!$this->prihlasovatelna()) throw new Exception('Aktivita není otevřena pro přihlašování.');
    if($this->volno()=='u' || $this->volno()==$u->pohlavi())
    {
      self::prihlasRucne($u->id(),$this->id());
      return true;
    }
    return false;
  }
  
  /**
   * Je uživatel přihlášen na aktivitu?   
   */     
  function prihlasen()
  {
    if(isset($this->a['prihlasen']))
      return $this->a['prihlasen'];
    else
      return false;
  }

  /** 
   * Vrátí html kód pro přihlášení / odhlášení / informaci o zaplněnosti
   * @param $pouzeOdkaz bool má se vydávat pouze odkaz, bez průvodních informací
   *   jako "jen ženská místa" apod.?       
   */ 
  function prihlasovatko($pouzeOdkaz=false)
  {
    $u=$this->u;
    if(REG_AKTIVIT && $u && $u->gcPrihlasen() && $this->a['typ'] && $this->prihlasovatelna())
    {
      if(isset($this->a['id_stavu_prihlaseni']))
      {
        $stav=$this->a['id_stavu_prihlaseni'];
        if($stav==1) return '<em>účast</em>';
        if($stav==2) return '<em>jako náhradník</em>';
        if($stav==3) return '<em>neúčast</em>';
        if($stav==4) return '<em>pozdní odhlášení</em>';
        //jinak pokračujeme dál std. vyhodnocením
      }
      if($this->a['prihlasen'])
      {
        return '<a href="javascript:document.getElementById(\'odhlasit'.
          $this->id().'\').submit()">odhlásit</a><form '.
          'id="odhlasit'.$this->id().'" method="post" '.
          'style="position:absolute"><input type="hidden" name="odhlasit" '.
          'value="'.$this->id().'" /></form>';
      }
      elseif($this->a['organizator']==$this->u->id())
      {
        return '';
      }
      else
      {
        if($this->volno()=='u' || $this->volno()==$u->pohlavi())
          return '<a href="javascript:document.getElementById(\'prihlasit'.
            $this->id().'\').submit()">přihlásit</a><form '.
            'id="prihlasit'.$this->id().'" method="post" '.
            'style="position:absolute"><input type="hidden" name="prihlasit" '.
            'value="'.$this->id().'" /></form>';
        if($this->volno()=='f' && !$pouzeOdkaz)
          return 'pouze ženská místa';
        if($this->volno()=='m' && !$pouzeOdkaz)
          return 'pouze mužská místa';
        /*if($this->volno()=='x')
          return 'plná kapacita';*/
        return '';
      }
    }
    else
    {
      return '';
    }
  }
  
  
  ////////////////////
  // Protected věci //
  ////////////////////
  
  /**
   * Low-level funkce pro napráskání do databáze (čekuje pouze integritu při-
   * hlášení na potomky a existence samotné akce, příp. zabránění duplicit v
   * tabulkách prihlaseni a prihlaseni_spec).
   */      
  protected static function prihlasRucne($uid,$aid)
  {
    if(!(int)$uid) throw new Exception('Nezadán uživatel.');
    if(!(int)$aid) throw new Exception('Nezadána aktivita.');
    $aktivita=dbQuery('SELECT dite FROM akce_seznam WHERE id_akce='.(int)$aid);
    if(mysql_num_rows($aktivita)!==1) throw new Exception('Neexistující aktivita.');
    $a=mysql_fetch_assoc($aktivita);
    if($a['dite'])
      self::prihlasRucne($uid,$a['dite']);
    dbQuery('
      INSERT INTO akce_prihlaseni 
      SET id_uzivatele='.(int)$uid.', id_akce='.(int)$aid);
    dbQuery('
      INSERT INTO akce_prihlaseni_log 
      SET id_uzivatele='.(int)$uid.', id_akce='.(int)$aid.', typ="prihlaseni"');
    if(ODHLASENI_POKUTA_KONTROLA) //pokud by náhodou měl záznam za pokutu a přihlásil se teď, tak smazat
      dbQueryS('DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0  
        AND id_akce=$1 AND id_stavu_prihlaseni=4',array($uid,$aid));
  }
  
}
