<?php

/**
 * Třída aktivity
 */
class Aktivita
{

  protected
    $a,     // databázový řádek s aktivitou
    $nova,  // jestli jde o nově uloženou aktivitu nebo načtenou z DB
    $ignorovane=array();  // ???

  const
    AJAXKLIC='aEditFormTest',  // název post proměnné, ve které jdou data, pokud chceme ajaxově testovat jejich platnost a čekáme json odpověď
    OBRKLIC='aEditObrazek',    // název proměnné, v které bude případně obrázek
    POSTKLIC='aEditForm',      // název proměnné (ve výsledku pole), v které bude editační formulář aktivity předávat data
    TEAMKLIC='aTeamForm',      // název post proměnné s formulářem pro výběr teamu
    ZAMEK=0x01;                // ignorování zamčení

  /**
   * Vytvoří aktivitu dle výstupu z databáze. Pokud výstup (např. položkou
   * "přihlášen") je vztažen vůči uživateli, je potřeba ho zadat teď jako $u,
   * později to nebude možné.
   */
  private function __construct($db)
  {
    if(!$db)
      throw new Exception('prázdný parametr konstruktoru');
    else if(is_array($db))
    {
      $this->a=$db;
      $this->nova=false;
    }
    else
      throw new Exception('nesprávný vstup konstruktoru (nepodporovaný typ)');
  }

  /**
   * Jestli je na aktivitu zakázáno uplatnit jakékoli procentuální slevy
   */
  function bezSlevy()
  {
    return (bool)$this->a['bez_slevy'];
  }

  /**
   * Cena aktivity čitelná člověkem, poplatná aktuálnímu okamžiku. V případě
   * uvedení uživatele vrací pro něj specifickou cenu.
   */
  function cena(Uzivatel $u=null)
  {
    if(!($this->cenaZaklad()>0))
      return 'zdarma';
    else if($this->a['bez_slevy'])
      return round($this->cenaZaklad()).'&thinsp;Kč';
    else if($u && $u->gcPrihlasen())
      return round($this->cenaZaklad()*$u->finance()->slevaAktivity()).'&thinsp;Kč';
    else if(SLEVA_AKTIVNI)
      return round($this->cenaZaklad()*0.8).'&thinsp;Kč / '.round($this->cenaZaklad()*0.6).'&thinsp;Kč';
    else
      return round($this->cenaZaklad()*1.0).'&thinsp;Kč / '.round($this->cenaZaklad()*0.8).'&thinsp;Kč';
  }

  /** Základní cena aktivity */
  function cenaZaklad() {
    return $this->a['cena'];
  }

  /** Vrací datum ve stylu Pátek 14:00-18:00 */
  function denCas() {
    return $this->zacatek()->format('l G:i').'–'.$this->konec()->format('G:i');
  }

  /**
   * Vrátí HTML kód editoru aktivit určený pro vytváření a editaci aktivity.
   * Podle nastavení $a buď aktivitu edituje nebo vytváří.
   */
  static function editor(Aktivita $a=null)
  {
    return self::editorParam($a);
  }

  /**
   * Vrátí HTML kód omezeného editoru který může používat vypravěč aktivity např
   */
  function editorVypravec()
  {
    return self::editorParam($this,array('popis','obrazek'));
  }

  /**
   * Vrátí v chyby v JSON formátu (pro ajax) nebo FALSE pokud žádné nejsou
   */
  static function editorChybyJson()
  {
    //if(!$_POST[self::AJAXKLIC]) ?
    $a=$_POST[self::POSTKLIC];
    return json_encode(array('chyby'=>self::editorChyby($a)));
  }

  /**
   * Vrátí, jestli se volající stránka snaží získat JSON data pro ověření formu
   */
  static function editorTestJson()
  {
    if(isset($_POST[self::AJAXKLIC]))
      return true;
    else
      return false;
  }

