<?php

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */
class Finance {

  protected
    $u,       // uživatel, jehož finance se počítají
    $stav=0,  // celkový výsledný stav uživatele na účtu
    $deltaPozde=0,      // o kolik se zvýší platba při zaplacení pozdě
    $scnA,              // součinitel ceny aktivit
    $logovat = true,    // ukládat seznam předmětů?
    $cenik,             // instance ceníku
    // tabulky s přehledy
    $prehled=[],   // tabulka s detaily o platbách
    $slevyA=[],    // pole s textovými popisy slev uživatele na aktivity
    $slevyO=[],    // pole s textovými popisy obecných slev
    // součásti výsledné ceny
    $cenaAktivity   = 0.0,  // cena aktivit
    $cenaUbytovani  = 0.0,  // cena objednaného ubytování
    $cenaPredmety   = 0.0,  // cena předmětů a dalších objednávek v shopu
    $cenaVstupne    = 0.0,
    $cenaVstupnePozde = 0.0,
    $sleva          = 0.0,  // sleva za tech. aktivity a odvedené aktivity
    $slevaVyuzita   = 0.0,  // sleva za odvedené aktivity (využitá část)
    $zustatek       = 0,    // zůstatek z minula
    $platby         = 0.0,  // platby připsané na účet
    $posledniPlatba;        // datum poslední připsané platby

  protected static
    $maxSlevaAktivit=100, // v procentech
    $slevaZaAktivitu = [ // ve formátu max. délka => sleva
      1  =>  55,
      2  => 110,
      5  => 220,
      7  => 330,
      9  => 440,
      11 => 550,
      13 => 660,
    ];

  const
    // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    AKTIVITA      = -1,
    // mezera na typy předmětů (1-4? viz db)
    ORGSLEVA      = 10,
    VSTUPNE       = 11,
    CELKOVA       = 12,
    PLATBY_NADPIS = 13,
    ZUSTATEK      = 14,
    PLATBA        = 15,
    VYSLEDNY      = 16;

  /**
   * @param Uzivatel $u uživatel, pro kterého se finance sestavují   
   * @param int $zustatek zůstatek na účtu z minulých GC
   */
  function __construct(Uzivatel $u, int $zustatek) {
    $this->u = $u;
    $this->zustatek = $zustatek;
    if(!$u->gcPrihlasen()) return;

    $this->zapoctiAktivity();
    $this->zapoctiVedeniAktivit();

    $this->cenik = new Cenik($u, $this->sleva);

    $this->zapoctiShop();
    $this->zapoctiPlatby();
    $this->zapoctiZustatek();

    $cena =
      + $this->cenaPredmety
      + $this->cenaUbytovani
      + $this->cenaAktivity;

    $sleva = $this->sleva;
    Cenik::aplikujSlevu($cena, $sleva);
    $this->slevaVyuzita = $this->sleva - $sleva;
    if($this->sleva) $this->log(
      '<b>Sleva za organizované aktivity</b><br>využitá z celkem '.$this->slevaVypravecMax().'',
      '<b>'.$this->slevaVypravecVyuzita().'</b><br>&emsp;',
      self::ORGSLEVA);

    $cena = $cena
      + $this->cenaVstupne
      + $this->cenaVstupnePozde;

    $this->logb('Celková cena', $cena, self::CELKOVA);

    $this->stav =
      - $cena
      + $this->platby
      + $this->zustatek;

    $this->logb('Aktivity', $this->cenaAktivity, self::AKTIVITA);
    $this->logb('Ubytování', $this->cenaUbytovani, 2);
    $this->logb('Předměty a strava', $this->cenaPredmety, 1);
    $this->logb('Připsané platby', $this->platby + $this->zustatek, self::PLATBY_NADPIS);
    $this->logb('Stav financí', $this->stav, self::VYSLEDNY);
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
    if($kategorie == 2) $nkat = 4;
    if($kategorie == 3) $nkat = 2;
    if($kategorie == 4) $nkat = 3;
    $kategorie = $nkat;
    // přidání
    $this->prehled[] = [
      $nazev,
      $castka,
      $kategorie
    ];
  }

  /**
   * Zaloguje zvýrazněný záznam
   */
  protected function logb($nazev, $castka, $kategorie = null) {
    $this->log("<b>$nazev</b>", "<b>$castka</b>", $kategorie);
  }

