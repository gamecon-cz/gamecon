<?php

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */

class Finance
{
  protected
    $u,       // uživatel, jehož finance se počítají
    $stav=0,  // celkový výsledný stav uživatele na účtu
    $deltaPozde=0,      // o kolik se zvýší platba při zaplacení pozdě
    $scnA,              // součinitel ceny aktivit
    $logovat = true,    // ukládat seznam předmětů?
    $cenik,             // instance ceníku
    // tabulky s přehledy
    $prehled=array(),   // tabulka s detaily o platbách
    $slevyA=array(),    // pole s textovými popisy slev uživatele na aktivity
    $slevyO=array(),    // pole s textovými popisy obecných slev
    // součásti výsledné ceny
    $cenaAktivity   = 0.0,  // cena aktivit
    $cenaUbytovani  = 0.0,  // cena objednaného ubytování
    $cenaPredmety   = 0.0,  // cena předmětů a dalších objednávek v shopu
    $sleva          = 0.0,  // sleva za tech. aktivity a odvedené aktivity
    $zustatek       = 0.0,  // zůstatek z minula
    $platby         = 0.0;  // platby připsané na účet

  protected static
    $maxSlevaAktivit=100, // v procentech
    $slevaZaAktivitu = array( // ve formátu max. délka => sleva
      1  =>  60,
      2  =>  90,
      5  => 180,
      7  => 270,
      9  => 360,
      11 => 450,
      13 => 540,
    );
  
  const
    // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    AKTIVITA      = -1,
    // mezera na typy předmětů (1-4? viz db)
    ORGSLEVA      = 10,
    CELKOVA       = 11,
    PLATBY_NADPIS = 12,
    ZUSTATEK      = 13,
    PLATBA        = 14,
    VYSLEDNY      = 15;
  
  /**
   * Konstruktor
   * @param Uzivatel $u uživatel, pro kterého se finance sestavují   
   */
  function __construct(Uzivatel $u)
  {
    $this->u = $u;
    if(!$u->gcPrihlasen()) return;

    $this->cenik = new Cenik($u);

    $this->zapoctiAktivity();
    $this->zapoctiShop();
    $this->zapoctiPlatby();
    $this->zapoctiZustatek();
    $this->zapoctiVedeniAktivit();

    $cena =
      + $this->cenaPredmety
      + $this->cenaUbytovani
      + $this->cenaAktivity;

    $sleva = $this->sleva;
    Cenik::aplikujSlevu($cena, $sleva);
    if($this->sleva) $this->log(
      'Využitá sleva za organizované aktivity (z celkem '.$this->sleva.')',
      $this->sleva - $sleva,
      self::ORGSLEVA);

    $this->log('Celková cena', $cena, self::CELKOVA);

    $this->stav =
      - $cena
      + $this->platby
      + $this->zustatek;

    $this->log('<b>Stav financí</b>', '<b>'.$this->stav.'</b>', self::VYSLEDNY);
  }

  /** Cena za uživatelovy aktivity */
  function cenaAktivity() {
    return $this->cenaAktivity;
  }

  /** Cena za objednané předměty */
  function cenaPredmety() {
    return $this->cenaPredmety;
  }

  /** Cena za objednané ubytování */
  function cenaUbytovani() {
    return $this->cenaUbytovani;
  }

  /** Porovnávání k řazení php 4 style :/ */
  private function cmp($a,$b) {
    // podle typu
    $m = $a[2] - $b[2];
    if($m) return $m;
    // podle názvu
    $o = strcmp($a[0], $b[0]);
    if($o) return $o;
    // podle ceny
    return $a[1] - $b[1];
  }

  /**
   * Zaloguje do seznamu nákupů položku (pokud je logování zapnuto)
   */
  protected function log($nazev, $castka, $kategorie = null) {
    if(!$this->logovat) return;
    if(is_numeric($castka)) $castka = round($castka);
    // hack změna řazení
    $nkat = $kategorie;
    if($kategorie == 2) $nkat = 3;
    if($kategorie == 3) $nkat = 2;
    $kategorie = $nkat;
    // přidání
    $this->prehled[] = array(
      $nazev,
      $castka,
      $kategorie
    );
  }

  /** Vrátí sumu plateb (připsaných peněz) */
  function platby()
  { return $this->platby; }
  
