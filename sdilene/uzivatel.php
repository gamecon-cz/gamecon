<?php

/**
 * Třída popisující uživatele a jeho vlastnosti
 * @todo načítání separátního (nepřihlášeného uživatele) např. pro účely schi-
 *   zofrenie v adminovi (nehrozí špatný přístup při nadměrném volání např. při
 *   práci s více uživateli někde jinde?)
 */
class Uzivatel
{
  protected
    $u=array(),
    $klic='',
    $finance=null;

  /** Vytvoří uživatele z různých možných vstupů */
  function __construct($uzivatel)
  {
    if(is_array($uzivatel) && array_keys_exist(array(
      'id_uzivatele', 'login_uzivatele', 'pohlavi'
      ),$uzivatel))
    { //asi čteme vstup z databáze
      $this->u=$uzivatel;
      return;
    }
    /* //zvážit, možná neimplementovat
    if((int)$uzivatel!=0)
    {
    }
    */
    else
      throw new Exception('Špatný vstup konstruktoru uživatele');
  }

  /**
   * Vrátí aboslutní adresu avataru včetně http. Pokud avatar neexistuje, vrací
   * default avatar. Pomocí adresy je docíleno, aby se při nezměně obrázku dalo
   * cacheovat.
   */
  function avatar()
  {
    $cesta=__DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/avatary/';
    $url=URL_WEBU.'/files/systemove/avatary/';
    if(is_file($cesta.$this->id().'.jpg'))
      // přidáváme datum poslední modifikace, aby prohlížeč necacheoval, pokud nemá
      // pozor: prohlížeč si dělá co chce a teprve po dlouhé nezměně obrázku začne cacheovat - to je ok
      return $url.$this->id().'.jpg?x='.base_convert(filemtime($cesta.$this->id().'.jpg'),10,16);
    else
      return self::avatarDefault();
  }

  /**
   * Vrátí defaultní avatar
   */
  static function avatarDefault()
  {
    return URL_WEBU.'/files/systemove/avatary/default.png';
  }

  /**
   * Načte a uloží avatar uživatele poslaný pomoci POST. Pokud se obrázek ne-
   * poslal, nestane se nic a vrátí false.
   * @param $name název post proměnné, ve které je obrázek, např. html input
   * <input type="file" name="obrazek"> má $name='obrazek'. U formu je potřeba
   * nastavit <form method="post" enctype="multipart/form-data"> enctype aby to
   * fungovalo
   * @return bool true pokud se obrázek nahrál a uložil, false jinak
   */
  function avatarNactiPost($name)
  {
    if(!isset($_FILES[$name]['tmp_name']) || !$_FILES[$name]['tmp_name'])
      return false;
    $cesta=__DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/avatary/';
    // překlopení avataru ID.jpg <--> IDb.jpg kvůli obejití cache
    $soub=$cesta.$this->id().'.jpg';
    move_uploaded_file($_FILES[$name]['tmp_name'],$soub);
    // úpravy obrázku podle podmínek
    $wMax=60;
    $hMax=60;
    $obr=imagecreatefromjpeg($soub); // načíst
    $nobr=imagecreatetruecolor(60,60);
    $w=imagesx($obr);
    $h=imagesy($obr);
    $hRatio=$hMax/$h; // násobek, kolikrát násobit výšku
    $wRatio=$wMax/$w; // násobek, kolikrát násobit šířku
    if($wRatio < $hRatio)
    { // obrázek přetekl na šířku
      $sy=0;
      $sh=$h;
      $sx=$w/2-$h/2;
      $sw=$h;
    }
    else
    { // obrázek přetekl na výšku
      $sy=$h/2-$w/2;
      $sh=$w;
      $sx=0;
      $sw=$w;
    }
    imagecopyresampled($nobr,$obr,
      0,0,         //dst x,y
      $sx,$sy,     //src x,y
      $wMax,$hMax, //dst w,h
      $sw,$sh      //scr w,h
    );
    imagejpeg($nobr,$soub,98); // uložit
    return true;
  }