  /** Vrátí sumu plateb (připsaných peněz) */
  function platby() {
    return $this->platby;
  }

  /**
   * Vrátí / nastaví datum posledního provedení platby
   *
   * @return string datum poslední platby
   */
  function posledniPlatba() {
    if(!isset($this->posledniPlatba)) {
      $uid = $this->u->id();
      $this->posledniPlatba = dbOneCol("
        SELECT max(provedeno) as datum
        FROM platby
        WHERE castka > 0 AND id_uzivatele = $1",[$uid]
      );
    }
    return $this->posledniPlatba;
  }

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
  static function pripis(Uzivatel $u, $castka, $poznamka = null, Uzivatel $provedl = null) {
    $poznamka= empty($poznamka) ? null : $poznamka;
    $orgId= $provedl instanceof Uzivatel ? $provedl->id() : 0;
    dbInsertUpdate('platby',[
      'id_uzivatele'=>$u->id(),
      'castka'=>$castka,
      'rok'=>ROK,
      'provedeno'=>date("Y-m-d H:i:s"),
      'provedl'=>$orgId,
      'poznamka'=>$poznamka
    ]);
  }

  /** Vrátí aktuální stav na účtu uživatele pro tento rok */
  function stav() {
    return $this->stav;
  }

  /** Vrátí člověkem čitelný stav účtu */
  function stavHr() {
    return $this->stav().'&thinsp;Kč';
  }

  /** Vrátí stav na účtu uživatele pro tento rok, pokud by neplatila sleva za včasnou platbu */
  function stavPozde() {
    return $this->stav - $this->deltaPozde;
  }

  /**
   * Vrací součinitel ceny aktivit jako float číslo. Např. 0.0 pro aktivity
   * zdarma a 1.0 pro aktivity za plnou cenu.   
   */
  function slevaAktivity() {
    return $this->soucinitelAktivit(); //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
  }

  /**
   * Vrátí výchozí vygenerovanou slevu za vedení dané aktivity
   */
  static function slevaZaAktivitu(Aktivita $a) {
    if($a->nedavaSlevu()) return 0;
    $delka = $a->delka();
    if($delka == 0) return 0;
    $sleva = 0;
    foreach(self::$slevaZaAktivitu as $tabDelka => $tabSleva) {
      if($delka <= $tabDelka) {
        $sleva = $tabSleva;
        break;
      }
    }
    return $sleva;
  }

  /**
   * Výše vypravěčské slevy (celková)
   */
  function slevaVypravecMax() {
    return $this->sleva;
  }