  /**
   * Vrátí html formátovaný přehled financí
   * @todo přesun css někam sdíleně
   */
  function prehledHtml() {
    usort($this->prehled,'self::cmp');
    $out='';
    $out.='<table>';
    foreach($this->prehled as $r)
      $out.= '<tr><td style="text-align:left">' . $r[0] . '</td>'.
        '<td style="text-align:right">' . $r[1] . '</td></tr>';
    $out.='</table>';
    return $out;
  }
  
  /**
   * Připíše uživateli $u platbu ve výši $castka aktuálnímu uživateli
   * @todo rozlišení neznámý uživatel (nezadáno) vs. provedl systém   
   */
  static function pripis(Uzivatel $u,$castka,$poznamka=null,Uzivatel $provedl=null)
  {
    $poznamka= empty($poznamka) ? null : $poznamka;
    $orgId= $provedl instanceof Uzivatel ? $provedl->id() : 0;
    dbInsertUpdate('platby',array(
      'id_uzivatele'=>$u->id(),
      'castka'=>$castka,
      'rok'=>ROK,
      'provedeno'=>date("Y-m-d H:i:s"),
      'provedl'=>$orgId,
      'poznamka'=>$poznamka
    ));
  }

  /** Vrátí aktuální stav na účtu uživatele pro tento rok */
  function stav()
  { return $this->stav; }
  
  /** Vrátí člověkem čitelný stav účtu */
  function stavHr()
  { return $this->stav().'&thinsp;Kč'; }
  
  /** Vrátí stav na účtu uživatele pro tento rok, pokud by neplatila sleva za včasnou platbu */
  function stavPozde()
  { return $this->stav-$this->deltaPozde; }
  
  /**
   * Vrací součinitel ceny aktivit jako float číslo. Např. 0.0 pro aktivity
   * zdarma a 1.0 pro aktivity za plnou cenu.   
   */
  function slevaAktivity()
  {
    return $this->soucinitelAktivit(); //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
  }
  
  /**
   * @todo přesunout do ceníku (viz nutnost počítání součinitele aktivit)
   */
  function slevyAktivity() {
    //return $this->cenik->slevyObecne();
    return $this->slevyA;
  }
  
  /**
   * Viz ceník
   */
  function slevyVse() {
    return $this->cenik->slevySpecialni();
  }

