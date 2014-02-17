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
    POSTKLIC='aEditForm',      // název proměnné (ve výsledku pole), v které bude editační formulář aktivity předávat data
    OBRKLIC='aEditObrazek',    // název proměnné, v které bude případně obrázek
    AJAXKLIC='aEditFormTest',  // název post proměnné, ve které jdou data, pokud chceme ajaxově testovat jejich platnost a čekáme json odpověď
    OBRAZEK_MAXW=400;          // maximální šířka obrázku aktivity (větší se resizne na tuto šířku)

  /**
   * Vytvoří aktivitu dle výstupu z databáze. Pokud výstup (např. položkou
   * "přihlášen") je vztažen vůči uživateli, je potřeba ho zadat teď jako $u,
   * později to nebude možné.
   */
  function __construct($db)
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
    $a['bez_slevy']=empty($a['bez_slevy'])?0:1; //checkbox pro "bez_slevy"
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
  {
    return $this->a['id_akce'];
  }

  /** Nastaví omezení, která se mají ignorovat při manipulaci s aktivitami */
  function ignorovane($pole)
  {
    if(!is_array($pole)) throw new Excpetion('Nesprávně zadané parametry');
    $this->ignorovane=$pole;
  }

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
    if(!$this->a['kapacita_celkova'] || !REG_AKTIVIT)
      return '';
    if(!$this->prihlasovatelna() && !$this->probehnuta()) //u proběhnutých aktivit se zobrazí čísla. Možno měnit.
      return ' <span class="neprihlasovatelna">('.$this->a['pocet'].'/'.$this->a['kapacita_celkova'].')</span>';
    switch($this->volno())
    {
      case 'u':
      case 'x':
        return(' ('.$this->a['pocet'].'/'.$this->a['kapacita_celkova'].')');
      case 'f':
        return(' <span class="f">('.$this->a['pocet_f'].'/'.$this->a['kapacita_f'].')</span>'.
          ' <span class="m">('.$this->a['pocet_m'].'/'.($this->a['kapacita_m']+$this->a['kapacita']).')</span>');
      case 'm':
        return(' <span class="f">('.$this->a['pocet_f'].'/'.($this->a['kapacita_f']+$this->a['kapacita']).')</span>'.
          ' <span class="m">('.$this->a['pocet_m'].'/'.$this->a['kapacita_m'].')</span>');
    }
  }

  /** Vrátí id organizátora aktivity */
  function orgId()
  { return $this->a['organizator']; }

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

  /**
   * Vrátí čitelné jméno organizátora
   * @todo obecně by bylo dobré vracet objekt Uživatel, ale do budoucna by bylo
   *    nutné kvůli tomu překopat konstruktory, aby bylo garantováno načtení a
   *    obecně vyřešit celé ORM.
   *    Navíc bude pravděpodobně dál řešeno, pokud se změní vztah org:aktivita
   *    z 1:N na M:N
   * @throws Exception jestliže aktivita nebyla načtena tak, aby obsahovala
   *    potřebné informace.
   */
  function orgJmeno()
  {
    if(!array_key_exists('jmeno_uzivatele',$this->a))
      throw new Exception('Nenačteny údaje organizátora.');
    return $this->a['login_uzivatele'];
  }

  /** Zdali chceme, aby se na aktivitu bylo možné běžně přihlašovat */
  function prihlasovatelna()
  {
    //stav 4 je rezervovaný pro viditelné nepřihlašovatelné aktivity
    return(REG_AKTIVIT && $this->a['stav']==1);
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

  /** Vrátí typ volných míst na aktivitě */
  function volno()
  {
    if(!isset($this->a['pocet_m'])) throw new Exception ('Nenačteny počty přihlášených do aktivity.');
    $m=$this->a['pocet_m'];
    $f=$this->a['pocet_f'];
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

  public function typ()
  { return $this->a['typ']; }

  /** Vrátí DateTime objekt začátku aktivity */
  function zacatek() {
    return $this->a['zacatek'] ? new DateTimeCz($this->a['zacatek']) : null;
  }

  /** Vrátí DateTime objekt konce aktivity */
  function konec() {
    return $this->a['konec'] ? new DateTimeCz($this->a['konec']) : null;
  }

  /** Surový řádek z databáze - hack, používat jen pro zpětnou kompatibilitu */
  function rawDb() {
    return $this->a;
  }

  /**
   * Pokusí se vyčíst aktivitu z dodaného ID. Vrátí aktivitu nebo null
   */
  static function zId($id)
  {
    if((int)$id)
    {
      $a=dbOneLine('SELECT
          *, -- speciální selecty kvůli sdílené url a popisu u aktivit s více instancemi
          IF(a.patri_pod,(SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod=a.patri_pod),url_akce) url_akce,
          IF(a.patri_pod,(SELECT MAX(popis) FROM akce_seznam WHERE patri_pod=a.patri_pod),popis) popis
        FROM akce_seznam a
        WHERE id_akce='.(int)$id);
      if(!$a) return null;
      return new Aktivita($a);
    }
    return null;
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
   * Vrátí aktivitu s danou url, pokud je veřejně viditelná
   * @param $url url aktivity
   * @param $typ url typu
   */
  function zUrlViditelna($url, $typ) {
    $a = dbOneLineS('
      SELECT * FROM akce_seznam a
      JOIN akce_typy t ON(t.id_typu=a.typ)
      WHERE t.url_typu=$0
      AND a.url_akce=$1
      AND (a.stav=1 OR a.stav=2 OR a.stav=4)
      AND a.rok='.ROK_AKTUALNI, array($typ, $url));
    if(!$a) return null;
    return new Aktivita($a);
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
    $xtpl->assign('obrKlic',self::OBRKLIC);
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
   * Nastaví obrázek podle souboru
   */
  protected function nastavObrazek($soubor)
  {
    if(!is_file($soubor))
      throw new Exception('Neexistující soubor');
    $obr=imagecreatefromjpeg($soubor); // načíst
    $wMax=self::OBRAZEK_MAXW;
    if(imagesx($obr)>$wMax) // zmenšit na omezenou max šířku. Todo obecný resampling. Použít nebo je lepší nechat hi-res?
    {
      $ratio=$wMax/imagesx($obr);
      $nobr=imagecreatetruecolor($wMax,imagesy($obr)*$ratio);
      imagecopyresampled($nobr,$obr,
        0,0,  //dst x,y
        0,0,  //src x,y
        imagesx($nobr),imagesy($nobr), //dst w,h
        imagesx($obr), imagesy($obr)   //scr w,h
      );
      imagejpeg($nobr,$soubor,98); // uložit
    }
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
    if(empty($_FILES[self::OBRKLIC]['tmp_name'])) // neposlán obrázek pro aktualizaci, příp. posláný prázdný (nová aktivita)
      return;
    $soub=__DIR__.'/'.SDILENE_WWW_CESTA.'/files/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    move_uploaded_file($_FILES[self::OBRKLIC]['tmp_name'],$soub);
    $this->nastavObrazek($soub);
  }

}