  /** Smaže avatar uživatele. (jen uživatelská část webu) */
  function avatarSmaz()
  {
    if(is_file('./files/systemove/avatary/'.$this->id().'.jpg'))
      return unlink('./files/systemove/avatary/'.$this->id().'.jpg');
    else
      return true; //obrázek není -> jakoby se smazal v pohodě
  }

  /**
   * Vrátí datum narození uživatele jako DateTime
   */
  public function datumNarozeni()
  {
    if((int)$this->u['datum_narozeni']) //hack, neplatný formát je '0000-00-00'
      return new DateTime($this->u['datum_narozeni']);
    else
      return new DateTime('1970-01-01');
  }

  /**
   * Přidá uživateli židli (posadí uživatele na židli)
   */
  function dejZidli($idZidle)
  {
    $z=(int)$idZidle;
    if($z==0)
      return false;
    $o=dbQuery('SELECT * FROM r_prava_zidle WHERE id_zidle='.$z);
    while($r=mysql_fetch_assoc($o))
      if(!$this->maPravo($r['id_prava']))
      {
        $this->u['prava'][]=(int)$r['id_prava'];
        if($this->klic)
          $_SESSION[$this->klic]['prava'][]=(int)$r['id_prava'];
      }
    dbQuery('INSERT IGNORE INTO r_uzivatele_zidle(id_uzivatele,id_zidle)
      VALUES ('.$this->id().','.$z.')');
    return true;
  }

  /** Vrátí finance daného uživatele
   *  @todo ošetřit kolize v případě, že prve nepožádá rozšířený výpis aktivit
   *  a potom jo */
  function finance($nastaveni=null)
  {
    //pokud chceme finance poprvé, spočteme je a uložíme
    if(!$this->finance)
      $this->finance=new Finance($this,$nastaveni);
    return $this->finance;
  }

  /**
   * Odhlásí uživatele z aktuálního ročníku GameConu, včetně všech předmětů a
   * aktivit.
   * @todo Vyřešit, jak naložit s nedostaveními se na aktivity a podobně (např.
   * při počítání zůstatků a různých jiných administrativních úkolech to toho
   * uživatele může přeskakovat či ignorovat, atd…). Jmenovité problémy:
   * - platby (pokud ho vynecháme při přepočtu zůstatku, přijde o love)
   * @todo Možná vyhodit výjimku, pokud už prošel infem, místo pouhého neudělání
   * nic?
   * @todo Při odhlášení z GC pokud jsou zakázané rušení nákupů může být též
   * problém (k zrušení dojde)
   */
  function gcOdhlas()
  {
    if($this->gcPrihlasen() && !$this->gcPritomen())
    { // jen pokud je přihlášen a zároveň ještě neprošel infopultem
      // smazání přihlášení na aktivity, na které je jen přihlášen (ne je už hrál, jako náhradník apod.)
      dbQuery('DELETE p.* FROM akce_prihlaseni p JOIN akce_seznam a
        WHERE a.rok='.ROK.' AND p.id_stavu_prihlaseni=0 AND p.id_uzivatele='.$this->id());
      // smazání DrD, víceméně spekulativní, potenciální bugy
      dbQuery('DELETE FROM drd_postava WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM drd_prihlasky WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM drd_uzivatele_druziny WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM postavy_poznamka WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM postavy_schopnosti WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM postavy_vybaveni WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM postavy_zbrane_f2f WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      dbQuery('DELETE FROM postavy_zbrane_str WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      // zrušení nákupů
      dbQuery('DELETE FROM shop_nakupy WHERE rok='.ROK.' AND id_uzivatele='.$this->id());
      // finální odebrání židle "registrován na GC"
      $this->vemZidli(ID_ZIDLE_PRIHLASEN);
      // odeslání upozornění, pokud u nás má peníze
      if(mysql_num_rows(dbQuery('SELECT 1 FROM platby WHERE rok='.ROK.' AND id_uzivatele='.$this->id()))>0)
        mail(
          'info@gamecon.cz',
          'Uživatel '.$this->jmenoNick().' se odhlásil ale platil',
          'Uživatel '.$this->jmenoNick().' (ID '.$this->id().') se odhlásil z
          GameConu, ale v aktuálním roce ('.ROK.') si převedl nějaké peníze. Bude
          vhodné to prověřit popř. smazat platbu z připsaných a dát do zůstatku
          v seznamu uživatelů, aby mu peníze nepropadly');
    }
  }

  /** Je uživatel přihlášen na aktuální GC? */
  function gcPrihlasen()
  {
    return $this->maPravo(ID_PRAVO_PRIHLASEN);
  }

  /** Příhlásí uživatele na GC. True pokud je (nebo už byl) přihlášen. */
  function gcPrihlas()
  {
    if($this->gcPrihlasen())
      return true;
    else if($this->dejZidli(ID_ZIDLE_PRIHLASEN))
      return true;
    return false;
  }

  /** Prošel uživatel infopultem, dostal materiály a je přítomen na aktuálím
   *  GC? */
  function gcPritomen()
  {
    return $this->maPravo(ID_PRAVO_PRITOMEN);
  }

  /** Jméno a příjmení uživatele v běžném (zákonném) tvaru */
  function jmeno()
  {
    return $this->u['jmeno_uzivatele'].' '.$this->u['prijmeni_uzivatele'];
  }

  /** Vrátí řetězec s jménem i nickemu uživatele jak se zobrazí např. u
   *  organizátorů aktivit */
  function jmenoNick()
  {
    return jmenoNick($this->u);
  }

  /** Vrátí koncovku "a" pro holky (resp. "" pro kluky) */
  function koncA()
  {
    if($this->pohlavi()=='f') return 'a'; return '';
  }

  /** Vrátí primární mailovou adresu uživatele */
  function mail()
  {
    return $this->u['email1_uzivatele'];
  }

  function maPravo($pravo)
  {
    /*
    if(isset($_SESSION['uzivatel']['prava'])
      && in_array($pravo, $_SESSION['uzivatel']['prava']))
      return true;
    else
      return false;
    */
    if(isset($this->u['prava']))
    {
      if(in_array($pravo, $this->u['prava']))
        return true;
      else
        return false;
    }
    else
      throw new Exception('Nenačtena práva pro uživatele.');
  }

  /**
   * Ručně načte práva - neoptimalizovaná varianta, přijatelné pouze pro prasečí
   * řešení, kde si to můžeme dovolit (=reporty)
   */
  public function nactiPrava()
  {
    if(!isset($this->u['prava']))
    {
      //načtení uživatelských práv
      $p=dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele='.$this->id());
      $prava=array(); //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
      while($r=mysql_fetch_assoc($p))
        $prava[]=(int)$r['id_prava'];
      $this->u['prava']=$prava;
    }
  }

  /**
   * Vrátí pole s nováčky (uživateli), kteří mají tohoto uživatele jako "guru"
   */
  function novacci()
  {
    $n=self::nactiUzivatele('WHERE guru='.$this->id());
    return $n;
  }

  /** Vrátí přezdívku (nickname) uživatele */
  function nick()
  {
    return $this->u['login_uzivatele'];
  }

  /** Odhlásí aktuálně přihlášeného uživatele, pokud není přihlášen, nic
   *  @param bool $back rovnou otočit na referrer?   */
  static function odhlas($back=false)
  {
    if(!session_id())
      session_start();
    session_destroy();
    if(isset($_COOKIE['gcTrvalePrihlaseni']))
      setcookie('gcTrvalePrihlaseni','',0,'/');
    if($back) back();
  }

  /** Odpojí od session uživatele na indexu $klic */
  static function odhlasKlic($klic)
  {
    if(!session_id())
      session_start();
    unset($_SESSION[$klic]);
  }

  /**
   * Otočí (znovunačte, přihlásí a odhlásí, ...) uživatele
   */
  public function otoc()
  {
    if(!$this->klic) Throw new Exception('Neznámý klíč uživatele v session');
    $id=$this->id();
    $klic=$this->klic;
    //máme obnovit starou proměnnou pro id uživatele (otáčíme aktuálně přihlášeného uživatele)?
    $sesObnovit=(isset($_SESSION['id_uzivatele']) && $_SESSION['id_uzivatele']==$this->id());
    if($klic=='uzivatel') //pokud je klíč default, zničíme celou session
      $this->odhlas();
    else //pokud je speciální, pouze přemažeme položku v session
      $this->odhlasKlic($klic);
    $u=Uzivatel::prihlasId($id,$klic);
    $this->u=$u->u;
    if($sesObnovit) $_SESSION['id_uzivatele']=$this->id();
  }

  /** Přihlásí uživatele s loginem $login k stránce
   *  @param string $klic klíč do $_SESSION kde poneseme hodnoty uživatele
   *  @param $login login nebo primární e-mail uživatele
   *  @param $heslo heslo uživatele
   *  @return mixed objekt s uživatelem nebo null */
  public static function prihlas($login,$heslo,$klic='uzivatel')
  {
    $u=dbOneLineS('SELECT * FROM uzivatele_hodnoty
      WHERE (login_uzivatele=$0 OR email1_uzivatele=$0) AND heslo_md5=$1',
      array($login,md5($heslo)));
    if($u)
    {
      $id=$u['id_uzivatele'];
      session_start();
      $_SESSION[$klic]=$u;
      $_SESSION[$klic]['id_uzivatele']=(int)$u['id_uzivatele'];
      //načtení uživatelských práv
      $p=dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele='.$id);
      $prava=array(); //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
      while($r=mysql_fetch_assoc($p))
        $prava[]=(int)$r['id_prava'];
      $_SESSION[$klic]['prava']=$prava;
      return new Uzivatel($_SESSION[$klic]);
    }
    else
      return null;
  }

  /**
   * Vytvoří v session na indexu $klic dalšího uživatele pro práci
   * @return objekt Uzivatel nebo null
   */
  public static function prihlasId($id,$klic='uzivatel')
  {
    $u=dbOneLineS('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele=$0',
      array($id));
    if($u)
    {
      if(!session_id())
        session_start();
      $_SESSION[$klic]=$u;
      $_SESSION[$klic]['id_uzivatele']=(int)$u['id_uzivatele'];
      //načtení uživatelských práv
      $p=dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele='.$id);
      $prava=array(); //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
      while($r=mysql_fetch_assoc($p))
        $prava[]=(int)$r['id_prava'];
      $_SESSION[$klic]['prava']=$prava;
      $u=new Uzivatel($_SESSION[$klic]);
      $u->klic=$klic;
      return $u;
    }
    else
      return null;
  }

  /** Alias prihlas() pro trvalé přihlášení */
  public static function prihlasTrvale($login,$heslo,$klic='uzivatel')
  {
    $u=Uzivatel::prihlas($login,$heslo,$klic);
    if($u)
    {
      dbQuery('
        UPDATE uzivatele_hodnoty
        SET random="'.($rand=randHex(20)).'"
        WHERE id_uzivatele='.$u->id());
      setcookie('gcTrvalePrihlaseni',$rand,time()+3600*24*365,'/');
    }
    return $u;
  }

  /**
   * Zaregistruje uživatele podle asoc.pole $tab, které by mělo odpovídat stru-
   * ktuře tabulky uzivatele_hodnoty.
   * @return id nově vytvořeného uživatele
   * @todo (jen) pokud bude potřeba další parametry typu "automaticky aktivovat
   * a neposílat aktivační mail" a podobné válce.
   */
  static function registruj($tab)
  {
    if(!isset($tab['login_uzivatele']) || !isset($tab['email1_uzivatele']))
      throw new Exception('špatný formát $tab (je to pole?)');
    $tab['random']=$rand=randHex(20);
    dbInsert('uzivatele_hodnoty',array_merge($tab,array('registrovan'=>date("Y-m-d H:i:s"))));
    $uid=dbLastId();
    //poslání mailu
    $tab['id_uzivatele']=$uid;
    $u=new Uzivatel($tab); //pozor, spekulativní, nekompletní! využito kvůli std rozhraní hlaskaMail
    $mail=new GcMail(hlaskaMail('registraceMail',$u,$tab['email1_uzivatele'],$rand));
    $mail->adresat($tab['email1_uzivatele']);
    $mail->predmet('Registrace na GameCon.cz');
    if(!$mail->odeslat())
      die('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email nebo nastala neočekávaná chyba databáze. Kontaktujte nás prosím e-mailem <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>');
    return $uid;
  }

  /**
   * Rychloregistruje uživatele s omezeným počtem údajů při registraci na místě.
   * @return id nově vytvořeného uživatele (možno vytvořit objekt uživatele
   *  později jen pokud má smysl - výkonnostní důvody)
   * @todo možno evidovat, že uživatel byl regnut na místě
   * @todo poslat mail s něčím jiným jak std hláškou
   */
  static function rychloreg($tab)
  {
    if(!isset($tab['login_uzivatele']) || !isset($tab['email1_uzivatele']))
      throw new Exception('špatný formát $tab (je to pole?)');
    if(empty($tab['stat_uzivatele'])) $tab['stat_uzivatele']=1;
    $tab['random']=$rand=randHex(20);
    $tab['registrovan']=date("Y-m-d H:i:s");
    dbInsert('uzivatele_hodnoty',$tab);
    $uid=dbLastId();
    //poslání mailu
    $tab['id_uzivatele']=$uid;
    $u=new Uzivatel($tab); //pozor, spekulativní, nekompletní! využito kvůli std rozhraní hlaskaMail
    $mail=new GcMail(hlaskaMail('rychloregMail',$u,$tab['email1_uzivatele'],$rand));
    $mail->adresat($tab['email1_uzivatele']);
    $mail->predmet('Registrace na GameCon.cz');
    if(!$mail->odeslat())
      die('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email');
    return $uid;
  }

  /**
   * Sedí uživatel na dané židli?
   * NEslouží k čekování vlastností uživatele, které obecně řeší práva resp.
   * Uzivatel::maPravo(), skutečně výhradně k správě židlí jako takových. Also
   * přímé čtení z databáze => pomalé.
   */
  public function sediNaZidli($zidle)
  {
    return mysql_num_rows(dbQuery('SELECT * FROM r_uzivatele_zidle WHERE id_uzivatele='.$this->id().' AND id_zidle='.(int)$zidle))>0;
  }

  /** Vrátí html formátovaný „status“ uživatele (pro interní informaci) */
  function statusHtml()
  {
    $ka = $this->pohlavi()=='f' ? 'ka' : '';
    if($this->maPravo(P_TRIKO_ZDARMA))
      return '<span style="color:red">Organizátor'.$ka.'</span>';
    else if($this->maPravo(P_ORG_AKCI))
      return '<span style="color:blue">Vypravěč'.$ka.'</span>';
    else
      return 'Účastník';
  }

  /**
   * Vrátí telefon uživatele v blíže neurčeném formátu
   * @todo specifikovat formát čísla
   */
  function telefon()
  {
    return $this->u['telefon_uzivatele'];
  }

  public function vek()
  {
    $narozeni=new DateTime();
    $narozeni->setTimestamp($this->u['datum_narozeni_uzivatele']);
    $vek=$narozeni->diff(new DateTime(DEN_PRVNI_DATE));
    return $vek->y;
  }

  /**
   * Odstraní uživatele z židle a aktualizuje jeho práva.
   */
  public function vemZidli($zidle)
  {
    dbQuery('DELETE FROM r_uzivatele_zidle WHERE id_uzivatele='.$this->id().' AND id_zidle='.(int)$zidle);
    $this->aktualizujPrava();
  }

  //getters, setters

  function id()
  { return isset($this->u['id_uzivatele'])?$this->u['id_uzivatele']:null; }

  /**
   * Vrátí pohlaví ve tvaru 'm' nebo 'f'
   */
  function pohlavi()
  {
    return $this->u['pohlavi'];
  }

  function prezdivka()
  { return $this->u['login_uzivatele']; }

  /** ISO 3166-1 alpha-2 */
  function stat()
  {
    if($this->u['stat_uzivatele']==1)
      return 'CZ';
    elseif($this->u['stat_uzivatele']==2)
      return 'SK';
    else
      throw new Exception('Neznámé id státu v databázi.');
  }

  /**
   * surová data z DB
   */
  function rawDb()
  { return $this->u; }

  static function zId($id) {
    return self::zIds((int)$id)[0];
  }

  /**
   * Vrátí pole uživatelů podle zadaných ID. Lze použít pole nebo string s čísly
   * oddělenými čárkami.
   */
  static function zIds($ids) {
    if(is_array($ids)) {
      if(empty($ids)) return array();
      return self::nactiUzivatele('WHERE u.id_uzivatele IN('.dbQv($ids).')');
    } else if(preg_match('@[0-9,]+@', $ids)) {
      return self::nactiUzivatele('WHERE u.id_uzivatele IN('.$ids.')');
    } else {
      throw new Exception('neplatný formát množiny id');
    }
  }

  /**
   * Pokusí se načíst uživatele podle aktivní session případně z perzistentního
   * přihlášení.
   * @param string $klic klíč do $_SESSION kde očekáváme hodnoty uživatele
   * @return mixed objekt uživatele nebo null
   * @todo nenačítat znovu jednou načteného, cacheovat
   */
  public static function zSession($klic='uzivatel')
  {
    if(!session_id())
      session_start();
    if(isset($_SESSION[$klic]))
    {
      $u=new Uzivatel($_SESSION[$klic]);
      $u->klic=$klic;
      return $u;
    }
    elseif(isset($_COOKIE['gcTrvalePrihlaseni']))
    {
      $id=dbOneLineS('
        SELECT id_uzivatele
        FROM uzivatele_hodnoty
        WHERE random!="" AND random=$0',
        array($_COOKIE['gcTrvalePrihlaseni']));
      $id=$id?$id['id_uzivatele']:null;
      //die(dbLastQ());
      if(!$id) return null;
      //změna tokenu do budoucna proti hádání
      dbQuery('
        UPDATE uzivatele_hodnoty
        SET random="'.($rand=randHex(20)).'"
        WHERE id_uzivatele='.$id);
      setcookie('gcTrvalePrihlaseni',$rand,time()+3600*24*365,'/');
      return Uzivatel::prihlasId($id,$klic);
    }
    else
    {
      return null;
    }
  }

  /** Vrátí pole uživatelů sedících na židli s daným ID */
  public static function zZidle($id)
  {
    return self::nactiUzivatele('WHERE z.id_zidle = '.dbQv($id));
  }

  ///////////////////////////////// Protected //////////////////////////////////

  /**
   * Aktualizuje práva uživatele z databáze (protože se provedla nějaká změna)
   */
  protected function aktualizujPrava()
  {
    $p=dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
      LEFT JOIN r_prava_zidle pz USING(id_zidle)
      WHERE uz.id_uzivatele='.$this->id());
    $prava=array(); //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
    while($r=mysql_fetch_assoc($p))
      $prava[]=(int)$r['id_prava'];
    $_SESSION[$this->klic]['prava']=$prava;
    $this->u['prava']=$prava;
  }

  /**
   * Načte uživatele včetně práv z DB podle zadané where klauzule. Tabulka se
   * aliasuje jako u.*
   */
  protected static function nactiUzivatele($where)
  {
    $o=dbQuery('SELECT
        u.*,
        -- u.login_uzivatele,
        -- z.id_zidle,
        -- p.id_prava,
        GROUP_CONCAT(DISTINCT p.id_prava) as prava
      FROM uzivatele_hodnoty u
      LEFT JOIN r_uzivatele_zidle z ON(z.id_uzivatele=u.id_uzivatele)
      LEFT JOIN r_prava_zidle p ON(p.id_zidle=z.id_zidle)
      '.$where.'
      GROUP BY u.id_uzivatele');
    $uzivatele=array();
    while($r=mysql_fetch_assoc($o))
    {
      $u=new self($r);
      $u->u['prava']=explode(',',$u->u['prava']);
      $uzivatele[]=$u;
    }
    return $uzivatele;
  }

}

?>
