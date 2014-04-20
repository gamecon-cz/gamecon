<?php

/**
 * Třída popisující systém financí daného uživatele. 
 */

class Finance
{
  private
    $u,       // uživatel, jehož finance se počítají
    $stav=0,  // celkový výsledný stav uživatele na účtu
    $deltaPozde=0,     // o kolik se zvýší platba při zaplacení pozdě
    $prehled=array(),  // tabulka s detaily o platbách
    $slevyA=array(),   // pole s textovými popisy slev uživatele na aktivity
    $slevyO=array(),   // pole s textovými popisy obecných slev
    $scnA=1.0,         // součinitel ceny aktivit
    $cenaAktivity=0,   // cena aktivit
    $cenaUbytovani=0,  // cena objednaného ubytování
    $cenaPremety=0,    // cena předmětů a dalších objednávek v shopu
    $platby=0;         // platby připsané na účet

  private static 
    $maxSlevaAktivit=100, // v procentech
    $slevaZaAktivitu = array( // ve formátu max. délka => sleva
      1  =>  50,
      2  =>  75,
      5  => 150,
      7  => 225,
      9  => 300,
      11 => 375,
      13 => 450,
    );
  
  const
    NAZEV=0,     // indexy do tabulky $prehled
    CENA=1,
    TYP=2,
    PODTYP=3,
    AKTIVITA=-1, // idčka typů, podle kterých se řadí výstupní tabulka $prehled
    //mezera na typy předmětů (1-4? viz db)
    ORGSLEVA=10,
    CELKOVA=11,
    PLATBY_NADPIS=12,
    ZUSTATEK=13,
    PLATBA=14,
    VYSLEDNY=15;
  
