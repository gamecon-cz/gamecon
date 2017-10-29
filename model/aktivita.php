<?php

/**
 * Třída aktivity
 */
class Aktivita {

  use Prednacitani;

  protected
    $a,         // databázový řádek s aktivitou
    $kolekce,   // nadřízená kolekce, v rámci které byla aktivita načtena
    $nova,      // jestli jde o nově uloženou aktivitu nebo načtenou z DB
    $organizatori;

  const
    AJAXKLIC='aEditFormTest',  // název post proměnné, ve které jdou data, pokud chceme ajaxově testovat jejich platnost a čekáme json odpověď
    KOLA='aTeamFormKolo',      // název post proměnné s výběrem kol pro team
    OBRKLIC='aEditObrazek',    // název proměnné, v které bude případně obrázek
    TAGYKLIC='aEditTag',       // název proměnné, v které jdou tagy
    POSTKLIC='aEditForm',      // název proměnné (ve výsledku pole), v které bude editační formulář aktivity předávat data
    TEAMKLIC='aTeamForm',      // název post proměnné s formulářem pro výběr teamu
    PN_PLUSMINUSP='cAktivitaPlusminusp',  // název post proměnné pro úpravy typu plus
    PN_PLUSMINUSM='cAktivitaPlusminusm',  // název post proměnné pro úpravy typu mínus
    HAJENI          = 72,      // počet hodin po kterýc aktivita automatick vykopává nesestavený tým
    // stavy aktivity
    AKTIVOVANA      = 1,
    PUBLIKOVANA     = 4,
    PRIPRAVENA      = 5,
    //ignore a parametry kolem přihlašovátka
    BEZ_POKUT       = 0b00010000,   // odhlášení bez pokut
    PLUSMINUS       = 0b00000001,   // plus/mínus zkratky pro měnění míst v team. aktivitě
    PLUSMINUS_KAZDY = 0b00000010,   // plus/mínus zkratky pro každého
    STAV            = 0b00000100,   // ignorování stavu
    TECHNICKE       = 0b01000000,   // přihlašovat i skryté technické aktivity
    ZAMEK           = 0b00001000,   // ignorování zamčení
    ZPETNE          = 0b00100000,   // možnost zpětně měnit přihlášení
    // parametry kolem továrních metod
    JEN_VOLNE       = 0b00000001,   // jen volné aktivity
    VEREJNE         = 0b00000010;   // jen veřejně viditelné aktivity

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
   * Aktivuje (zpřístupní pro přihlašování) aktivitu
   */
  function aktivuj() {
    if(!$this->zacatek()) throw new Chyba('Aktivita nemá nastavený čas');
    dbQuery('UPDATE akce_seznam SET stav = 1 WHERE id_akce = ' . $this->id());
    // TODO invalidate $this
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
    if($this->a['typ'] == 10)
      return null;
    else if(!($this->cenaZaklad() > 0))
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

  /** Délka aktivity v hodinách (float) */
  function delka() {
    if($zacatek = $this->zacatek())
      return ($this->konec()->getTimestamp() - $zacatek->getTimestamp()) / 3600;
    else
      return 0.0;
  }

  /** Vrací datum ve stylu Pátek 14:00-18:00 */
  function denCas() {
    if($z = $this->zacatek())
      return $z->format('l G:i').'–'.$this->konec()->format('G:i');
    else
      return '';
  }

  /** Vrátí potomky této aktivity (=navázané aktivity, další kola, ...) */
  function deti() {
    if($this->a['dite'])
      return self::zIds($this->a['dite']);
    else
      return [];
  }

  /** Počet hodin do začátku aktivity (float) */
  function doZacatku() {
    return ($this->zacatek()->getTimestamp() - time()) / 3600;
  }

  /**
   * Vrátí HTML kód editoru aktivit určený pro vytváření a editaci aktivity.
   * Podle nastavení $a buď aktivitu edituje nebo vytváří.
   * @todo Zkusit refaktorovat editor na samostatnou třídu, pokud to půjde bez
   * vytvoření závislostí na vnitřní proměnné aktivity.
   */
  static function editor(Aktivita $a = null) {
    return self::editorParam($a);
  }

  /**
   * Vrátí pole obsahující chyby znemožňující úpravu aktivity. Hodnoty jsou
   * chybové hlášky. Význam indexů ndef (todo možno rozšířit).
   * @param $a Pole odpovídající strukturou vkládanému (upravovanému) řádku DB,
   * podle toho nemá (má) id aktivity
   */
  protected static function editorChyby($a) {
    $chyby = [];

    // kontrola dostupnosti organizátorů v daný čas
    if(!empty($a['den'])) {
      // hack - převod začátku a konce z formátu formu na legitimní formát data a času
      $a['zacatek'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['zacatek'].'H'))->formatDb();
      $a['konec'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['konec'].'H'))->formatDb();
      foreach($a['organizatori'] as $org) {
        if(!maVolno($org, $a, isset($a['id_akce'])?$a['id_akce']:null)) {
          $k = maVolnoKolize();
          $k = current($k);
          $chyby[] = 'Organizátor '.Uzivatel::zId($org)->jmenoNick().' má v danou dobu '.$k['nazev_akce'].' ('.datum2($k).')';
        }
      }
    }

    // kontrola duplicit url
    if(dbOneLineS('SELECT 1 FROM akce_seznam
      WHERE url_akce = $1 AND ( patri_pod = 0 OR patri_pod != $2 ) AND id_akce != $3 AND rok = $4',
      [$a['url_akce'], $a['patri_pod'], $a['id_akce'], ROK])) {
      $chyby[] = 'Url je už použitá pro jinou aktivitu. Vyberte jinou, nebo použijte tlačítko „inst“ v seznamu aktivit pro duplikaci.';
    }