  /**
   * Zpracuje data odeslaná formulářem s vloženým editorem
   * @return vrací null pokud se nic nestalo nebo aktualizovaný objekt Aktivita,
   *   pokud k nějaké aktualizaci došlo.
   */
  static function editorZpracuj()
  {
    if(!isset($_POST[self::POSTKLIC])) return null;
    $a=$_POST[self::POSTKLIC];
    if(empty($a['url_akce']) && !empty($_POST[self::POSTKLIC.'staraUrl'])) // v případě nezobrazení tabulky a tudíž chybějícího text. pole s url (viz šablona) se použije hidden pole s původní url
      $a['url_akce']=$_POST[self::POSTKLIC.'staraUrl'];
    $a['bez_slevy'] = (int)!empty($a['bez_slevy']); //checkbox pro "bez_slevy"
    $a['teamova']   = (int)!empty($a['teamova']);   //checkbox pro "teamova"
    $a['team_min']  = $a['teamova'] ? (int)$a['team_min'] : null;
    $a['team_max']  = $a['teamova'] ? (int)$a['team_max'] : null;
    // u teamových aktivit se kapacita ignoruje - později se nechá jak je nebo přepíše minimem, pokud jde o novou aktivitu
    if($a['teamova']) unset($a['kapacita'], $a['kapacita_f'], $a['kapacita_m']);
    //if(self::editorChyby($a)) return false; //řeší ajax (?)
    // přepočet času
    $a['zacatek'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['zacatek'].'H'))->formatDb();
    $a['konec'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['konec'].'H'))->formatDb();
    unset($a['den']);
    if(!$a['patri_pod'] && $a['id_akce'])
    { // editace jediné aktivity
      dbInsertUpdate('akce_seznam',$a);
      $aktivita=new Aktivita($a);
      $aktivita->zpracujObrazekPost();
      return $aktivita;
    }
    else if($a['patri_pod'])
    { // editace aktivity z rodiny instancí
      $doHlavni=array('url_akce','popis'); // věci, které se mají změnit jen u hlavní (master) instance
      $doAktualni=array('lokace','organizator','zacatek','konec'); // věci, které se mají změnit jen u aktuální instance
      $aktivita=new Aktivita($a);
      // (zbytek se změní v obou)
      // určení hlavní aktivity
      $idHlavni=dbOneCol('SELECT MIN(id_akce) FROM akce_seznam WHERE patri_pod='.(int)$a['patri_pod']);
      $patriPod=$a['patri_pod'];
      unset($a['patri_pod']);
      // změny v hlavní aktivitě
      $zmenyHlavni=array_diff_key($a,array_flip($doAktualni));
      $zmenyHlavni['id_akce']=$idHlavni;
      dbInsertUpdate('akce_seznam',$zmenyHlavni);
      // změny v konkrétní instanci
      $zmenyAktualni=array_diff_key($a,array_flip($doHlavni));
      dbInsertUpdate('akce_seznam',$zmenyAktualni);
      // změny u všech
      $zmenyVse=array_diff_key($a,array_flip(array_merge($doHlavni,$doAktualni)));
      unset($zmenyVse['patri_pod'],$zmenyVse['id_akce']); //id se nesmí updatovat!
      dbUpdate('akce_seznam',$zmenyVse,array('patri_pod'=>$patriPod));
      // obrázek
      $aktivita->zpracujObrazekPost();
      return $aktivita;
    }
    else
    { // vkládání nové aktivity
      // inicializace hodnot pro novou aktivitu
      $a['id_akce']=null;
      $a['rok']=ROK;
      if($a['teamova']) $a['kapacita'] = $a['team_max']; // při vytváření nové aktivity se kapacita inicializuje na max. teamu
      empty($a['nazev_akce'])?$a['nazev_akce']='(neurčený název)':0;
      // vložení, nahrání obrzáku
      dbInsertUpdate('akce_seznam',$a);
      $a['id_akce']=mysql_insert_id();
      $aktivita=new Aktivita($a);
      $aktivita->zpracujObrazekPost();
      $aktivita->nova=true;
      return $aktivita;
    }
  }

  function id()
  { return $this->a['id_akce']; }

  /** Nastaví omezení, která se mají ignorovat při manipulaci s aktivitami */
  function ignorovane($pole)
  {
    if(!is_array($pole)) throw new Excpetion('Nesprávně zadané parametry');
    $this->ignorovane=$pole;
  }

  /** Vytvoří novou instanci aktivity */
  function instanciuj()
  {
    $akt = dbOneLine('SELECT * FROM akce_seznam WHERE id_akce='.$this->id());
    //odstraníme id, url a popisek, abychom je nepoužívali/neduplikovali při vkládání
    //stav se vloží implicitní hodnota v DB
    unset($akt['id_akce'], $akt['url_akce'], $akt['popis'], $akt['stav'], $akt['zamcel']);
    if($akt['teamova']) $akt['kapacita'] = $akt['team_max'];
    if($akt['patri_pod']>0)
    { //aktivita už má instanční skupinu, použije se stávající
      dbInsert('akce_seznam', $akt);
    }
    else
    { //aktivita je zatím bez instanční skupiny - vytvoříme
      //todo lock
      $max=dbOneLine('SELECT max(patri_pod) as max FROM akce_seznam');
      $patriPod=$max['max']+1; //nové ID rodiny instancí
      $akt['patri_pod']=$patriPod;
      dbQuery('UPDATE akce_seznam SET patri_pod='.$patriPod.
        ' WHERE id_akce='.$this->id()); //update původní aktivity
      dbInsert('akce_seznam',$akt);
    }
  }

  /** Vrací celkovou kapacitu aktivity */
  protected function kapacita()
  {
    return $this->a['kapacita'] + $this->a['kapacita_m'] + $this->a['kapacita_f'];
  }

  function lokaceId()
  { return $this->a['lokace']; }

  function nazev()
  { return $this->a['nazev_akce']; }

  /**
   * Jestli objekt aktivity představuje nově vytvořený řádek v databázi, nebo
   * byl jenom z DB načten.
   * @return bool false - načtený z databáze, true - nový, vložený do databáze
   */
  function nova()
  { return $this->nova; }

  /**
   * Vrací absolutní adresu k obrázku aktivity. Ošetřeno cacheování.
   */
  function obrazek()
  {
    $url=URL_WEBU.'/files/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    $soub=__DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    if(is_file($soub))
      return $url.'?x='.base_convert(filemtime($soub),10,16);
    else
      return '';
  }

  /** Vrátí html kód s políčky určujícímí obsazenost */
  function obsazenostHtml()
  {
    $m = $this->prihlasenoMuzu(); // počty
    $f = $this->prihlasenoZen();
    $c = $m + $f;
    $km = $this->a['kapacita_m']; // kapacity
    $kf = $this->a['kapacita_f'];
    $ku = $this->a['kapacita'];
    $kc = $ku + $km + $kf;
    if(!$kc || !REG_AKTIVIT)
      return '';
    if(!$this->prihlasovatelna() && !$this->probehnuta()) //u proběhnutých aktivit se zobrazí čísla. Možno měnit.
      return " <span class=\"neprihlasovatelna\">($c/$kc)</span>";
    switch($this->volno())
    {
      case 'u':
      case 'x':
        return " ($c/$kc)";
      case 'f':
        return ' <span class="f">('.$f.'/'.$kf.')</span>'.
          ' <span class="m">('.$m.'/'.($km+$ku).')</span>';
      case 'm':
        return ' <span class="f">('.$f.'/'.($kf+$ku).')</span>'.
          ' <span class="m">('.$m.'/'.$km.')</span>';
    }
  }

  /**
   * Odemče hromadně zamčené aktivity a odhlásí ty, kteří nesestavili teamy.
   * Vrací počet odemčených teamů (=>uvolněných míst)
   */
  static function odemciHromadne() {
    $o = dbQuery('SELECT id_akce, zamcel FROM akce_seznam WHERE zamcel');
    $i = 0;
    while( list($aid, $uid) = mysql_fetch_row($o) ) {
      Aktivita::zId($aid)->odhlas(Uzivatel::zId($uid));
      $i++;
    }
    return $i;
    // uvolnění zámku je součástí odhlášení, pokud je sám -> done
  }

  /** Odhlásí uživatele z aktivity */
  function odhlas(Uzivatel $u) {
    // TODO kontroly? (např. jestli je aktivní přihlašování?)
    if($this->a['dite']) { // odhlášení z potomků
      self::zId($this->a['dite'])->odhlas($u);
    }
    $aid = $this->id();
    $uid = $u->id();
    dbQuery("DELETE FROM akce_prihlaseni WHERE id_uzivatele=$uid AND id_akce=$aid");
    dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='odhlaseni'");
    if(ODHLASENI_POKUTA_KONTROLA && aktivitaDoZacatkuH($this)<ODHLASENI_POKUTA1_H) //pokuta aktivní
      dbQueryS("INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$uid, id_akce=$aid, id_stavu_prihlaseni=4");
    if($this->a['zamcel'] == $uid)
      dbQuery("UPDATE akce_seznam SET zamcel=NULL WHERE id_akce=$aid");
    if($this->a['teamova'] && $this->prihlaseno()==1) // odhlašuje se poslední hráč
      dbQuery("UPDATE akce_seznam SET kapacita=team_max WHERE id_akce=$aid");
  }

  /**
   * Vrátí pole uživatelů, kteří jsou organizátory jakékoli ze skupiny instancí
   * aktivity. Pokud nemá instance, vrátí organizátory aktivity jak jsou.
   */
  function organizatoriSkupiny() {
    if($this->a['patri_pod']) {// má instance
      $uids = dbOneCol('SELECT GROUP_CONCAT(organizator) FROM akce_seznam WHERE patri_pod='.$this->a['patri_pod'].' GROUP BY patri_pod');
    } else {
      $uids = $this->a['organizator'];
    }
    return Uzivatel::zIds($uids);
  }

  /** Vrátí id organizátora aktivity */
  function orgId()
  { return $this->a['organizator']; }

  /** Vrátí specifické označení organizátora pro aktivitu tohoto typu */
  function orgTitul() {
    return dbOneCol('SELECT titul_orga FROM akce_typy WHERE id_typu='.$this->a['typ']);
  }

  /**
   * Vrátí formátovaný (html) popisek aktivity
   */
  function popis()
  {
    $popis=$this->a['popis'];
    $popis=strtr($popis,array(
      ' - '=>' – ',
      '...'=>'…'));
    $popis=preg_replace('@([^=])"([^"\n<>]+)"([^>])@', '$1„$2“$3', $popis); //uvozovky
    $popis=Markdown($popis);
    $popis=preg_replace('@([^=">])(http://[a-zA-Z0-9/\?\.=\-_]+)@',
      '$1<a href="$2" onclick="return!window.open(this.href)">$2</a>', $popis);
    return $popis;
  }

  /** Přihlásí uživatele na aktivitu */
  function prihlas(Uzivatel $u, $ignorovat = 0)
  {
    // kontroly
    if($this->prihlasen($u))          return;
    if(!maVolno($u->id(), $this->a))  throw new Chyba(hlaska('kolizeAktivit')); // TODO převést na metodu uživatele
    if(!$u->gcPrihlasen())            throw new Exception('Nemáš aktivní přihlášku na GameCon.');
    if(!REG_AKTIVIT)                  throw new Exception('Přihlašování není spuštěno.');
    if(!$this->prihlasovatelna())     throw new Exception('Aktivita není otevřena pro přihlašování.');
    if($this->volno()!='u' && $this->volno()!=$u->pohlavi()) throw new Chyba(hlaska('plno'));
    if($this->a['zamcel'] && !($ignorovat&self::ZAMEK)) throw new Chyba(hlaska('zamcena'));
    // vložení do db
    if($this->a['dite']) {
      self::zId($this->a['dite'])->prihlas($u);
    }
    $aid = $this->id();
    $uid = $u->id();
    if($this->a['teamova'] && $this->prihlaseno()==0)
      dbQuery("UPDATE akce_seznam SET zamcel=$uid WHERE id_akce=$aid");
    dbQuery("INSERT INTO akce_prihlaseni SET id_uzivatele=$uid, id_akce=$aid");
    dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='prihlaseni'");
    if(ODHLASENI_POKUTA_KONTROLA) //pokud by náhodou měl záznam za pokutu a přihlásil se teď, tak smazat
      dbQueryS('DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0
        AND id_akce=$1 AND id_stavu_prihlaseni=4', array($uid, $aid));
  }

  /** Jestli je uživatel  přihlášen na tuto aktivitu */
  function prihlasen(Uzivatel $u)
  {
    return strpos($this->prihlaseni(), ','.$u->id().$u->pohlavi()) !== false;
  }

  /**
   * Vrátí serializovaný seznam přihlášených a pokud takový neexistuje, načte
   * ho. Formát seznamu je: ,1204m0,864f2,742f1,...,1001m1, kde čísla jsou id
   * uživatelů, písmena pohlaví a čísla z pohlavím stav přihlášení.
   */
  private function prihlaseni()
  {
    if(!array_key_exists('prihlaseni', $this->a))
      throw new Exception ('Nenačteny počty přihlášených do aktivity.');
    return $this->a['prihlaseni'];
  }

  /** Počet přihlášených */
  protected function prihlaseno()
  {
    if($p = $this->prihlaseni())
      return substr_count($p, ',') - 1;
    else
      return 0;
  }

  protected function prihlasenoMuzu()
  {
    return substr_count($this->prihlaseni(), 'm');
  }

  protected function prihlasenoZen()
  {
    return substr_count($this->prihlaseni(), 'f');
  }

  /**
   * Vrátí stav přihlášení uživatele na aktivitu. Pokud není přihlášen, vrací
   * hodnotu -1.
   */
  private function prihlasenStav(Uzivatel $u)
  {
    $prihlaseni = $this->prihlaseni();
    $usymbol = ','.$u->id().$u->pohlavi();
    $pos = strpos($prihlaseni, $usymbol);
    if($pos !== false) {
      return (int)substr($prihlaseni, $pos+strlen($usymbol), 1);
    } else {
      return -1;
    }
  }

  /** Zdali chceme, aby se na aktivitu bylo možné běžně přihlašovat */
  function prihlasovatelna()
  {
    //stav 4 je rezervovaný pro viditelné nepřihlašovatelné aktivity
    return(REG_AKTIVIT && $this->a['stav']==1);
  }

  /**
   * Vrátí html kód pro přihlášení / odhlášení / informaci o zaplněnosti pro
   * daného uživatele. Pokud není zadán, vrací prázdný řetězec.
   * @todo v rodině instancí maximálně jedno přihlášení?
   * @todo konstanty pro jména POST proměnných? viz prihlasovatkoZpracuj
   */
  function prihlasovatko(Uzivatel $u = null)
  {
    if(REG_AKTIVIT && $u && $u->gcPrihlasen() && $this->a['typ'] && $this->prihlasovatelna())
    {
      if( ($stav = $this->prihlasenStav($u)) > -1 )
      {
        if($stav==0)
          return '<a href="javascript:document.getElementById(\'odhlasit'.
            $this->id().'\').submit()">odhlásit</a><form '.
            'id="odhlasit'.$this->id().'" method="post" '.
            'style="position:absolute"><input type="hidden" name="odhlasit" '.
            'value="'.$this->id().'" /></form>';
        if($stav==1) return '<em>účast</em>';
        if($stav==2) return '<em>jako náhradník</em>';
        if($stav==3) return '<em>neúčast</em>';
        if($stav==4) return '<em>pozdní odhlášení</em>';
      }
      elseif($this->a['organizator'] == $u->id())
      {
        return '';
      }
      elseif($this->a['zamcel'])
      {
        return '&#128274;'; //zámek
      }
      else
      {
        $volno = $this->volno();
        if($volno=='u' || $volno==$u->pohlavi())
          return '<a href="javascript:document.getElementById(\'prihlasit'.
            $this->id().'\').submit()">přihlásit</a><form '.
            'id="prihlasit'.$this->id().'" method="post" '.
            'style="position:absolute"><input type="hidden" name="prihlasit" '.
            'value="'.$this->id().'" /></form>';
        if($volno=='f' && !$pouzeOdkaz)
          return 'pouze ženská místa';
        if($volno=='m' && !$pouzeOdkaz)
          return 'pouze mužská místa';
        /*if($volno=='x')
          return 'plná kapacita';*/
        return '';
      }
    }
    else
    {
      return '';
    }
  }

  /** Zpracuje post data z přihlašovátka. Pokud došlo ke změně, vyvolá reload */
  static function prihlasovatkoZpracuj(Uzivatel $u = null)
  {
    if(post('prihlasit')) {
      self::zId(post('prihlasit'))->prihlas($u);
      back();
    }
    if(post('odhlasit')) {
      self::zId(post('odhlasit'))->odhlas($u);
      back();
    }
  }

  /**
   * Dávkově přihlásí uživatele na tuto aktivitu a (bez postihu) odhlásí
   * aktivity, které s novou aktivitou kolidují
   * @param $uids pole s ID uživatelů
   */
  function prihlasPrepisHromadne($uids)
  {
    $pKolize=dbOneCol('
      SELECT GROUP_CONCAT(id_akce)
      FROM akce_seznam
      WHERE rok='.ROK.'
      AND NOT ( '.$this->a['konec'].' <= zacatek OR konec <= '.$this->a['zacatek'].' )
    ');
    dbQuery("DELETE FROM akce_prihlaseni WHERE id_akce IN($pKolize) AND id_uzivatele IN(".implode(',',$uids).')');
    dbQuery('INSERT INTO akce_prihlaseni(id_akce,id_uzivatele) VALUES ('.$this->id().','.implode('),('.$this->id().',',$uids).')');
  }

  /** Zdali už aktivita začla a proběhla (rozhodný okamžik je vyjetí seznamů
   *  přihlášených na infopultu) */
  function probehnuta()
  {
    return $this->a['stav']==2;
  }

  function typ()
  { return $this->a['typ']; }

  /** Vrátí typ volných míst na aktivitě */
  function volno()
  {
    $m = $this->prihlasenoMuzu();
    $f = $this->prihlasenoZen();
    $ku=$this->a['kapacita'];
    $km=$this->a['kapacita_m'];
    $kf=$this->a['kapacita_f'];
    if(($ku+$km+$kf)<=0)
      return 'u'; //aktivita bez omezení
    if( $m+$f >= $ku+$km+$kf )
      return 'x'; //beznadějně plno
    if( $m >= $ku+$km )
      return 'f'; //muži zabrali všechna univerzální i mužská místa
    if( $f >= $ku+$kf )
      return 'm'; //LIKE WTF? (opak předchozího)
    //else
    return 'u'; //je volno a žádné pohlaví nevyžralo limit míst
  }

  /**
   * Vrátí formulář pro výběr teamu na aktivitu. Pokud není zadán uživatel,
   * vrací nějakou false ekvivalentní hodnotu.
   */
  function vyberTeamu(Uzivatel $u = null) {
    if(!$u || $this->a['zamcel']!=$u->id()) return null;
    ob_start();
    ?>
    <input type="text" value="<?=$u->id()?>" disabled="disabled">
    <form method="post">
    <?php
    for($i=0; $i < $this->kapacita()-1; $i++) {
      echo '<input name="'.self::TEAMKLIC.'['.$i.']" type="text">';
      if($i >= $this->a['team_min']-1) // -1 za leadera, prevUntil kvůli jquery ui bugu (přidává element)
        echo ' <a href="#" onclick="$(this).prevUntil(\'br\').fadeOut(function(){ $(this).val(-1); }); $(this).fadeOut(); return false;">odebrat</a>';
      echo '<br>';
    }
    ?>
    <input type="hidden" name="<?=self::TEAMKLIC.'Aktivita'?>" value="<?=$this->id()?>">
    <input type="button" value="potvrdit">
    </form>
    <script>
    (function(){
      var form = $('script:last').prev();
      var button = form.find('input[type=button]');
      button.click(function(){
        button.prop("disabled", true);
        $.post(document.URL, form.serialize(), function(data){
          if(data.chyby.length) {
            alert(data.chyby);
            button.prop("disabled", false);
          } else {
            location.reload();
          }
        }, 'json');
      });
      form.find('input[type=text]').autocomplete({
        source: 'ajax-omnibox',
        minLength: 2,
        autoFocus: true, // automatický výběr první hodnoty, aby uživatel mohl zmáčknout rovnou enter
        focus: function(event,ui) {
          event.preventDefault(); // neměnit text inputu při výběru
        }
      });
    })();
    </script>
    <?php
    return ob_get_clean();
  }

  /**
   * Zpracuje data formuláře pro výběr teamu a vrátí případné chyby jako json.
   * Ukončuje skript.
   * @todo kontrola, jestli nezamčel moc míst
   */
  static function vyberTeamuZpracuj(Uzivatel $leader = null) {
    if( !$leader || !($t = post(self::TEAMKLIC)) ) return;
    $chyby = array();
    $prihlaseni = array(); // pro rollback
    $chybny = null; // pro uživatele jehož jméno se zobrazí v rámci chyby
    try
    {
      $a = Aktivita::zId(post(self::TEAMKLIC.'Aktivita'));
      if($leader->id() != $a->a['zamcel']) throw new Exception('Nejsi teamleader.');
      $up = post(self::TEAMKLIC);
      $zamceno = 0;
      foreach($up as $i=>$uid) {
        if($uid==-1 || !$uid)
          unset($up[$i]);
        if($uid==-1)
          $zamceno++;
      }
      // kontrola a pokus o přihlášení jednotlivých členů
      $clenove = Uzivatel::zIds($up);
      if(count($clenove) != count($up)) throw new Exception('Zadáno neplatné id uživatele.');
      foreach($clenove as $clen) {
        $chybny = $clen;
        $a->prihlas($clen, self::ZAMEK);
        $prihlaseni[] = $clen;
      }
      // maily přihlášeným
      $mail = new GcMail(hlaskaMail('prihlaseniTeamMail',
        $leader, $leader->jmenoNick(), $a->nazev(), $a->denCas()
      )); // TODO link na stránku aktivity
      $mail->predmet('Přihláška na '.$a->nazev()); // TODO korektní pády atd
      foreach($clenove as $clen) {
        $mail->adresat($clen->mail());
        $mail->odeslat();
      }
      // hotovo, odemčít aktivitu a snížit počet míst
      $mist = $a->a['kapacita'] - $zamceno;
      dbQuery("UPDATE akce_seznam SET zamcel=null, kapacita=$mist WHERE id_akce={$a->id()}");
    }
    catch(Exception $e)
    {
      // rollback
      foreach($prihlaseni as $clen)
        $a->odhlas($clen); // TODO bez pokut apod…
      // zobrazení
      if($chybny)
        $chyby[] = 'Nelze, uživateli '.$chybny->jmenoNick().'('.$chybny->id().')'." se při přihlašování objevila chyba:\n• ".$e->getMessage();
      else
        $chyby[] = $e->getMessage();
    }
    echo json_encode(array('chyby'=>$chyby));
    die();
  }

  /** Vrátí DateTime objekt začátku aktivity */
  function zacatek() {
    if(is_string($this->a['zacatek']))
      $this->a['zacatek'] = new DateTimeCz($this->a['zacatek']);
    return $this->a['zacatek'];
  }

  /** Vrátí DateTime objekt konce aktivity */
  function konec() {
    if(is_string($this->a['konec']))
      $this->a['konec'] = new DateTimeCz($this->a['konec']);
    return $this->a['konec'];
  }

  /**
   * Pokusí se vyčíst aktivitu z dodaného ID. Vrátí aktivitu nebo null
   * @todo optimalizovaný select (viz zUrl...) ?
   */
  static function zId($id)
  {
    if((int)$id)
    {
      $a=dbOneLine('SELECT
          a.*, -- speciální selecty kvůli sdílené url a popisu u aktivit s více instancemi
          IF(a.patri_pod,(SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod=a.patri_pod),url_akce) url_akce,
          IF(a.patri_pod,(SELECT MAX(popis) FROM akce_seznam WHERE patri_pod=a.patri_pod),popis) popis,
          CONCAT(",",GROUP_CONCAT(ap.id_uzivatele,u.pohlavi,ap.id_stavu_prihlaseni),",") AS prihlaseni
        FROM akce_seznam a
        LEFT JOIN akce_prihlaseni ap ON(ap.id_akce = a.id_akce)
        LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele = ap.id_uzivatele)
        WHERE a.id_akce='.(int)$id.'
        GROUP BY a.id_akce');
      if(!$a) return null;
      return new Aktivita($a);
    }
    return null;
  }

  /** Vrátí pole aktivit které se letos zobrazí v programu */
  static function zProgramu() {
    $o = dbQuery('
      SELECT a.*,
        CONCAT(",",GROUP_CONCAT(ap.id_uzivatele,u.pohlavi,ap.id_stavu_prihlaseni),",") AS prihlaseni
      FROM akce_seznam a
      LEFT JOIN akce_prihlaseni ap ON(ap.id_akce = a.id_akce)
      LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele = ap.id_uzivatele)
      JOIN akce_lokace l ON (a.lokace=l.id_lokace) -- poradi lokaci v programu
      WHERE a.rok = '.ROK.' AND a.zacatek AND a.stav IN(1,2,3,4)
      GROUP BY a.id_akce
      ORDER BY DAY(a.zacatek), l.poradi, HOUR(a.zacatek), a.nazev_akce
    ');
    $p = array();
    while($r = mysql_fetch_assoc($o)) {
      unset($r['popis'], $r['url_akce']);
      $p[] = new self($r);
    }
    return new ArrayIterator($p);
  }

  /**
   * Vrátí aktivity z rozmezí
   * @todo skutečně implementovat flags nebo zrušit
   */
  const JEN_VOLNE         = 0x01;
  const ZACATEK_V_ROZMEZI = 0x00;
  const CELE_V_ROZMEZI    = 0x02;
  static function zRozmezi(DateTime $od, DateTime $do, $flags) {
    $jenVolne = $flags&0x01;
    $cele     = $flags&0x02;
    $o=self::nactiSkupinuRucne("
        a.zacatek BETWEEN '{$od->formatDb()}' AND '{$do->formatDb()}'
      ", null, 'a.zacatek', ($jenVolne ? 'pocet < kapacita_celkova' : null)
    );
    $a=array();
    while($r=mysql_fetch_assoc($o))
      $a[]=new self($r);
    return $a;
  }

  /**
   * Vrátí pole instancí s danou url (jen ty letošní veřejně viditelné)
   * @param $url url aktivity
   * @param $typ url typu
   * @todo iterátor
   */
  static function zUrlViditelne($url, $typ) {
    $o = dbQueryS('
      SELECT af.*,
        a.url_akce, a.popis, -- rozkopírování (přepsání) hodnot z hlavní instance do všech instancí
        CONCAT(",",GROUP_CONCAT(ap.id_uzivatele,u.pohlavi,ap.id_stavu_prihlaseni),",") AS prihlaseni
      FROM akce_seznam a
      JOIN akce_typy t ON(t.id_typu=a.typ)
      JOIN akce_seznam af ON(af.id_akce = a.id_akce OR af.patri_pod AND af.patri_pod = a.patri_pod) -- připojení instancí k výchozí aktivitě
      LEFT JOIN akce_prihlaseni ap ON(ap.id_akce = af.id_akce)
      LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele = ap.id_uzivatele) -- kvůli pohlaví
      WHERE t.url_typu = $1
      AND a.url_akce = $2
      AND a.stav IN(1,2,4)
      AND a.rok = $3
      GROUP BY af.id_akce
      ORDER BY af.zacatek, af.id_akce
      ', array($typ, $url, ROK_AKTUALNI));
    $m = array();
    while($a = mysql_fetch_assoc($o)) {
      $m[] = new self($a);
    }
    return $m;
  }


  ////////////////////
  // Protected věci //
  ////////////////////

  /**
   * Načte skupinu aktivit z databáze, vrátí databázový soubor pro ruční
   * přepracování. Slouží jako surový databázový dotaz, který je v každé
   * odvozené třídě aliasován s adekvátními nutnými operacemi kolem.
   *
   * @param Uzivatel $u přidá do výsledku sloupec "přihlášen", pakliže je
   *   uživatel $u přihlášen na danou aktivitu
   * @param string $sqlOrderBy volitelně část dotazu po ORDER BY
   * @param string $sqlWhere normální sql WHERE na filtrování
   */
  protected static function nactiSkupinuRucne($sqlWhere,$u=null,$sqlOrderBy=null,$sqlHaving=null)
  {
    $prihlSql1=$prihlSql2='';
    if($u instanceof Uzivatel)
    { //přídavná část SQL dotazu pro rozlišení akcí, kde je uživatel přihlášen
      $prihlSql1=', ap.prihlasen';
      $prihlSql2='
        LEFT JOIN (
          SELECT id_akce, 1 as prihlasen FROM akce_prihlaseni WHERE id_uzivatele='.$u->id().'
        ) as ap ON(a.id_akce=ap.id_akce) -- aktivni prihlaseni daneho uzivatele';
    }
    $o=dbQuery('
      SELECT a.*,
        COUNT(p.id_uzivatele) as pocet,
        COUNT(NULLIF(p.id_uzivatele AND u.pohlavi="f",false)) as pocet_f,
        COUNT(NULLIF(p.id_uzivatele AND u.pohlavi="m",false)) as pocet_m,
        (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita_celkova,
        o.jmeno_uzivatele, o.prijmeni_uzivatele, o.login_uzivatele
        '.$prihlSql1.'
      FROM akce_seznam a
      LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce) -- počet lidí
      LEFT JOIN uzivatele_hodnoty u ON (u.id_uzivatele=p.id_uzivatele) -- kvůli groupu dle pohlaví
      LEFT JOIN uzivatele_hodnoty o ON (a.organizator=o.id_uzivatele) -- kvůli nicku organizátora
      '.$prihlSql2.'
      WHERE '.$sqlWhere.'
      GROUP BY a.id_akce '.
      ($sqlHaving?' HAVING '.$sqlHaving:'').
      ($sqlOrderBy?' ORDER BY '.$sqlOrderBy:'')
    );
    return $o;
  }

  /**
   * Vrátí pole obsahující chyby znemožňující úpravu aktivity. Hodnoty jsou
   * chybové hlášky. Význam indexů ndef (todo možno rozšířit).
   * @param $a Pole odpovídající strukturou vkládanému (upravovanému) řádku DB,
   * podle toho nemá (má) id aktivity
   */
  protected static function editorChyby($a)
  {
    $chyby=array();
    // hack - převod začátku a konce z formátu formu na legitimní formát data a času
    $a['zacatek'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['zacatek'].'H'))->formatDb();
    $a['konec'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['konec'].'H'))->formatDb();
    if(!maVolno($a['organizator'], $a, isset($a['id_akce'])?$a['id_akce']:null))
    {
      $k=maVolnoKolize();
      $k=current($k);
      $chyby[]='Organizátor má v danou dobu '.$k['nazev_akce'].' ('.datum2($k).')';
    }

    return $chyby;
  }

  /**
   * Vrátí html kód editoru, je možné parametrizovat, co se pomocí něj dá
   * měnit (todo)
   */
  protected static function editorParam(Aktivita $a=null,$omezeni=array())
  {
    $aktivita=$a?$a->a:null; //databázový řádek
    // inicializace šablony
    $xtpl=new XTemplate(__DIR__.'/editor.xtpl');
    $xtpl->assign('fields',self::POSTKLIC); // název proměnné (pole) v kterém se mají posílat věci z formuláře
    $xtpl->assign('ajaxKlic',self::AJAXKLIC);
    //  $xtpl->assign('readonly','disabled');
    $xtpl->assign('obrKlic', self::OBRKLIC);
    $xtpl->assign('obrKlicUrl', self::OBRKLIC.'Url');
    $xtpl->assign('obrKlicOrez', self::OBRKLIC.'Orez');
    $xtpl->assign('urlObrazku',$a?$a->obrazek():'');
    if($a) $xtpl->assign($a->a);
    // načtení lokací
    if(!$omezeni || !empty($omezeni['lokace']))
    {
      $q=dbQuery('SELECT * FROM akce_lokace ORDER BY poradi');
      while($r=mysql_fetch_assoc($q))
        $xtpl->assign('sel',$a && $aktivita['lokace']==$r['id_lokace']?'selected':'') xor
        $xtpl->assign($r) xor
        $xtpl->parse('upravy.tabulka.lokace');
    }
    // editace dnů + časů
    if(!$omezeni || !empty($omezeni['zacatek']))
    {
      // načtení dnů
      $xtpl->assign('sel',$a && !$a->zacatek() ? 'selected' : '');
      $xtpl->assign('den',0);
      $xtpl->assign('denSlovy','(neurčeno)');
      $xtpl->parse('upravy.tabulka.den');
      $aDen = $a && $a->zacatek() ? $a->zacatek()->format('l') : PHP_INT_MAX;
      foreach($GLOBALS['PROGRAM_DNY'] as $den=>$denSlovy) {
        $den = (new DateTimeCz(PROGRAM_OD))->add(new DateInterval('P'.$den.'D'));
        $xtpl->assign('sel', $den->format('l')==$aDen ? 'selected' : '');
        $xtpl->assign('den', $den->format('Y-m-d'));
        $xtpl->assign('denSlovy',$denSlovy);
        $xtpl->parse('upravy.tabulka.den');
      }
      // načtení časů
      $aZacatek = $a && $a->zacatek() ? $a->zacatek()->format('G') : PHP_INT_MAX;
      $aKonec = $a && $a->konec() ? $a->konec()->sub(new DateInterval('PT1H'))->format('G') : PHP_INT_MAX;
      for($i=$GLOBALS['PROGRAM_ZACATEK']=8;$i<$GLOBALS['PROGRAM_KONEC']=24;$i++)
      {
        $xtpl->assign('sel', $aZacatek==$i ? 'selected' : '');
        $xtpl->assign('zacatek',$i);
        $xtpl->assign('zacatekSlovy',$i.':00');
        $xtpl->parse('upravy.tabulka.zacatek');
        $xtpl->assign('sel', $aKonec==$i ? 'selected' : '');
        $xtpl->assign('konec', $i+1);
        $xtpl->assign('konecSlovy',($i+1).':00');
        $xtpl->parse('upravy.tabulka.konec');
      }
    }
    // načtení organizátorů
    if(!$omezeni || !empty($omezeni['organizator']))
    {
      $q=dbQuery('SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
        FROM uzivatele_hodnoty u
        LEFT JOIN r_uzivatele_zidle z USING(id_uzivatele)
        LEFT JOIN r_prava_zidle p USING(id_zidle)
        WHERE p.id_prava='.P_ORG_AKCI.'
        GROUP BY u.id_uzivatele
        ORDER BY u.login_uzivatele');
      $xtpl->assign('organizator','0'); //nejdřív nabídka bez orga
      $xtpl->assign('organizatorJmeno','(bez organizátora)');
      $xtpl->parse('upravy.tabulka.organizator');
      while($r=mysql_fetch_assoc($q))
        $xtpl->assign('sel',$a && $r['id_uzivatele']==$aktivita['organizator']?'selected':'') xor
        $xtpl->assign('organizator',$r['id_uzivatele']) xor
        $xtpl->assign('organizatorJmeno',jmenoNick($r)) xor
        $xtpl->parse('upravy.tabulka.organizator');
    }
    // načtení typů
    if(!$omezeni || !empty($omezeni['typ']))
    {
      $xtpl->assign(array('sel'=>'','id_typu'=>0,'typ_1p'=>'(bez typu – organizační)'));
      $xtpl->parse('upravy.tabulka.typ');
      $q=dbQuery('SELECT * FROM akce_typy');
      while($r=mysql_fetch_assoc($q))
        $xtpl->assign('sel',$a && $r['id_typu']==$aktivita['typ']?'selected':'') xor
        $xtpl->assign($r) xor
        $xtpl->parse('upravy.tabulka.typ');
    }
    // výstup
    if(empty($omezeni)) $xtpl->parse('upravy.tabulka'); // todo ne pokud je bez omezení, ale pokud je omezeno všechno. Pokud jen něco, doprogramovat selektivní omezení pro prvky tabulky i u IFů nahoře a vložit do šablony
    $xtpl->parse('upravy');
    return $xtpl->text('upravy');
  }

  /**
   * Zpracuje obrázek poslaný formulářem. Formulář musí mít typ:
   *   form method="post" enctype="multipart/form-data"
   * aby to fungovalo.
   */
  protected function zpracujObrazekPost()
  {
    // todo změna url (fixme). Hack
    $cesta=__DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/aktivity/';
    if($_POST[self::POSTKLIC.'staraUrl']!=$this->a['url_akce'])
      if(is_file($cesta.$_POST[self::POSTKLIC.'staraUrl'].'.jpg'))
         rename( $cesta.$_POST[self::POSTKLIC.'staraUrl'].'.jpg', $cesta.$this->a['url_akce'].'.jpg');
    // aktualizace obrázku
    $soubor = __DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    $o = null;
    if(!empty($_FILES[self::OBRKLIC]['tmp_name'])) { // poslán obrázek pro aktualizaci
      move_uploaded_file($_FILES[self::OBRKLIC]['tmp_name'], $soubor);
      $o = Obrazek::zJpg($soubor);
    }
    if($url = post(self::OBRKLIC.'Url')) {
      $o = Obrazek::zUrl($url, $soubor);
    }
    // resize
    if($o!==null) {
      $r = 4/3;
      $orez = post(self::OBRKLIC.'Orez');
      if($orez == 'stretch')  $o->ratio($r);
      elseif($orez == 'fit')  $o->ratioFit($r);
      else                    $o->ratioFill($r);
      $o->reduce(400, 300);
      $o->uloz();
    }
  }

}