  /**
   * Konstruktor
   * @param Uzivatel $u uživatel, pro kterého se finance sestavují   
   * @param array $nastaveni volitelná nastavení:
   *  detail (bool) - detailní informace o aktivitách?
   */           
  function __construct(Uzivatel $u,$nastaveni=null)
  {
    $this->u=$u;
    //$this->nastaveni=$nastaveni; //není v proměnných, wut?
    // načtení info  
    if($u->gcPrihlasen())
    {
      // pomocné proměnné
      $this->gac=0;
      $uid=$this->u->id();
      $rok=ROK;
      // výpočet slevy na aktivity
      $this->scnA=$this->spoctiSoucinitelAktivit();
      // výpočet slev na předměty - použijeme je jako "čerpání" slevy
      if($this->u->maPravo(P_TRIKO_ZDARMA)) {
        $slevaTricko = 200;
        $this->slevyO[] = 'jedno červené tričko zdarma';
      } else if($this->u->maPravo(P_TRIKO_ZAPUL)) {
        $slevaTricko = 100;
        $this->slevyO[] = 'jedno modré vypravěčské tričko za polovic';
      } else {
        $slevaTricko = 0;
      }
      if($slevaKostka=$this->u->maPravo(P_KOSTKA_ZDARMA)?15:0)
        $this->slevyO[]='kostka zdarma';
      if($slevaPlacka=$this->u->maPravo(P_PLACKA_ZDARMA)?15:0)
        $this->slevyO[]='placka zdarma';
      if($this->u->maPravo(P_JIDLO_ZDARMA))
        $this->slevyO[]='jídlo zdarma';
      if($this->u->maPravo(P_UBYTOVANI_ZDARMA))
        $this->slevyO[]='ubytování zdarma';
      // vypravěčská sleva dle aktivit
      $slevaVse = $this->slevaVypravec();
      // provedení dotazu
      $o=dbQuery("
        -- aktivity
          SELECT
            a.nazev_akce as nazev,
            a.cena*(st.platba_procent/100)*IF(a.bez_slevy OR a.typ=10, 1.0, $this->scnA)*IF(a.typ=10,-1.0,1.0) as cena,
            ".self::AKTIVITA." as typ,
            0 as podtyp
          FROM (
            SELECT * FROM akce_prihlaseni WHERE id_uzivatele=$uid
            UNION
            SELECT * FROM akce_prihlaseni_spec WHERE id_uzivatele=$uid) p
          JOIN akce_seznam a USING(id_akce)
          JOIN akce_prihlaseni_stavy st USING(id_stavu_prihlaseni)
          WHERE rok=$rok
        UNION ALL -- kvůli zachování duplicit ALL (také rychlejší)
        -- předměty a zázemí
          SELECT
            p.nazev,
              IF(".(int)$this->u->maPravo(P_JIDLO_ZDARMA)." AND p.typ=4,
                0,
              IF(".(int)$this->u->maPravo(P_UBYTOVANI_ZDARMA)." AND p.typ=2,
                0,
           -- ELSE
              n.cena_nakupni)) 
            as cena,
            p.typ,
            if(p.typ=2,p.ubytovani_den,p.model_rok) as podtyp
          FROM shop_nakupy n
          JOIN shop_predmety p USING(id_predmetu)
          WHERE n.id_uzivatele=$uid AND n.rok=$rok
        -- platba s nápisem platba {datum}
        UNION ALL
          SELECT
            IF(provedl=1,
              CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' Platba na účet'),
              CONCAT(DATE_FORMAT(provedeno,'%e.%c.'),' ',IFNULL(poznamka,'(bez poznámky)'))
              ) as nazev,
            castka as cena,
            ".self::PLATBA." as typ,
            0 as podtyp
          FROM platby
          WHERE id_uzivatele=$uid AND rok=$rok
        -- todo zůstatek z minulého roku
        UNION ALL
          SELECT
            'Zůstatek z minulých let' as nazev,
            zustatek as cena,
            ".self::ZUSTATEK." as typ,
            0 as podtyp
          FROM uzivatele_hodnoty
          WHERE id_uzivatele=$uid
      ");
      // zpracování výsledků a odečtení jednorázových slev
      $suma=0;
      $zaplaceno=0;
      $plateb=0;
      while($r=mysql_fetch_assoc($o))
      {
        // přepočet záporných cen (tech. aktivity) na reál slevu
        if($r['cena'] < 0 && $r['typ']==self::AKTIVITA) {
          $slevaVse -= $r['cena'];
          $r['cena'] = 0;
        }
        // slevy na první kus od předmětu
        if($r['typ']==3)
          self::zapoctiSlevu($r['cena'],$slevaTricko);
        else if($r['typ']==1 && $r['nazev']=='Placka')
          self::zapoctiSlevu($r['cena'],$slevaPlacka);
        else if($r['typ']==1 && $r['nazev']=='Kostka')
          self::zapoctiSlevu($r['cena'],$slevaKostka);
        // rozlišení a zaúčtování platba x cena
        if($r['typ']==self::PLATBA || $r['typ']==self::ZUSTATEK)
          $zaplaceno+=$r['cena'];
        else
          $suma+=$r['cena'];
        // započtení do mezisoučtů
        switch($r['typ']){
          case self::PLATBA:        $this->platby+=$r['cena']; break;
          case self::AKTIVITA:      $this->cenaAktivity+=$r['cena']; break;
          case 2:                   $this->cenaUbytovani+=$r['cena']; break;
          case 1: case 3: case 4:   $this->cenaPremety+=$r['cena']; break;
          default: break;
        }
        $nazev= $r['typ']==1 ? $r['nazev'].' '.$r['podtyp'] : $r['nazev'];
          $this->prehled[]=array( self::NAZEV=>$nazev, self::CENA=>$r['cena'], self::TYP=>$r['typ'], self::PODTYP=>$r['podtyp'] );
        if( $r['typ']==-1 && ($this->u->maPravo(P_SLEVA_VCAS) || SLEVA_AKTIVNI) && $this->scnA>0 )
          $this->deltaPozde+=($r['cena']/$this->scnA)*0.2;
        if(!$plateb && ($r['typ']==self::PLATBA || $r['typ']==self::ZUSTATEK))
          $this->prehled[]=array( self::NAZEV=>'<b>Připsané platby</b>', self::CENA=>'', self::TYP=>self::PLATBY_NADPIS ) xor
          $plateb++;
      }
      // vypravěčská sleva na vše
      if($slevaVse)
      {
        $puvSleva=$slevaVse;
        self::zapoctiSlevu($suma,$slevaVse);
        $this->prehled[]=array(
          self::NAZEV => 'Sleva za organizované aktivity',
          self::CENA => $puvSleva.' (využito '.($puvSleva-$slevaVse).')',
          self::TYP => self::ORGSLEVA);
        $this->slevyO[] = 'sleva '.$puvSleva.'&thinsp;Kč pro vypravěče za odvedené aktivity na všechno';
      }
      // celkové sumy do přehledu a výsledek
      $this->prehled[]=array( self::NAZEV => 'Celková cena', self::CENA => $suma, self::TYP => self::CELKOVA);
      $this->prehled[]=array( self::NAZEV => '<b>Stav financí</b>', self::CENA => '<b>'.(-$suma+$zaplaceno).'</b>', self::TYP => self::VYSLEDNY);
      
      $this->stav=-$suma+$zaplaceno;
    }
  }
  
  /** Vrátí zůstatek bonusu */
  public function bonus()
  { throw new DeprecatedException(); return $this->bon; }
  
  public function cenaAktivity()
  { return $this->cenaAktivity; }
  
  public function cenaUbytovani()
  { return $this->cenaUbytovani; }
  
  public function cenaPredmety()
  { return $this->cenaPremety; }
  
  /** Vrátí lidsky čitelný tvar ve stylu gcoruny/bonus */
  public function hr()
  {
    if($this->u->gcPrihlasen())
      return $this->stav.'&thinsp;Kč';
    else
      return '?';
  }
  
  /** Vrátí zůstatek gamecorun */
  public function gamecoruny()
  { return $this->stav(); }
  
  /** Vrátí sumu plateb (připsaných peněz) */
  function platby()
  { return $this->platby; }
  
  /** Vrátí html formátovaný přehled financí */
  public function prehledHtml()
  {
    usort($this->prehled,'Finance::cmp');
    $out='';
    $out.='<table>';
    foreach($this->prehled as $r)
      $out.= '<tr><td>' . $r[self::NAZEV] . '</td><td>' . $r[self::CENA] . '</td></tr>';
    $out.='</table>';
    return $out;
  }
  