    return $chyby;
  }

  /**
   * Vrátí v chyby v JSON formátu (pro ajax) nebo FALSE pokud žádné nejsou
   */
  static function editorChybyJson() {
    $a = $_POST[self::POSTKLIC];
    return json_encode(['chyby' => self::editorChyby($a)]);
  }

  /**
   * Vrátí html kód editoru, je možné parametrizovat, co se pomocí něj dá
   * měnit (todo)
   */
  protected static function editorParam(Aktivita $a = null, $omezeni = []) {
    $aktivita = $a ? $a->a : null; // databázový řádek

    // inicializace šablony
    $xtpl = new XTemplate(__DIR__.'/editor.xtpl');
    $xtpl->assign('fields', self::POSTKLIC); // název proměnné (pole) v kterém se mají posílat věci z formuláře
    $xtpl->assign('ajaxKlic',self::AJAXKLIC);
    $xtpl->assign('obrKlic', self::OBRKLIC);
    $xtpl->assign('obrKlicUrl', self::OBRKLIC.'Url');
    $xtpl->assign('pnTagy', self::TAGYKLIC);
    $xtpl->assign('viceScript', file_get_contents(WWW.'/soubory/doplnovani-vice.js'));
    $xtpl->assign('tagyMoznosti', json_encode(dbOneArray('SELECT nazev FROM tagy')));
    if($a) {
      $xtpl->assign($a->a);
      $xtpl->assign('popis', dbText($aktivita['popis']));
      $xtpl->assign('tagy', implode(', ', $a->tagy()));
      $xtpl->assign('urlObrazku', $a->obrazek());
      $xtpl->assign('vybaveni', $a->vybaveni());
    }

    // načtení lokací
    if(!$omezeni || !empty($omezeni['lokace'])) {
      $q = dbQuery('SELECT * FROM akce_lokace ORDER BY poradi');
      while($r = mysqli_fetch_assoc($q)) {
        $xtpl->assign('sel', $a && $aktivita['lokace'] == $r['id_lokace'] ? 'selected' : '');
        $xtpl->assign($r);
        $xtpl->parse('upravy.tabulka.lokace');
      }
    }

    // editace dnů + časů
    if(!$omezeni || !empty($omezeni['zacatek'])) {
      // načtení dnů
      $xtpl->assign('sel',$a && !$a->zacatek() ? 'selected' : '');
      $xtpl->assign('den',0);
      $xtpl->assign('denSlovy','(neurčeno)');
      $xtpl->parse('upravy.tabulka.den');
      for($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
        $xtpl->assign('sel', $a && $den->stejnyDen($a->zacatek()) ? 'selected' : '');
        $xtpl->assign('den', $den->format('Y-m-d'));
        $xtpl->assign('denSlovy', $den->format('l'));
        $xtpl->parse('upravy.tabulka.den');
      }
      // načtení časů
      $aZacatek = $a && $a->zacatek() ? $a->zacatek()->format('G') : PHP_INT_MAX;
      $aKonec = $a && $a->konec() ? $a->konec()->sub(new DateInterval('PT1H'))->format('G') : PHP_INT_MAX;
      for($i = PROGRAM_ZACATEK; $i < PROGRAM_KONEC; $i++) {
        $xtpl->assign('sel', $aZacatek == $i ? 'selected' : '');
        $xtpl->assign('zacatek', $i);
        $xtpl->assign('zacatekSlovy', $i.':00');
        $xtpl->parse('upravy.tabulka.zacatek');
        $xtpl->assign('sel', $aKonec == $i ? 'selected' : '');
        $xtpl->assign('konec', $i+1);
        $xtpl->assign('konecSlovy',($i+1).':00');
        $xtpl->parse('upravy.tabulka.konec');
      }
    }

    // načtení organizátorů
    if(!$omezeni || !empty($omezeni['organizator'])) {
      $q = dbQuery('
        SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
        FROM uzivatele_hodnoty u
        LEFT JOIN r_uzivatele_zidle z USING(id_uzivatele)
        LEFT JOIN r_prava_zidle p USING(id_zidle)
        WHERE p.id_prava = '.P_ORG_AKCI.'
        GROUP BY u.id_uzivatele
        ORDER BY u.login_uzivatele
      ');
      $vsichniOrg = [ 0 => '(nikdo)' ];
      while($r = mysqli_fetch_assoc($q)) {
        $vsichniOrg[$r['id_uzivatele']] = Uzivatel::jmenoNickZjisti($r);
      }
      $aktOrg = $a ? array_map(function($e) { return $e->id(); }, $a->organizatori()) : [];
      $aktOrg[] = 0; // poslední pole má selected 0 (žádný org)
      $poli = count($aktOrg);
      for($i = 0; $i < $poli; $i++) {
        foreach($vsichniOrg as $id => $org) {
          if($id == $aktOrg[$i]) {
            $xtpl->assign('sel', 'selected');
          } else {
            $xtpl->assign('sel', '');
          }
          $xtpl->assign('organizator', $id);
          $xtpl->assign('organizatorJmeno', $org);
          $xtpl->parse('upravy.tabulka.orgBox.organizator');
        }
        $xtpl->assign('i', $i);
        $xtpl->parse('upravy.tabulka.orgBox');
      }
    }

    // načtení typů
    if(!$omezeni || !empty($omezeni['typ'])) {
      $xtpl->assign(['sel'=>'','id_typu'=>0,'typ_1p'=>'(bez typu – organizační)']);
      $xtpl->parse('upravy.tabulka.typ');
      $q = dbQuery('SELECT * FROM akce_typy');
      while($r = mysqli_fetch_assoc($q)) {
        $xtpl->assign('sel', $a && $r['id_typu'] == $aktivita['typ'] ? 'selected' : '');
        $xtpl->assign($r);
        $xtpl->parse('upravy.tabulka.typ');
      }
    }

    // výstup
    if(empty($omezeni)) $xtpl->parse('upravy.tabulka'); // TODO ne pokud je bez omezení, ale pokud je omezeno všechno. Pokud jen něco, doprogramovat selektivní omezení pro prvky tabulky i u IFů nahoře a vložit do šablony
    $xtpl->parse('upravy');
    return $xtpl->text('upravy');
  }

  /**
   * Vrátí, jestli se volající stránka snaží získat JSON data pro ověření formu
   */
  static function editorTestJson() {
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
  static function editorZpracuj() {
    if(!isset($_POST[self::POSTKLIC])) return null;

    // úprava přijatých dat
    $a = $_POST[self::POSTKLIC];
    // v případě nezobrazení tabulky a tudíž chybějícího text. pole s url (viz šablona) se použije hidden pole s původní url
    if(empty($a['url_akce']) && !empty($_POST[self::POSTKLIC.'staraUrl'])) {
      $a['url_akce'] = $_POST[self::POSTKLIC.'staraUrl'];
    }
    $a['bez_slevy'] = (int)!empty($a['bez_slevy']); //checkbox pro "bez_slevy"
    $a['teamova']   = (int)!empty($a['teamova']);   //checkbox pro "teamova"
    $a['team_min']  = $a['teamova'] ? (int)$a['team_min'] : null;
    $a['team_max']  = $a['teamova'] ? (int)$a['team_max'] : null;
    // u teamových aktivit se kapacita ignoruje - později se nechá jak je nebo přepíše minimem, pokud jde o novou aktivitu
    if($a['teamova']) unset($a['kapacita'], $a['kapacita_f'], $a['kapacita_m']);
    // přepočet času
    if(empty($a['den'])) {
      $a['zacatek'] = $a['konec'] = null;
    } else {
      $a['zacatek'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['zacatek'].'H'))->formatDb();
      $a['konec'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT'.$a['konec'].'H'))->formatDb();
    }
    unset($a['den']);
    // extra položky kvůli sep. tabulkám
    $organizatori = $a['organizatori'];
    unset($a['organizatori']);
    $popis = $a['popis'];
    unset($a['popis']);

    // uložení změn do akce_seznam
    if(!$a['patri_pod'] && $a['id_akce']) {
      // editace jediné aktivity
      dbInsertUpdate('akce_seznam', $a);
      $aktivita = self::zId($a['id_akce']);
    } elseif($a['patri_pod']) {
      // editace aktivity z rodiny instancí
      $doHlavni   = ['url_akce', 'popis', 'vybaveni'];  // věci, které se mají změnit jen u hlavní (master) instance
      $doAktualni = ['lokace','zacatek','konec'];       // věci, které se mají změnit jen u aktuální instance
      $aktivita   = self::zId($a['id_akce']);
      // (zbytek se změní v obou)
      // určení hlavní aktivity
      $idHlavni = dbOneCol('SELECT MIN(id_akce) FROM akce_seznam WHERE patri_pod = '.(int)$a['patri_pod']);
      $patriPod = $a['patri_pod'];
      unset($a['patri_pod']);
      // změny v hlavní aktivitě
      $zmenyHlavni = array_diff_key($a, array_flip($doAktualni));
      $zmenyHlavni['id_akce'] = $idHlavni;
      dbInsertUpdate('akce_seznam', $zmenyHlavni);
      // změny v konkrétní instanci
      $zmenyAktualni = array_diff_key($a, array_flip($doHlavni));
      dbInsertUpdate('akce_seznam', $zmenyAktualni);
      // změny u všech
      $zmenyVse = array_diff_key($a, array_flip(array_merge($doHlavni, $doAktualni)));
      unset($zmenyVse['patri_pod'], $zmenyVse['id_akce']); // id se nesmí updatovat!
      dbUpdate('akce_seznam', $zmenyVse, ['patri_pod' => $patriPod]);
    } else {
      // vkládání nové aktivity
      // inicializace hodnot pro novou aktivitu
      $a['id_akce'] = null;
      $a['rok'] = ROK;
      if($a['teamova']) $a['kapacita'] = $a['team_max']; // při vytváření nové aktivity se kapacita inicializuje na max. teamu
      if(empty($a['nazev_akce'])) $a['nazev_akce'] = '(neurčený název)';
      // vložení
      dbInsertUpdate('akce_seznam', $a);
      $a['id_akce'] = dbInsertId();
      $aktivita = self::zId($a['id_akce']);
      $aktivita->nova = true;
    }

    // objektová rozhraní
    if($f = postFile(self::OBRKLIC))      $aktivita->obrazek(Obrazek::zJpg($f));
    if($url = post(self::OBRKLIC.'Url'))  $aktivita->obrazek(Obrazek::zUrl($url));
    $aktivita->organizatori($organizatori);
    $aktivita->popis($popis);
    $tagy = [];
    foreach(explode(',', post(self::TAGYKLIC)) as $t) {
      $t = trim($t);
      $t = preg_replace('@\s+@', ' ', $t);
      if($t) $tagy[] = $t;
    }
    $aktivita->tagy($tagy);

    return $aktivita;
  }

  function id()
  { return $this->a['id_akce']; }

  /** Vytvoří novou instanci aktivity */
  function instanciuj()
  {
    $akt = dbOneLine('SELECT * FROM akce_seznam WHERE id_akce='.$this->id());
    //odstraníme id, url a popisek, abychom je nepoužívali/neduplikovali při vkládání
    //stav se vloží implicitní hodnota v DB
    unset($akt['id_akce'], $akt['url_akce'], $akt['stav'], $akt['zamcel'], $akt['vybaveni']);
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

  protected function kolekce() {
    return $this->kolekce->getArrayCopy();
  }

  /** Vrátí DateTime objekt konce aktivity */
  function konec() {
    if(is_string($this->a['konec']))
      $this->a['konec'] = new DateTimeCz($this->a['konec']);
    return $this->a['konec'];
  }

  /**
   * Zapíše do přihlašovacího kombinaci aktivita + uživatel + zpráva
   */
  function log(Uzivatel $u, $zprava) {
    dbInsert('akce_prihlaseni_log', [
      'id_uzivatele' => $u->id(),
      'id_akce' => $this->id(),
      'typ' => $zprava,
    ]);
  }

  function lokaceId()
  { return $this->a['lokace']; }

  /** Vrátí lokaci (ndef. formát, ale musí podporovat __toString) */
  function lokace() {
    return Lokace::zId($this->a['lokace']);
  }

  function nazev()
  { return $this->a['nazev_akce']; }

  /**
   * Aktivita negeneruje slevu organizátorovi
   */
  function nedavaSlevu() {
    return $this->a['nedava_slevu'];
  }

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
    $url=URL_WEBU.'/soubory/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    $soub = WWW.'/soubory/systemove/aktivity/'.$this->a['url_akce'].'.jpg';
    if(func_num_args() == 0) {
      try {
        return Nahled::zSouboru($soub)->pasuj(400);
      } catch(Exception $e) {
        return '';
      }
    } else {
      $o = func_get_arg(0);
      $o->fitCrop(2048, 2048);
      $o->uloz($soub);
    }
  }

  /** (Správný) alias pro obsazenostHtml() */
  function obsazenost() {
    return $this->obsazenostHtml();
  }

  /** Vrátí html kód s políčky určujícímí obsazenost */
  function obsazenostHtml() {
    $m = $this->prihlasenoMuzu(); // počty
    $f = $this->prihlasenoZen();
    $c = $m + $f;
    $km = $this->a['kapacita_m']; // kapacity
    $kf = $this->a['kapacita_f'];
    $ku = $this->a['kapacita'];
    $kc = $ku + $km + $kf;
    if(!$kc)
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
    $o = dbQuery('SELECT id_akce, zamcel FROM akce_seznam WHERE zamcel AND zamcel_cas < NOW() - interval '.self::HAJENI.' hour');
    $i = 0;
    while( list($aid, $uid) = mysqli_fetch_row($o) ) {
      Aktivita::zId($aid)->odhlas(Uzivatel::zId($uid));
      $i++;
    }
    return $i;
    // uvolnění zámku je součástí odhlášení, pokud je sám -> done
  }

  /**
   * Odhlásí uživatele z aktivity
   * @todo kontroly? (např. jestli je aktivní přihlašování?) (administrativní
   *  odhlašování z DrD počítá s možnosti odhlásit např. od semifinále dál)
   */
  function odhlas(Uzivatel $u, $params = 0) {
    foreach($this->deti() as $dite) { // odhlášení z potomků
      $dite->odhlas($u); // spoléhá na odolnost proti odhlašování z aktivit kde uživatel není
    }
    if(!$this->prihlasen($u)) return; // ignorovat pokud přihlášen není tak či tak
    // reálné odhlášení
    $aid = $this->id();
    $uid = $u->id();
    dbQuery("DELETE FROM akce_prihlaseni WHERE id_uzivatele=$uid AND id_akce=$aid");
    dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='odhlaseni'");
    if(ODHLASENI_POKUTA_KONTROLA && $this->doZacatku() < ODHLASENI_POKUTA1_H && !($params & self::BEZ_POKUT)) //pokuta aktivní
      dbQuery("INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$uid, id_akce=$aid, id_stavu_prihlaseni=4");
    if($this->a['zamcel'] == $uid)
      dbQuery("UPDATE akce_seznam SET zamcel=NULL, zamcel_cas=NULL, team_nazev=NULL WHERE id_akce=$aid");
    if($this->a['teamova'] && $this->prihlaseno()==1) // odhlašuje se poslední hráč
      dbQuery("UPDATE akce_seznam SET kapacita=team_max WHERE id_akce=$aid");
    $this->refresh();
  }

  /** Vráti aktivitu ze stavu připravená do stavu publikovaná */
  function odpriprav() {
    if(!$this->a['stav'] == self::PRIPRAVENA) throw new Exception('Aktivita není v stavu "připravená"');
    dbUpdate('akce_seznam', ['stav' => self::PUBLIKOVANA], ['id_akce' => $this->id()]);
  }

  /**
   * Vrátí pole uživatelů, kteří jsou organizátory této aktivity. Při zadaném
   * parametru poli ID nastaví tyto organizátory.
   * @todo dělat diff a ne delete/insert
   */
  function organizatori($ids = null) {
    if(is_array($ids)) {
      dbQuery('DELETE FROM akce_organizatori WHERE id_akce = '.$this->id());
      foreach($ids as $id)
        if((int)$id)
          dbQuery('INSERT INTO akce_organizatori(id_akce, id_uzivatele)
            VALUES ('.$this->id().','.(int)$id.')');
    } else {
      if(!isset($this->organizatori)) $this->prednactiMN([
        'atribut'       =>  'organizatori',
        'cil'           =>  Uzivatel::class,
        'tabulka'       =>  'akce_organizatori',
        'zdrojSloupec'  =>  'id_akce',
        'cilSloupec'    =>  'id_uzivatele',
      ]);
      return $this->organizatori;
    }
  }

  /**
   * Vrátí pole uživatelů, kteří jsou organizátory jakékoli ze skupiny instancí
   * aktivity. Pokud nemá instance, vrátí organizátory aktivity jak jsou.
   */
  function organizatoriSkupiny() {
    if($this->a['patri_pod']) {
      return Uzivatel::zIds(dbOneCol('
        SELECT GROUP_CONCAT(ao.id_uzivatele)
        FROM akce_seznam a
        LEFT JOIN akce_organizatori ao USING (id_akce)
        WHERE a.patri_pod = '.$this->a['patri_pod']
      ));
    } else {
      return $this->organizatori();
    }
  }

  /**
   * @return Vrátí iterátor jmen organizátorů v lidsky čitelné podobě.
   * @deprecated Použít přístup přes organizatori() a jmenoNick() například.
   */
  function orgJmena() {
    // TODO logování deprekace
    $jmena = new ArrayIteratorTos;
    foreach($this->organizatori() as $o) {
        $jmena[] = $o->jmenoNick();
    }
    return $jmena;
  }

  /** Vrátí specifické označení organizátora pro aktivitu tohoto typu */
  function orgTitul() {
    return dbOneCol('SELECT titul_orga FROM akce_typy WHERE id_typu='.$this->a['typ']);
  }

  /** Alias */
  function otoc() {
    $this->refresh();
  }

  /** Skupina (id) aktivit. Spíše hack, raději refaktorovat */
  function patriPod() {
    return $this->a['patri_pod'];
  }

  /**
   * Vrátí formátovaný (html) popisek aktivity
   */
  function popis() {
    if(func_num_args() == 0) {
      return dbMarkdown($this->a['popis']);
    } else {
      $oldId = $this->a['popis'];
      $id = dbTextHash(func_get_arg(0));
      if($this->a['patri_pod'])
        dbUpdate('akce_seznam', ['popis' => $id], ['patri_pod' => $this->a['patri_pod']]);
      else
        dbUpdate('akce_seznam', ['popis' => $id], ['id_akce' => $this->id()]);
      $this->a['popis'] = $id;
      dbTextClean($oldId);
    }
  }

  /**
   * Vrátí form(y) s vybírátky plus a mínus pro změny počtů míst teamových akt.
   * @todo parametry typu komplexnost výpisu a že nemůže měnit kdokoli aktivut
   * ale jen ten kdo je na ni přihlášený (vs. orgové v adminu)
   */
  protected function plusminus(Uzivatel $u = null, $parametry = 0) {
    // kontroly
    if(!$this->a['teamova'] || $this->a['stav'] != 1) return '';
    if($parametry & self::PLUSMINUS && (!$u || !$this->prihlasen($u))) return '';
    // tisk formu
    $out = '';
    if($this->a['team_max'] > $this->a['kapacita']) {
      $out .= ' <form method="post" style="display:inline"><input type="hidden" name="'.self::PN_PLUSMINUSP.'" value="'.$this->id().'"><a href="#" onclick="$(this).closest(\'form\').submit(); return false">▲</a></form>';
    }
    if($this->a['team_min'] < $this->a['kapacita'] && $this->prihlaseno() < $this->a['kapacita']) {
      $out .= ' <form method="post" style="display:inline"><input type="hidden" name="'.self::PN_PLUSMINUSM.'" value="'.$this->id().'"><a href="#" onclick="$(this).closest(\'form\').submit(); return false">▼</a></form>';
    }
    return $out;
  }

  /** Zpracuje formy na měnění počtu míst team. aktivit */
  protected static function plusminusZpracuj(Uzivatel $u = null, $parametry = 0) {
    if(post(self::PN_PLUSMINUSP)) {
      dbQueryS('UPDATE akce_seznam SET kapacita = kapacita + 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSP)]);
      back();
    }
    if(post(self::PN_PLUSMINUSM)) {
      dbQueryS('UPDATE akce_seznam SET kapacita = kapacita - 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSM)]);
      back();
    }
  }

  /**
   * @return počet týmů přihlášených na tuto aktivitu
   */
  protected function pocetTeamu() {
    $id = $this->id();
    $idRegex = '(^|,)' . $this->id() . '(,|$)'; // reg. výraz odpovídající id aktivity v seznamu odděleném čárkami
    return dbOneCol('
      SELECT COUNT(1)
      FROM (
        -- vybereme aktivity základního kola, z kterých se dá dostat do této aktivity (viz WHERE)
        SELECT a.id_akce
        FROM akce_seznam a
        -- připojíme k každé aktivitě přihlášené účastníky
        LEFT JOIN akce_prihlaseni prihlaseni_zaklad ON prihlaseni_zaklad.id_akce = a.id_akce
        -- připojíme k každému účastníkovi, jestli je přihlášen i na tuto semifinálovou aktivitu
        LEFT JOIN akce_prihlaseni prihlaseni_toto ON prihlaseni_toto.id_uzivatele = prihlaseni_zaklad.id_uzivatele AND prihlaseni_toto.id_akce = $0
        WHERE a.dite RLIKE $1
        GROUP BY a.id_akce
        -- vybereme jenom aktivity, z který je víc jak 0 přihlášeno i na toto semifinále
        HAVING COUNT(prihlaseni_toto.id_uzivatele) > 0
      ) poddotaz
    ', [$id, $idRegex]);
  }

  /**
   * Přihlásí uživatele na aktivitu
   * @todo koncepčnější ignorování stavu
   */
  function prihlas(Uzivatel $u, $ignorovat = 0)
  {
    // kontroly
    if($this->prihlasen($u))          return;
    if(!maVolno($u->id(), $this->a))  throw new Chyba(hlaska('kolizeAktivit')); // TODO převést na metodu uživatele
    if(!$u->gcPrihlasen())            throw new Exception('Nemáš aktivní přihlášku na GameCon.');
    if($this->volno()!='u' && $this->volno()!=$u->pohlavi()) throw new Chyba(hlaska('plno'));
    foreach($this->deti() as $dite) { // nemůže se přihlásit na aktivitu, pokud už je přihášen na jinou aktivitu s stejnými potomky
      foreach($dite->rodice() as $rodic) {
        if($rodic->prihlasen($u)) throw new Chyba(hlaska('maxJednou'));
      }
    }
    if($this->a['team_kapacita'] !== null) {
      $jeNovyTym = false; // jestli se uživatel přihlašuje jako první z nového/dalšího týmu
      foreach($this->rodice() as $rodic) {
        if($rodic->prihlasen($u) && $rodic->prihlaseno() == 1) {
          $jeNovyTym = true;
          break;
        }
      }
      if($jeNovyTym && $this->pocetTeamu() >= $this->a['team_kapacita']) {
        throw new Exception('Na aktivitu ' . $this->nazev() . ': ' . $this->denCas() . ' je už přihlášen maximální počet týmů');
      }
    }

    // potlačitelné kontroly
    if($this->a['zamcel'] && !($ignorovat & self::ZAMEK)) throw new Chyba(hlaska('zamcena'));
    if(!$this->prihlasovatelna($ignorovat)) {
      // hack na ignorování stavu
      $puvodniStav = $this->a['stav'];
      if($ignorovat & self::STAV) $this->a['stav'] = 1; // nastavíme stav jako by bylo vše ok
      $prihlasovatelna = $this->prihlasovatelna($ignorovat);
      $this->a['stav'] = $puvodniStav;
      if(!$prihlasovatelna) throw new Exception('Aktivita není otevřena pro přihlašování.');
    }

    // přihlášení na navázané aktivity (jen pokud není teamleader)
    if($this->a['dite'] && $this->prihlaseno() > 0) {
      $deti = $this->deti();
      if(count($deti) == 1) {
        current($deti)->prihlas($u, self::STAV);
      } else {
        // vybrání jednoho uživatele, který už na navázané aktivity přihlášen je
        $vzor = Uzivatel::zId( substr(explode(',', $this->prihlaseniRaw())[1], 0, -2) );
        $uspech = false;
        foreach($deti as $dite) {
          // přihlášení na navázané aktivity podle vzoru vybraného uživatele
          if($dite->prihlasen($vzor)) {
            $dite->prihlas($u, self::STAV);
            $uspech = true;
            break;
          }
        }
        if(!$uspech) throw new Exception('Nepodařilo se určit výběr dalšího kola.');
      }
    }

    // přihlášení na samu aktivitu (uložení věcí do DB)
    $aid = $this->id();
    $uid = $u->id();
    if($this->a['teamova'] && $this->prihlaseno()==0 && $this->prihlasovatelna())
      dbUpdate('akce_seznam', ['zamcel'=>$uid, 'zamcel_cas'=>dbNow()], ['id_akce'=>$aid]);
    dbQuery("INSERT INTO akce_prihlaseni SET id_uzivatele=$uid, id_akce=$aid");
    dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='prihlaseni'");
    if(ODHLASENI_POKUTA_KONTROLA) //pokud by náhodou měl záznam za pokutu a přihlásil se teď, tak smazat
      dbQueryS('DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0
        AND id_akce=$1 AND id_stavu_prihlaseni=4', [$uid, $aid]);
    $this->refresh();
  }

  /** Jestli je uživatel  přihlášen na tuto aktivitu */
  function prihlasen(Uzivatel $u)
  {
    return strpos($this->prihlaseniRaw(), ','.$u->id().$u->pohlavi()) !== false;
  }

  /**
   * Vrátí serializovaný seznam přihlášených a pokud takový neexistuje, načte
   * ho. Formát seznamu je: ,1204m0,864f2,742f1,...,1001m1, kde čísla jsou id
   * uživatelů, písmena pohlaví a čísla z pohlavím stav přihlášení.
   * @see ucastnici
   */
  private function prihlaseniRaw()
  {
    if(!array_key_exists('prihlaseni', $this->a))
      throw new Exception ('Nenačteny počty přihlášených do aktivity.');
    return $this->a['prihlaseni'];
  }

  /** Počet přihlášených */
  protected function prihlaseno()
  {
    if($p = $this->prihlaseniRaw())
      return substr_count($p, ',') - 1;
    else
      return 0;
  }

  protected function prihlasenoMuzu()
  {
    return substr_count($this->prihlaseniRaw(), 'm');
  }

  protected function prihlasenoZen()
  {
    return substr_count($this->prihlaseniRaw(), 'f');
  }

  /**
   * Vrátí stav přihlášení uživatele na aktivitu. Pokud není přihlášen, vrací
   * hodnotu -1.
   */
  private function prihlasenStav(Uzivatel $u)
  {
    $prihlaseni = $this->prihlaseniRaw();
    $usymbol = ','.$u->id().$u->pohlavi();
    $pos = strpos($prihlaseni, $usymbol);
    if($pos !== false) {
      return (int)substr($prihlaseni, $pos+strlen($usymbol), 1);
    } else {
      return -1;
    }
  }

  /** Zdali chceme, aby se na aktivitu bylo možné běžně přihlašovat */
  function prihlasovatelna($parametry = 0) {
    $zpetne = $parametry & self::ZPETNE;
    $technicke = $parametry & self::TECHNICKE;
    // stav 4 je rezervovaný pro viditelné nepřihlašovatelné aktivity
    return(
      (REG_AKTIVIT || $zpetne && po(REG_GC_DO)) &&
      (
        $this->a['stav'] == 1 ||
        $technicke && $this->a['stav'] == 0 && $this->a['typ'] == 10 ||
        $zpetne && $this->a['stav'] == 2
      ) &&
      $this->a['zacatek'] &&
      $this->a['typ']
    );
  }

  /**
   * Vrátí html kód pro přihlášení / odhlášení / informaci o zaplněnosti pro
   * daného uživatele. Pokud není zadán, vrací prázdný řetězec.
   * @todo v rodině instancí maximálně jedno přihlášení?
   * @todo konstanty pro jména POST proměnných? viz prihlasovatkoZpracuj
   */
  function prihlasovatko(Uzivatel $u = null, $parametry = 0) {
    $out = '';
    if($u && $u->gcPrihlasen() && $this->prihlasovatelna($parametry)) {
      if(($stav = $this->prihlasenStav($u)) > -1) {
        if($stav == 0 || $parametry & self::ZPETNE)
          $out .=
            '<form method="post" style="display:inline">'.
            '<input type="hidden" name="odhlasit" value="'.$this->id().'">'.
            '<a href="#" onclick="$(this).parent().submit(); return false">odhlásit</a>'.
            '</form>';
        if($stav == 1) $out .= '<em>účast</em>';
        if($stav == 2) $out .= '<em>jako náhradník</em>';
        if($stav == 3) $out .= '<em>neúčast</em>';
        if($stav == 4) $out .= '<em>pozdní odhlášení</em>';
      } elseif($u->organizuje($this)) {
        $out = '';
      } elseif($this->a['zamcel']) {
        $out = '&#128274;'; //zámek
      } else {
        $volno = $this->volno();
        if($volno == 'u' || $volno == $u->pohlavi())
          $out =
            '<form method="post" style="display:inline">'.
            '<input type="hidden" name="prihlasit" value="'.$this->id().'">'.
            '<a href="#" onclick="$(this).parent().submit(); return false">přihlásit</a>'.
            '</form>';
        elseif($volno == 'f')
          $out = 'pouze ženská místa';
        elseif($volno == 'm')
          $out = 'pouze mužská místa';
      }
    }
    if($parametry & self::PLUSMINUS_KAZDY) {
      $out .= '&emsp;' . $this->plusminus($u);
    }
    return $out;
  }

  /** Zpracuje post data z přihlašovátka. Pokud došlo ke změně, vyvolá reload */
  static function prihlasovatkoZpracuj(Uzivatel $u = null, $parametry = 0)
  {
    if(post('prihlasit')) {
      self::zId(post('prihlasit'))->prihlas($u, $parametry);
      back();
    }
    if(post('odhlasit')) {
      $bezPokut = $parametry & self::ZPETNE ? self::BEZ_POKUT : 0; // v případě zpětných změn bez pokut
      self::zId(post('odhlasit'))->odhlas($u, $bezPokut);
      back();
    }
    if($parametry & self::PLUSMINUS_KAZDY) {
      self::plusminusZpracuj($u, $parametry);
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

  /** Nastaví aktivitu jako "připravena pro aktivaci" */
  function priprav() {
    dbUpdate('akce_seznam', ['stav' => self::PRIPRAVENA], ['id_akce' => $this->id()]);
  }

  /** Zdali už aktivita začla a proběhla (rozhodný okamžik je vyjetí seznamů
   *  přihlášených na infopultu) */
  function probehnuta()
  {
    return $this->a['stav']==2;
  }

  /**
   * Vrací surový databázový řádek, nepoužívat (pouze pro debug a zpětnou
   * kompatibilitu, postupně odstranit).
   * @deprecated
   */
  function rawDb() {
    return $this->a;
  }

  /** Aktualizuje stav aktivity podle databáze */
  function refresh() {
    $aktualni = self::zId($this->id());
    $this->a = $aktualni->a;
  }

  /** Vrátí aktivity, u kterých je tato aktivita jako jedno z dětí */
  function rodice() {
    return self::zWhere('WHERE a.dite rlike "(^|,)'.$this->id().'(,|$)"');
  }

  /**
   * Smaže aktivitu z DB
   */
  function smaz() {
    foreach($this->prihlaseni() as $u) {
      $this->odhlas($u);
    }
    dbQuery('DELETE FROM akce_organizatori WHERE id_akce = ' . $this->id());
    dbQuery('DELETE FROM akce_seznam WHERE id_akce = ' . $this->id());
    // řešení instancí, pokud patří do rodiny instancí
    $rodina = $this->a['patri_pod'];
    if($rodina) {
      // načtení id mateřské instance
      $r = dbOneLine('SELECT MIN(id_akce) as mid, COUNT(1) as pocet FROM akce_seznam WHERE patri_pod = ' . $rodina);
      $mid = $r['mid'];
      $pocet = $r['pocet'];
      // zbyla jediná instance, zrušit u ní patri_pod
      if($pocet == 1) {
        dbQuery('UPDATE akce_seznam SET patri_pod = 0 WHERE patri_pod = ' . $rodina);
      }
      // id zrušené instance bylo nejnižší => je potřeba uložit url a popisek do nové instance
      if($this->id() < $mid) {
        dbQueryS(
          'UPDATE akce_seznam SET url_akce=$1, popis=$2, vybaveni=$3 WHERE id_akce=$4',
          [$this->a['url_akce'], $this->a['popis'], $this->a['vybaveni'], $mid]
        );
      }
    }
    // invalidace aktuální instance
    $this->a = null;
  }

  /**
   * Vrátí iterátor tagů
   */
  function tagy() {
    if(func_num_args() == 0) {
      if($this->a['tagy'])
        return explode(',', $this->a['tagy']);
      else
        return [];
    } else {
      $tagy = func_get_arg(0);
      dbDelete('akce_tagy', ['id_akce' => $this->id()]);
      if(!$tagy) return;
      $qtagy = array_map(function($e){ return '('.dbQv($e).')'; }, $tagy);
      dbQuery('INSERT IGNORE INTO tagy(nazev) VALUES '.implode(',', $qtagy).'');
      dbQuery('INSERT INTO akce_tagy(id_akce, id_tagu)
        SELECT $1, id FROM tagy WHERE nazev IN('.dbQa($tagy).')
      ', [$this->id()]);
    }
  }

  /**
   * Je aktivita teamová?
   */
  function teamova() {
    return $this->a['teamova'];
  }

  function tym() {
    if($this->teamova() && $this->prihlaseno() > 0 && !$this->a['zamcel'])
      return new Tym($this, $this->a);
    else
      return null;
  }

  /**
   * Vrátí ID typu aktivity
   * @todo na této úrovni není dořešené ORM, toto by se mělo přejmenovat na
   *  typId() a nějak koncepčně fixnout
   */
  function typ()
  { return $this->a['typ']; }
  
  /**
   * Vrátí pole s přihlášenými účastníky
   */
  function prihlaseni() {
    $u = substr($this->prihlaseniRaw(), 1, -1);
    $u = preg_replace('@(m|f)\d+@', '', $u);
    return Uzivatel::zIds($u);
  }

  /**
   * Uloží údaje o prezenci u této aktivity
   * @param $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
   */
  function ulozPrezenci($dorazili) {
    $prihlaseni = [];  // přihlášení kteří dorazili
    $nahradnici = [];  // náhradníci
    $nedorazili = [];  // přihlášení kteří nedorazili
    $doraziliIds = []; // id všech co dorazili (kvůli kontrole přítomnosti)
    // určení skupin kdo dorazil a kdo ne
    foreach($dorazili as $u) {
      if($this->prihlasen($u)) {
        $prihlaseni[] = $u;
      } else {
        $nahradnici[] = $u;
      }
      $doraziliIds[$u->id()] = true;
    }
    foreach($this->prihlaseni() as $u) {
      if(isset($doraziliIds[$u->id()])) continue;
      $nedorazili[] = $u;
    }
    // úprava stavu přihlášení podle toho do jaké skupiny spadá
    foreach($prihlaseni as $u) {
      dbInsertUpdate('akce_prihlaseni', [
        'id_uzivatele' => $u->id(),
        'id_akce' => $this->id(),
        'id_stavu_prihlaseni' => 1
      ]);
    }
    foreach($nahradnici as $u) {
      dbInsert('akce_prihlaseni', [
        'id_uzivatele' => $u->id(),
        'id_akce' => $this->id(),
        'id_stavu_prihlaseni' => 2
      ]);
      $this->log($u, 'prihlaseni_nahradnik');
    }
    foreach($nedorazili as $u) {
      dbDelete('akce_prihlaseni', [
        'id_uzivatele' => $u->id(),
        'id_akce' => $this->id()
      ]);
      dbInsert('akce_prihlaseni_spec', [
        'id_uzivatele' => $u->id(),
        'id_akce' => $this->id(),
        'id_stavu_prihlaseni' => 3
      ]);
      $this->log($u, 'nedostaveni_se');
    }
  }

  /**
   * @return string absolutní url k anotaci aktivity na webu
   */
  function url() {
    static $typy; // TODO hack na cacheování názvů typů kvůli chybějícímu orm
    if(!$typy) {
      $o = dbQuery('SELECT id_typu, url_typu_mn FROM akce_typy');
      while($r = mysqli_fetch_row($o)) $typy[$r[0]] = $r[1];
    }
    return URL_WEBU . '/' . $typy[$this->a['typ']] . '#' . $this->a['url_akce'];
  }

  /**
   * @return string část url identifikující aktivitu (unikátní v dané linii)
   */
  function urlId() {
    return $this->a['url_akce'];
  }

  /** Vrátí, jestli aktivita bude aktivována v budoucnu, později než v další vlně */
  function vBudoucnu() {
    return $this->a['stav'] == self::PUBLIKOVANA;
  }

  /** Vrátí, jestli aktivita bude aktivována v další vlně */
  function vDalsiVlne() {
    return $this->a['stav'] == self::PRIPRAVENA || !REG_AKTIVIT && $this->a['stav'] == self::AKTIVOVANA;
  }

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

  /** Jestli volno pro daného uživatele (nebo aspoň pro někoho, pokud null) */
  function volnoPro(Uzivatel $u = null) {
    $v = $this->volno();
    if($u)
      return $v == 'u' || $v == $u->pohlavi();
    else
      return $v != 'x';
  }

  /**
   * Jestli má uživatel aktivitu vidět (případně jestli má být vidět veřejně,
   * pokud $u == null).
   */
  function viditelnaPro(Uzivatel $u = null) {
    return (
      in_array($this->a['stav'], [1, 2, 4, 5]) // podle stavu je aktivita viditelná
        && !($this->a['typ'] == Typ::TECHNICKA && $this->a['stav'] == 2) || // ale skrýt technické proběhnuté
      $u && $this->prihlasen($u) ||
      $u && $u->organizuje($this)
    );
  }

  /**
   * @return text s informací o extra vybavení pro tuto aktivitu
   */
  function vybaveni() {
    if($this->a['patri_pod'])
      return dbOneCol('SELECT MAX(vybaveni) FROM akce_seznam WHERE patri_pod = $1',
        [$this->a['patri_pod']]);
    else
      return dbOneCol('SELECT vybaveni FROM akce_seznam WHERE id_akce = $1', [$this->id()]);
  }

  /**
   * Vrátí formulář pro výběr teamu na aktivitu. Pokud není zadán uživatel,
   * vrací nějakou false ekvivalentní hodnotu.
   * @todo ideálně převést na nějaké statické metody týmu nebo samostatnou třídu
   */
  function vyberTeamu(Uzivatel $u = null) {
    if(!$u || $this->a['zamcel'] != $u->id() || !$this->prihlasovatelna()) return null;

    $t = new XTemplate(__DIR__ . '/tym-formular.xtpl');

    // obecné proměnné šablony
    $zbyva = strtotime($this->a['zamcel_cas']) + self::HAJENI * 60 * 60 - time();
    $t->assign([
      'zbyva'       =>  floor($zbyva / 3600) . ' hodin ' . floor($zbyva % 3600 / 60) . ' minut',
      'postname'    =>  self::TEAMKLIC,
      'prihlasenyUzivatelId' => $u->id(),
      'aktivitaId'  =>  $this->id(),
    ]);

    // výběr instancí, pokud to aktivita vyžaduje
    if($this->a['dite']) {

      // načtení "kol" (podle hloubky zanoření v grafu instancí)
      $urovne[] = [$this];
      do {
        $dalsi = [];
        foreach(end($urovne) as $a) {
          if($a->a['dite'])
            $dalsi = array_merge($dalsi, explode(',', $a->a['dite']));
        }
        if($dalsi)
          $urovne[] = self::zIds($dalsi);
      } while($dalsi);
      unset($urovne[0]); // aktuální aktivitu už má přihlášenu - ignorovat

      // vybírací formy dle "kol"
      foreach($urovne as $i => $uroven) {
        $t->assign('postnameKolo', self::KOLA . '[' . $i . ']');
        foreach($uroven as $varianta) {
          $t->assign([
            'koloId' => $varianta->id(),
            'nazev' => $varianta->nazev() . ': ' . $varianta->denCas(),
          ]);
          $t->parse('formular.kola.uroven.varianta');
        }
        $t->parse('formular.kola.uroven');
      }
      $t->parse('formular.kola');

    }

    // políčka pro výběr míst
    for($i = 0; $i < $this->kapacita() - 1; $i++) {
      $t->assign('postnameMisto', self::TEAMKLIC . '[' . $i . ']');
      if($i >= $this->a['team_min'] - 1) // -1 za týmlídra
        $t->parse('formular.misto.odebrat');
      $t->parse('formular.misto');
    }

    // název (povinný pro DrD)
    if($this->a['typ'] == Typ::DRD)   $t->parse('formular.nazevPovinny');
    else                              $t->parse('formular.nazevVolitelny');

    // výpis celého formuláře
    $t->parse('formular');
    return $t->text('formular');
  }

  /**
   * Zpracuje data formuláře pro výběr teamu a vrátí případné chyby jako json.
   * Ukončuje skript.
   * @todo kontrola, jestli nezamčel moc míst
   */
  static function vyberTeamuZpracuj(Uzivatel $leader = null) {
    if( !$leader || !($t = post(self::TEAMKLIC)) ) return;
    $chyby = [];
    $prihlaseni = []; // pro rollback
    $prihlaseniLeadera = []; // pro rollback kol u leadera
    $chybny = null; // pro uživatele jehož jméno se zobrazí v rámci chyby
    try
    {
      dbBegin();
      $a = Aktivita::zId(post(self::TEAMKLIC.'Aktivita'));
      if($leader->id() != $a->a['zamcel']) throw new Exception('Nejsi teamleader.');
      // přihlášení teamleadera na zvolená další kola (pokud jsou)
      $kola = post(self::KOLA) ?: [];
      foreach($kola as $koloId) {
        $kolo = self::zId($koloId);
        $kolo->prihlas($leader, self::STAV);
        $prihlaseniLeadera[] = $kolo;
      }
      // načtení zvolených členů teamu
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
      // hotovo, odemčít aktivitu, snížit počet míst a nastavit název týmu
      dbUpdate('akce_seznam', [
        'kapacita'    =>  $a->a['kapacita'] - $zamceno,
        'zamcel'      =>  null,
        'zamcel_cas'  =>  null,
        'team_nazev'  =>  post(self::TEAMKLIC.'Nazev') ?: null,
      ], ['id_akce' => $a->id()]);
    }
    catch(Exception $e)
    {
      dbRollback();
      // zobrazení
      if($chybny)
        $chyby[] = 'Nelze, uživateli '.$chybny->jmenoNick().'('.$chybny->id().')'." se při přihlašování objevila chyba:\n• ".$e->getMessage();
      else
        $chyby[] = $e->getMessage();
    }
    if(empty($chyby)) dbCommit();
    echo json_encode(['chyby'=>$chyby]);
    die();
  }

  /**
   * Má aktivita vyplněnou prezenci?
   * (aktivity s 0 lidmi jsou považovány za nevyplněné vždycky)
   */
  function vyplnenaPrezence() {
    return 0 < dbOneCol('SELECT MAX(id_stavu_prihlaseni) FROM akce_prihlaseni WHERE id_akce = '.$this->id());
  }

  /** Vrátí DateTime objekt začátku aktivity */
  function zacatek() {
    if(is_string($this->a['zacatek']))
      $this->a['zacatek'] = new DateTimeCz($this->a['zacatek']);
    return $this->a['zacatek'];
  }

  /** Je aktivita už proběhlá resp. už uzavřená pro změny? */
  function zamcena() {
    return $this->a['stav'] == 2;
  }

  /** Zamče aktivitu pro další změny (k použití před jejím začátkem) */
  function zamci() {
    dbQuery('UPDATE akce_seznam SET stav = 2 WHERE id_akce = ' . $this->id());
    // TODO invalidate $this
  }

  /**
   * Vrátí pole aktivit s zadaným filtrem a řazením. Filtr funguje jako asoc.
   * pole s filtrovanými hodnotami, řazení jako pole s pořadím dle priorit.
   * Podporované volby filtru: (vše id nebo boolean)
   *  rok, typ, organizator, jenViditelne
   * @todo filtr dle orga
   * @todo explicitní filtr i pro řazení (např. pole jako mapa veřejný řadící
   *  parametr => sloupec
   */
  static function zFiltru($filtr, $razeni = []) {
    // sestavení filtrů
    $wheres = [];
    if(!empty($filtr['rok']))
      $wheres[] = 'a.rok = '.(int)$filtr['rok'];
    if(!empty($filtr['typ']))
      $wheres[] = 'a.typ = '.(int)$filtr['typ'];
    if(!empty($filtr['organizator']))
      $wheres[] = 'a.id_akce IN (SELECT id_akce FROM akce_organizatori WHERE id_uzivatele = '.(int)$filtr['organizator'].')';
    if(!empty($filtr['jenViditelne']))
      $wheres[] = 'a.stav IN(1,2,4,5) AND NOT (a.typ = 10 AND a.stav = 2)';
    if(!empty($filtr['od']))
      $wheres[] = dbQv($filtr['od']).' <= a.zacatek';
    if(!empty($filtr['do']))
      $wheres[] = 'a.zacatek <= '.dbQv($filtr['do']);
    $where = implode(' AND ', $wheres);
    $order = null;
    foreach($razeni as $sloupec) {
      $order[] = dbQi($sloupec);
    }
    if($order) $order = 'ORDER BY '.implode(', ', $order);
    // select
    $aktivity = (array)self::zWhere('WHERE '.$where, null, $order); // přetypování nutné kvůli správné funkci unsetu
    if(!empty($filtr['jenVolne'])) {
      foreach($aktivity as $id => $a) {
        if($a->volno() == 'x') unset($aktivity[$id]);
      }
    }
    return $aktivity;
  }

  /**
   * Pokusí se vyčíst aktivitu z dodaného ID. Vrátí aktivitu nebo null
   */
  static function zId($id)
  {
    if((int)$id)
      return self::zWhere('WHERE a.id_akce='.(int)$id)->current();
    else
      return null;
  }

  /**
   * Načte aktivitu z pole ID nebo řetězce odděleného čárkami
   * @todo sanitizace před veřejným použitím a podpora řetězce, nejen pole
   */
  static function zIds($ids) {
    if(!is_array($ids)) $ids = explode(',', $ids);
    return self::zWhere('WHERE a.id_akce IN('.dbQa($ids).')');
  }

  /**
   * Vrátí všechny aktivity, které vede daný uživatel
   */
  static function zOrganizatora(Uzivatel $u) {
    // join hack na akt. uživatele
    return self::zWhere('JOIN akce_organizatori ao ON (ao.id_akce = a.id_akce AND ao.id_uzivatele = '.$u->id().') WHERE a.rok = '.ROK);
  }

  /**
   * Vrátí pole aktivit které se letos potenciálně zobrazí v programu
   */
  static function zProgramu($order) {
    return self::zWhere(
      'WHERE a.rok = $1 AND a.zacatek AND ( a.stav IN(1,2,3,4,5) OR a.typ = 10 )',
      [ROK],
      'ORDER BY DAY(zacatek), '.dbQi($order).', HOUR(zacatek), nazev_akce'
    );
  }

  /**
   * Vrátí aktivity z rozmezí (aktuálně s začátkem v rozmezí konkrétně)
   * @todo možno přidat flag 'celé v rozmezí'
   */
  static function zRozmezi(DateTimeCz $od, DateTimeCz $do, $flags = 0) {
    $aktivity = self::zFiltru([
      'jenViditelne'  =>  (bool)($flags & self::VEREJNE),
      'od'            =>  $od->formatDb(),
      'do'            =>  $do->formatDb(),
    ]);
    if($flags & self::JEN_VOLNE)
      foreach($aktivity as $i => $a)
        if($a->volno() == 'x') unset($aktivity[$i]);
    return $aktivity;
  }

  /**
   * Vrátí pole instancí s danou url (jen ty letošní veřejně viditelné)
   * @param $url url aktivity
   * @param $typ url typu
   */
  static function zUrlViditelne($url, $typ) {
    return self::zWhere(
      'WHERE at.url_typu = $1 AND a.stav IN(1,2,4,5) AND a.rok = $3 AND (
        a.url_akce = $2 OR IF(a.patri_pod, a.patri_pod = (
          SELECT patri_pod FROM akce_seznam WHERE url_typu = $1 AND stav IN(1,2,4,5) AND rok = $3 AND url_akce = $2
        ), 0)
      )',
      [$typ, $url, ROK],
      'ORDER BY zacatek, id_akce'
    );
  }

  /**
   * Vrátí iterátor letošních aktivit daného uživatele
   */
  static function zUzivatele(Uzivatel $u) {
    return self::zWhere(
      'WHERE a.rok = $1 AND a.id_akce IN(SELECT id_akce FROM akce_prihlaseni WHERE id_uzivatele = $2)',
      [ROK, $u->id()]
    );
  }

  /**
   * Vrátí iterátor s aktivitami podle zadané where klauzule. Alias tabulky
   * akce_seznam je 'a'.
   * @param $where obsah where klauzule (bez úvodního klíč. slova WHERE)
   * @param $args volitelné pole argumentů pro dbQueryS()
   * @param $order volitelně celá klauzule ORDER BY včetně klíč. slova
   * @todo třída která obstará reálný iterátor, nejenom obalení pole (nevýhoda
   *  pole je nezměněná nutnost čekat, než se celá odpověď načte a přesype do
   *  paměti
   */
  protected static function zWhere($where, $args = null, $order = null) {
    $url_akce       = 'IF(t2.patri_pod, (SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod = t2.patri_pod), t2.url_akce) as url_temp';
    $prihlaseni     = 'CONCAT(",",GROUP_CONCAT(p.id_uzivatele,u.pohlavi,p.id_stavu_prihlaseni),",") AS prihlaseni';
    $tagy           = 'GROUP_CONCAT(t.nazev) as tagy';
    $o = dbQueryS("
      SELECT t3.*, $tagy FROM (
        SELECT t2.*, $prihlaseni, $url_akce FROM (
          SELECT a.*, at.url_typu, al.poradi
          FROM akce_seznam a
          LEFT JOIN akce_typy at ON (at.id_typu = a.typ)
          LEFT JOIN akce_lokace al ON (al.id_lokace = a.lokace)
          $where
        ) as t2
        LEFT JOIN akce_prihlaseni p ON (p.id_akce = t2.id_akce)
        LEFT JOIN uzivatele_hodnoty u ON (u.id_uzivatele = p.id_uzivatele)
        GROUP BY t2.id_akce
      ) as t3
      LEFT JOIN akce_tagy at ON (at.id_akce = t3.id_akce)
      LEFT JOIN tagy t ON (t.id = at.id_tagu)
      GROUP BY t3.id_akce
      $order
    ", $args);

    $kolekce = new ArrayIterator();
    while($r = mysqli_fetch_assoc($o)) {
      $r['url_akce'] = $r['url_temp'];
      $aktivita = new self($r);
      $aktivita->kolekce = $kolekce;
      $kolekce[$r['id_akce']] = $aktivita;
    }

    return $kolekce;
  }

}