  /**
   * Vrátí součinitel ceny aktivit, tedy slevy uživatele vztahující se k
   * aktivitám. Vrátí hodnotu.
   * @todo přesunout výpočet konstant pro židle předloni, předpředloni atd…
   */
  protected function soucinitelAktivit()
  {
    if(!isset($this->scnA)) {
      // pomocné proměnné
      $sleva=0; // v procentech
      $uid=$this->u->id();
      $rok=ROK;
      $zLoni=   -(ROK%2000-1)*100-1;
      $zPLoni=  -(ROK%2000-2)*100-1;
      $zPPLoni= -(ROK%2000-3)*100-1;
      // výpočet pravidel
      if($this->u->maPravo(P_SLEVA_STUDENT))
        // sleva 20%, pokud je student
        $sleva+=20 xor
        $this->slevyA[]='studentská sleva 20%'.($this->u->maPravo(P_ORG_AKCI)?' (pro vypravěče automaticky)':'');
      if($this->u->maPravo(P_SLEVA_VCAS) || SLEVA_AKTIVNI)
        // sleva 20%, pokud zaplatil včas (resp. ještě může zaplatit včas)
        $sleva+=20 xor
        $this->slevyA[]='sleva 20% za včasnou platbu'.(SLEVA_AKTIVNI?' (pokud zaplatíš do '.datum3(SLEVA_DO).' nebo už máš zaplaceno)':'');
      if(($novacku=dbOneCol("
        SELECT count(1)
        FROM uzivatele_hodnoty u
        LEFT JOIN r_uzivatele_zidle z ON(u.id_uzivatele=z.id_uzivatele AND ( z.id_zidle=$zLoni OR z.id_zidle=$zPLoni OR z.id_zidle=$zPPLoni ))
        WHERE u.guru=$uid AND ISNULL(z.id_zidle)
        "))>0)
        // sleva 20% za _každého_ nováčka
        $sleva+=$novacku*20 xor
        $this->slevyA[]='za každého nového účastníka 20% (tj. '.($novacku*20).'% celkem)';
      if($sleva>self::$maxSlevaAktivit)
        // omezení výše slevy na maximální hodnotu
        $sleva=self::$maxSlevaAktivit;
      $slevaAktivity=(100-$sleva)/100;
      // výsledek
      $this->scnA = $slevaAktivity;
    }
    return $this->scnA;
  }

  /**
   * Započítá do mezisoučtů aktivity uživatele
   * @todo odstranit zbytečnosti
   */
  protected function zapoctiAktivity() {
    $scn = $this->soucinitelAktivit();
    $rok = ROK;
    $uid = $this->u->id();
    $o = dbQuery("
      SELECT
        a.nazev_akce as nazev,
        a.cena *
          (st.platba_procent/100) *
          IF(a.bez_slevy OR a.typ=10, 1.0, $scn) *
          IF(a.typ=10,-1.0,1.0) as cena
      FROM (
        SELECT * FROM akce_prihlaseni WHERE id_uzivatele = $uid
        UNION
        SELECT * FROM akce_prihlaseni_spec WHERE id_uzivatele = $uid) p
      JOIN akce_seznam a USING(id_akce)
      JOIN akce_prihlaseni_stavy st USING(id_stavu_prihlaseni)
      WHERE rok = $rok
    ");
    while($r = mysql_fetch_assoc($o)) {
      if($r['cena'] >= 0) {
        $this->cenaAktivity += $r['cena'];
      } else {
        $this->sleva -= $r['cena'];
      }
      $this->log($r['nazev'], $r['cena'] < 0 ? 0 : $r['cena'], self::AKTIVITA);
    }
  }

  /**
   * Započítá do mezisoučtů platby na účet
   * @todo odstranit zbytečnosti
   */
  protected function zapoctiPlatby() {
    $rok = ROK;
    $uid = $this->u->id();
    $o = dbQuery("
      SELECT
        IF(provedl=1,
          CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' Platba na účet'),
          CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' ',IFNULL(poznamka,'(bez poznámky)'))
          ) as nazev,
        castka as cena
      FROM platby
      WHERE id_uzivatele = $uid AND rok = $rok
    ");
    while($r = mysql_fetch_assoc($o)) {
      $this->platby += $r['cena'];
      $this->log($r['nazev'], $r['cena'], self::PLATBA);
    }
    $this->log('<b>Připsané platby</b>', '', self::PLATBY_NADPIS);
  }

  /**
   * Započítá do mezisoučtů nákupy v eshopu
   */
  protected function zapoctiShop() {
    $rok = ROK;
    $uid = $this->u->id();
    $o = dbQuery("
      SELECT p.nazev, n.cena_nakupni, p.typ, p.ubytovani_den, p.model_rok
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $uid AND n.rok = $rok
    ");
    while($r = mysql_fetch_assoc($o)) {
      $cena = $this->cenik->shop($r);
      if($r['typ'] == Shop::UBYTOVANI) {
        $this->cenaUbytovani += $cena;
      } else {
        $this->cenaPredmety += $cena;
      }
      if($r['model_rok'] != ROK) {
        $r['nazev'] = $r['nazev'].' '.$r['model_rok'];
      }
      $this->log($r['nazev'], $cena, $r['typ']);
    }
  }

  /**
   * Započítá do mezisoučtů slevy za organizované aktivity
   */
  protected function zapoctiVedeniAktivit() {
    if(!$this->u->maPravo(P_ORG_AKCI)) return;
    foreach(Aktivita::zOrganizatora($this->u) as $a) {
      $delka = $a->delka();
      $sleva = 0;
      foreach(self::$slevaZaAktivitu as $tabDelka => $tabSleva) {
        if($delka <= $tabDelka) {
          $sleva = $tabSleva;
          break;
        }
      }
      $this->sleva += $sleva;
    }
  }

  /**
   * Započítá do mezisoučtů zůstatek z minulých let
   */
  protected function zapoctiZustatek() {
    $this->zustatek += dbOneCol(
      'SELECT zustatek FROM uzivatele_hodnoty WHERE id_uzivatele = '.$this->u->id()
    );
    $this->log('Zůstatek z minulých let', $this->zustatek, self::ZUSTATEK);
  }
  
}