  /** Vrátí sumu peněz, kterou uživatel poslal v tomto roce na účet (kč) */
  public function poslaneGac()
  { return $this->platby; }
  
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
    return $this->scnA; //todo když není přihlášen na GameCon, možná raději řešit zobrazení ceny defaultně (protože neznáme jeho studentství etc.). Viz také třída Aktivita
  }
  
  /** Vrátí slevu za odvedené aktivity (celkovou částku) */
  protected function slevaVypravec() {
    $slevaCelkem = 0;
    if($this->u->maPravo(P_ORG_AKCI)) {
      foreach(Aktivita::zOrganizatora($this->u) as $a) {
        $delka = $a->delka();
        $sleva = 0;
        foreach(self::$slevaZaAktivitu as $tabDelka => $tabSleva) {
          if($delka <= $tabDelka) {
            $sleva = $tabSleva;
            break;
          }
        }
        $slevaCelkem += $sleva;
      }
    }
    return $slevaCelkem;
  }

  /** Vrátí pole slev (objektů Sleva) vztahujících se jen na aktivity */
  function slevyAktivity()
  {
    $slevy=array();
    foreach($this->slevyA as $sleva)
      $slevy[]=Sleva::procentualni(null,null,$sleva); //fixme, nemusí být procentuální vždy
    return $slevy;
  }
  
  /** Vrátí popis aktivních slev čitelný pro uživatele */
  function slevyHtml()
  {
    $out='';
    if($this->slevyA)
    { 
      $out.='<b>Slevy na aktivity:</b> ';
      $out.=implode(', ',$this->slevyA);
    }
    if($this->slevyO)
    { 
      $out.='<br><b>Další bonusy:</b> ';
      $out.=implode(', ',$this->slevyO);
    }
    return $out;
  }
  
  /** Vrátí pole slev (objektů Sleva) vztahujících se jen na aktivity */
  function slevyVse()
  {
    $slevy=array();
    foreach($this->slevyO as $sleva)
      $slevy[]=Sleva::procentualni(null,null,$sleva); //fixme, nemusí být procentuální vždy, často absolutní
    return $slevy;
  }
  
  ////////////////////
  // Protected věci //
  ////////////////////
  
  /** Porovnávání k řazení php 4 style :/ */
  private function cmp($a,$b)
  { // řazení podle typu, jména, ceny
    if( ($a[self::TYP]==2 || $a[self::TYP]==3) && ($b[self::TYP]==2 || $b[self::TYP]==3) )
    { //prohození prio triček a ubytování
      $t=$b[self::TYP];
      $b[self::TYP]=$a[self::TYP];
      $a[self::TYP]=$t;
    }
    $m=$a[self::TYP]-$b[self::TYP];
    if($m) return $m;
    $n= isset($a[self::PODTYP],$b[self::PODTYP]) ? $a[self::PODTYP]-$b[self::PODTYP] : 0;
    if($n) return $n;
    $o=strcmp($a[self::NAZEV],$b[self::NAZEV]);
    if($o) return $o;
    return $a[self::CENA]-$b[self::CENA];
  }
  
  /** Vrací parametr detailního nastavení */
  private function nast($klic)
  {
    if(isset($this->nastaveni[$klic]))
      return $this->nastaveni[$klic];
    else
      return null;
  }
  
  /** Vrací cenu jako gcor/bonus */
  private function cena(Aktivita $a)
  {
    throw new DeprecatedException();
    $sleva=$this->zazemi['student']?SLEVA_STUDENT:SLEVA_PRACUJICI;
    if($this->zazemi['org'] && ($a->typ()==1 || $a->typ()==7))
      return '0/0 (org zdarma)';
    return $a->cenaZaklad()*(1.0-$sleva)*$a->nasobekPlatby().'/'.
      $a->cenaZaklad()*$sleva*$a->nasobekPlatby();
  }
  
  /**
   * Spočítá součinitel ceny aktivit, tedy slevy uživatele vztahující se k 
   * aktivitám. Vrátí hodnotu.
   * @todo přesunout výpočet konstant pro židle předloni, předpředloni atd…   
   */        
  protected function spoctiSoucinitelAktivit()
  {
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
    return $slevaAktivity;
  }
  
  /**
   * Počítá položku $sleva jako slevu v podobě počtu peněz a $cena jako cenu,
   * na kterou lze slevu aplikovat. Pokud je sleva vyšší jak cena, cena se dá na
   * 0 a zbytek slevy zbyde pro další použití, pokud naopak, odečte a znuluje se
   * celá sleva a cena se sníží o danou částku
   */      
  private static function zapoctiSlevu(&$cena,&$sleva)
  {
    if($sleva<=0)
      return; //nedělat nic
    if($sleva<=$cena)
    {
      $cena-=$sleva;
      $sleva=0;
    }
    else //$sleva>$cena
    {
      $sleva-=$cena;
      $cena=0;
    }    
  }
            
}