  /**
   * Výše vyčerpané vypravěčské slevy
   */
  function slevaVypravecVyuzita() {
    return $this->slevaVyuzita;
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
  protected function soucinitelAktivit() {
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
   * Součinitel ceny za aktivity při pozdní platbě
   * @todo hardcode obejití pro vypravěče není dobrý :(
   */
  protected function soucinitelAktivitPozde() {
    if(SLEVA_AKTIVNI && !$this->u->maPravo(P_ORG_AKCI))
      return $this->soucinitelAktivit() + 0.2;
    else
      return $this->soucinitelAktivit();
  }

  function vstupne() {
    return $this->cenaVstupne;
  }

  function vstupnePozde() {
    return $this->cenaVstupnePozde;
  }

  /**
   * Započítá do mezisoučtů aktivity uživatele
   * @todo odstranit zbytečnosti
   */
  protected function zapoctiAktivity() {
    $scn = $this->soucinitelAktivit();
    $scnPozde = $this->soucinitelAktivitPozde();
    $rok = ROK;
    $uid = $this->u->id();
    $o = dbQuery("
      SELECT
        a.nazev_akce as nazev,
        a.cena *
          (st.platba_procent/100) *
          IF(a.bez_slevy OR a.typ=10, 1.0, $scn) *
          IF(a.typ = 10 AND p.id_stavu_prihlaseni IN(3,4), 0.0, 1.0) *    -- zrušit 'storno' pro pozdě odhlášené tech. aktivity
          IF(a.typ=10,-1.0,1.0) as cena,
        a.cena *
          (st.platba_procent/100) *
          IF(a.bez_slevy OR a.typ=10, 1.0, $scnPozde) *
          IF(a.typ = 10 AND p.id_stavu_prihlaseni IN(3,4), 0.0, 1.0) *
          IF(a.typ=10,-1.0,1.0) as cenaPozde,
        st.id_stavu_prihlaseni
      FROM (
        SELECT * FROM akce_prihlaseni WHERE id_uzivatele = $uid
        UNION
        SELECT * FROM akce_prihlaseni_spec WHERE id_uzivatele = $uid) p
      JOIN akce_seznam a USING(id_akce)
      JOIN akce_prihlaseni_stavy st USING(id_stavu_prihlaseni)
      WHERE rok = $rok
    ");
    $a = $this->u->koncA();
    while($r = mysqli_fetch_assoc($o)) {
      if($r['cena'] >= 0) {
        $this->cenaAktivity += $r['cena'];
        $this->deltaPozde += $r['cenaPozde'] - $r['cena'];
      } else {
        $this->sleva -= $r['cena'];
      }
      $poznamka = '';
      if($r['id_stavu_prihlaseni'] == 3) $poznamka = " <i>(nedorazil$a)</i>";
      if($r['id_stavu_prihlaseni'] == 4) $poznamka = " <i>(odhlášen$a pozdě)</i>";
      $this->log($r['nazev'].$poznamka, $r['cena'] < 0 ? 0 : $r['cena'], self::AKTIVITA);
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
    while($r = mysqli_fetch_assoc($o)) {
      $this->platby += $r['cena'];
      $this->log($r['nazev'], $r['cena'], self::PLATBA);
    }
  }

  /**
   * Započítá do mezisoučtů nákupy v eshopu
   */
  protected function zapoctiShop() {
    $rok = ROK;
    $uid = $this->u->id();
    $o = dbQuery("
      SELECT p.id_predmetu, p.nazev, n.cena_nakupni, p.typ, p.ubytovani_den, p.model_rok
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $uid AND n.rok = $rok
    ");
    $soucty = [];
    while($r = mysqli_fetch_assoc($o)) {
      $cena = $this->cenik->shop($r);
      // započtení ceny
      if($r['typ'] == Shop::UBYTOVANI) {
        $this->cenaUbytovani += $cena;
      } elseif($r['typ'] == Shop::VSTUPNE) {
        if(strpos($r['nazev'], 'pozdě') === false)  $this->cenaVstupne = $cena;
        else                                        $this->cenaVstupnePozde = $cena;
      } else {
        $this->cenaPredmety += $cena;
      }
      // přidání roku do názvu
      if($r['model_rok'] != ROK) {
        $r['nazev'] = $r['nazev'].' '.$r['model_rok'];
      }
      // logování do výpisu
      if($r['typ'] == Shop::PREDMET) {
        $soucty[$r['id_predmetu']]['nazev'] = $r['nazev'];
        $soucty[$r['id_predmetu']]['typ'] = $r['typ'];
        @$soucty[$r['id_predmetu']]['pocet']++;
        @$soucty[$r['id_predmetu']]['suma'] += $cena;
      } elseif($r['typ'] == Shop::VSTUPNE) {
        $this->logb($r['nazev'], $cena, self::VSTUPNE);
      } else {
        $this->log($r['nazev'], $cena, $r['typ']);
      }
    }
    foreach($soucty as $p) {
      $this->log($p['nazev'].'  '.$p['pocet'].'×', $p['suma'], $p['typ']); // dvojmezera kvůli řazení
    }
  }

  /**
   * Započítá do mezisoučtů slevy za organizované aktivity
   */
  protected function zapoctiVedeniAktivit() {
    if(!$this->u->maPravo(P_ORG_AKCI)) return;
    if(!$this->u->maPravo(P_SLEVA_AKTIVITY)) return;
    foreach(Aktivita::zOrganizatora($this->u) as $a) {
      $this->sleva += self::slevaZaAktivitu($a);
    }
  }

  /**
   * Započítá do mezisoučtů zůstatek z minulých let
   */
  protected function zapoctiZustatek() {
    $this->log('Zůstatek z minulých let', $this->zustatek, self::ZUSTATEK);
  }

  /**
   * @return int zůstatek na účtu z minulých GC
   */
  function zustatek() {
    return $this->zustatek;
  }
}
