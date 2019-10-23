<?php

/**
 * Třída zodpovídající za spočítání finanční bilance uživatele na GC.
 */
class Finance {

  private
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
    $slevaObecna    = 0.0,  // sleva získaná z tabulky slev
    $slevaVyuzita   = 0.0,  // sleva za odvedené aktivity (využitá část)
    $zustatek       = 0,    // zůstatek z minula
    $platby         = 0.0,  // platby připsané na účet
    $posledniPlatba;        // datum poslední připsané platby

  private static
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
    AKTIVITA        = -1,
    PREDMETY_STRAVA = 1,
    UBYTOVANI       = 2,
    // mezera na typy předmětů (1-4? viz db)
    ORGSLEVA        = 10,
    PRIPSANE_SLEVY  = 11,
    VSTUPNE         = 12,
    CELKOVA         = 13,
    PLATBY_NADPIS   = 14,
    ZUSTATEK        = 15,
    PLATBA          = 16,
    VYSLEDNY        = 17;

  /**
   * @param Uzivatel $u uživatel, pro kterého se finance sestavují
   * @param int $zustatek zůstatek na účtu z minulých GC
   */
  function __construct(Uzivatel $u, int $zustatek) {
    $this->u = $u;
    $this->zustatek = $zustatek;

    $this->zapoctiVedeniAktivit();
    $this->zapoctiSlevy();

    $this->cenik = new Cenik($u, $this->sleva); // musí být načteno, i pokud není přihlášen na GC

    if(!$u->gcPrihlasen()) return;

    $this->zapoctiAktivity();
    $this->zapoctiShop();
    $this->zapoctiPlatby();
    $this->zapoctiZustatek();

    $cena =
      + $this->cenaPredmety
      + $this->cenaUbytovani
      + $this->cenaAktivity;

    $sleva = $this->sleva;
    ['cena' => $cena, 'sleva' => $sleva] = Cenik::aplikujSlevu($cena, $sleva);
    $this->slevaVyuzita = $this->sleva - $sleva;
    if($this->sleva) {
      $this->log(
        '<b>Sleva za organizované aktivity</b><br>využitá z celkem ' . $this->slevaVypravecMax() . '',
        '<b>' . $this->slevaVypravecVyuzita() . '</b><br>&emsp;',
        self::ORGSLEVA);
    }

    $zbyvajiciObecnaSleva = $this->slevaObecna;
    ['cena' => $cena, 'sleva' => $zbyvajiciObecnaSleva] = Cenik::aplikujSlevu($cena, $zbyvajiciObecnaSleva);
    if($this->slevaObecna) {
      $this->log(
        '<b>Sleva</b><br>využitá z celkem ' . $this->slevaObecna,
        '<b>' . ($this->slevaObecna - $zbyvajiciObecnaSleva) . '</b>',
        self::PRIPSANE_SLEVY);
    }

    $cena = $cena
      + $this->cenaVstupne
      + $this->cenaVstupnePozde;

    $this->logb('Celková cena', $cena, self::CELKOVA);

    $this->stav =
      - $cena
      + $this->platby
      + $this->zustatek;

    $this->logb('Aktivity', $this->cenaAktivity, self::AKTIVITA);
    $this->logb('Ubytování', $this->cenaUbytovani, self::UBYTOVANI);
    $this->logb('Předměty a strava', $this->cenaPredmety, self::PREDMETY_STRAVA);
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
  private function log($nazev, $castka, $kategorie = null) {
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
  private function logb($nazev, $castka, $kategorie = null) {
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
    $out='<table>';
    foreach($this->serazenyPrehled() as $r) {
      $out .= '<tr><td style="text-align:left">' . $r[0] . '</td><td style="text-align:right">' . $r[1] . '</td></tr>';
    }
    $out.='</table>';
    return $out;
  }

  public function prehledPopis(): string {
    $out = [];
    foreach($this->serazenyPrehled() as $r) {
      $out[] = $r[0] . ' ' . $r[1];
    }
    return implode(', ', $out);
  }

  private function serazenyPrehled(): array {
    $prehled = $this->prehled;
    usort($prehled,[static::class, 'cmp']);
    return $prehled;
  }

  /**
   * Připíše aktuálnímu uživateli platbu ve výši $castka.
   * @param float $castka
   * @param Uzivatel $provedl
   * @param string|null $poznamka
   */
  function pripis($castka, Uzivatel $provedl, $poznamka = null) {
    dbQuery(
      'INSERT INTO platby(id_uzivatele, castka, rok, provedl, poznamka) VALUES ($1, $2, $3, $4, $5)',
      [$this->u->id(), $castka, ROK, $provedl->id(), $poznamka ?: null]
    );
  }

  /**
   * Připíše aktuálnímu uživateli $u slevu ve výši $sleva
   * @param float $sleva
   * @param string|null $poznamka
   * @param Uzivatel $provedl
   */
  function pripisSlevu($sleva, $poznamka, Uzivatel $provedl) {
    dbQuery(
      'INSERT INTO slevy(id_uzivatele, castka, rok, provedl, poznamka) VALUES ($1, $2, $3, $4, $5)',
      [$this->u->id(), $sleva, ROK, $provedl->id(), $poznamka ?: null]
    );
  }

  /** Vrátí aktuální stav na účtu uživatele pro tento rok */
  function stav() {
    return $this->stav;
  }

  /** Vrátí výši obecné slevy připsané uživateli pro tento rok. */
  function slevaObecna() {
    return $this->slevaObecna;
  }

  /** Vrátí člověkem čitelný stav účtu */
  function stavHr() {
    return $this->stav().'&thinsp;Kč';
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
   */
  private function soucinitelAktivit() {
    if(!isset($this->scnA)) {
      // pomocné proměnné
      $sleva=0; // v procentech
      // výpočet pravidel
      if($this->u->maPravo(P_AKTIVITY_ZDARMA)) {
        // sleva 100%
        $sleva += 100;
        $this->slevyA[] = 'sleva 100%';
      } elseif($this->u->maPravo(P_AKTIVITY_SLEVA)) {
        // sleva 40%
        $sleva += 40;
        $this->slevyA[] = 'sleva 40%';
      }
      if($sleva>self::$maxSlevaAktivit) {
        // omezení výše slevy na maximální hodnotu
        $sleva = self::$maxSlevaAktivit;
      }
      $slevaAktivity=(100-$sleva)/100;
      // výsledek
      $this->scnA = $slevaAktivity;
    }
    return $this->scnA;
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
  private function zapoctiAktivity() {
    $scn = $this->soucinitelAktivit();
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
      } else {
        if (!$this->u->maPravo(P_NEMA_SLEVU_AKTIVITY)) {
          $this->sleva -= $r['cena'];
        }
      }

      $poznamka = '';
      if($r['id_stavu_prihlaseni'] == 3) $poznamka = " <i>(nedorazil$a)</i>";
      if($r['id_stavu_prihlaseni'] == 4) $poznamka = " <i>(odhlášen$a pozdě)</i>";
      if($r['id_stavu_prihlaseni'] == Aktivita::NAHRADNIK) continue;
      $this->log($r['nazev'].$poznamka, $r['cena'] < 0 ? 0 : $r['cena'], self::AKTIVITA);
    }
  }

  /**
   * Započítá do mezisoučtů platby na účet
   * @todo odstranit zbytečnosti
   */
  private function zapoctiPlatby() {
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
  private function zapoctiShop() {
    $o = dbQuery('
      SELECT p.id_predmetu, p.nazev, n.cena_nakupni, p.typ, p.ubytovani_den, p.model_rok
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $0 AND n.rok = $1
      ORDER BY n.cena_nakupni -- od nejlevnějších kvůli aplikaci slev na trička
    ', [$this->u->id(), ROK]);

    $soucty = [];
    foreach($o as $r) {
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
   * Započítá ručně zadané slevy z tabulky slev.
   */
  private function zapoctiSlevy() {
    $q = dbQuery('
      SELECT castka, poznamka
      FROM slevy
      WHERE id_uzivatele = $0 AND rok = $1
    ', [$this->u->id(), ROK]);

    foreach($q as $sleva) {
      if(strpos($sleva['poznamka'], '#kompenzace') !== false) {
        // speciální typ slevy: kompenzace
        // započítává se stejně jako sleva za vedené aktivity
        $this->sleva += $sleva['castka'];
      } else {
        // normální sleva
        // započítává se zvlášť
        $this->slevaObecna += $sleva['castka'];
      }
    }
  }

  /**
   * Započítá do mezisoučtů slevy za organizované aktivity
   */
  private function zapoctiVedeniAktivit() {
    if(!$this->u->maPravo(P_ORG_AKCI)) return;
    if($this->u->maPravo(P_NEMA_SLEVU_AKTIVITY)) return;
    foreach(Aktivita::zOrganizatora($this->u) as $a) {
      $this->sleva += self::slevaZaAktivitu($a);
    }
  }

  /**
   * Započítá do mezisoučtů zůstatek z minulých let
   */
  private function zapoctiZustatek() {
    $this->log('Zůstatek z minulých let', $this->zustatek, self::ZUSTATEK);
  }

  /**
   * @return int zůstatek na účtu z minulých GC
   */
  function zustatek() {
    return $this->zustatek;
  }
}
