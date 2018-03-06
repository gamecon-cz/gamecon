<?php

/**
 * Třída zodpovědná za stanovení / prezentaci cen a slev věcí
 */

class Cenik {

  protected $u;
  protected $slevaKostky;
  protected $slevaPlacky;
  protected $slevaTricka = 0;
  protected $slevaTrickaTyp = 0;
  protected $slevaTrickaPuvodni = 0;

  const
    CERVENE   = 0b0001, // typy triček, na které je aplikovatelná sleva
    MODRE     = 0b0010,
    NORMALNI  = 0b0100;

  /**
   * Zobrazitelné texty k právům (jen statické). Nestatické texty nutno řešit
   * ručně. V polích se případně udává, které právo daný index „přebíjí“.
   */
  protected static $textSlev = [
    P_KOSTKA_ZDARMA => 'kostka zdarma',
    P_PLACKA_ZDARMA => 'placka zdarma',
    P_UBYTOVANI_ZDARMA  => 'ubytování zdarma',
    P_UBYTOVANI_STREDA_ZDARMA => ['ubytování ve středu zdarma', P_UBYTOVANI_ZDARMA],
    P_JIDLO_ZDARMA  => 'jídlo zdarma',
    P_JIDLO_SLEVA   => ['jídlo se slevou', P_JIDLO_ZDARMA],
    P_JIDLO_SNIDANE => 'možnost objednat si snídani',
  ];

  /**
   * Konstruktor
   * @param Uzivatel $u pro kterého uživatele se cena počítá
   * @param int $sleva celková sleva získaná za pořádané aktivity
   */
  function __construct(Uzivatel $u, $sleva) {
    $this->u = $u;
    $this->slevaKostky = $u->maPravo(P_KOSTKA_ZDARMA) ? 15 : 0;
    $this->slevaPlacky = $u->maPravo(P_PLACKA_ZDARMA) ? 15 : 0;

    if($u->maPravo(P_TRIKO_ZDARMA))
      $this->slevaTricka = 150;
    elseif($u->maPravo(P_TRIKO_ZA_SLEVU_MODRE) && $sleva >= 660)
      $this->slevaTricka = 150;
    elseif($u->maPravo(P_TRIKO_ZA_SLEVU) && $sleva >= 660)
      $this->slevaTricka = 200;
    elseif($u->maPravo(P_TRIKO_SLEVA_MODRE) || $u->maPravo(P_TRIKO_SLEVA))
      $this->slevaTricka = 50;

    $this->slevaTrickaPuvodni = $this->slevaTricka;

    if($u->maPravo(P_TRIKO_ZDARMA))
      $this->slevaTrickaTyp |= self::CERVENE;
    if($u->maPravo(P_TRIKO_SLEVA_MODRE) || $u->maPravo(P_TRIKO_ZA_SLEVU_MODRE))
      $this->slevaTrickaTyp |= self::MODRE;
    if($u->maPravo(P_TRIKO_SLEVA) || $u->maPravo(P_TRIKO_ZA_SLEVU))
      $this->slevaTrickaTyp |= self::NORMALNI;
  }

  /**
   * Sníží $cena o částku $sleva až do nuly. Změnu odečte i z $sleva.
   */
  static function aplikujSlevu(&$cena, &$sleva) {
    if($sleva <= 0) return; // nedělat nic
    if($sleva <= $cena) {
      $cena -= $sleva;
      $sleva = 0;
    } else { // $sleva > $cena
      $sleva -= $cena;
      $cena = 0;
    }
  }

  /**
   * Vrátí pole s popisy obecných slev uživatele (typicky procentuálních na
   * aktivity)
   * @todo možnost (zvážit) použití objektu Sleva, který by se uměl aplikovat
   */
  function slevyObecne() {
    return ['nic'];
  }

  /**
   * Vrátí pole s popisy speciálních slev a extra možností uživatele (typicky
   * vypravěčských, věci se slevami nebo zdarma apod.)
   * @todo vypravěčská sleva s číslem apod. (migrovat z financí)
   */
  function slevySpecialni() {
    $u = $this->u;
    $slevy = [];

    // standardní slevy vyplývající z práv
    foreach(self::$textSlev as $pravo => $text) {
      // přeskočení práv, která mohou být přebita + normalizace textu
      if(is_array($text)) {
        foreach($text as $i => $pravoPrebiji) {
          if($i && $u->maPravo($pravoPrebiji)) continue 2;
        }
        $text = $text[0];
      }
      // přidání infotextu o slevě
      if($u->maPravo($pravo)) $slevy[] = $text;
    }

    // spec. sleva na trička řešící barvy
    $trickaTypy = [];
    if($this->slevaTrickaTyp & self::CERVENE)     $trickaTypy[] = 'červené organizátorské';
    if($this->slevaTrickaTyp & self::MODRE)       $trickaTypy[] = 'modré vypravěčské';
    if($this->slevaTrickaTyp & self::NORMALNI)    $trickaTypy[] = 'běžné';
    if($this->slevaTrickaTyp === self::NORMALNI)  $trickaTypy = ['']; // obejití, aby se "běžné" psalo jen, pokud má i jiné možnosti trička
    if($trickaTypy) {
      $slevy[] = implode(' nebo ', $trickaTypy) . ' tričko ' . ($this->slevaTrickaPuvodni == 200 ? 'zdarma' : 'se slevou');
    }

    return $slevy;
  }

  /**
   * Vrátí cenu věci v e-shopu pro daného uživatele
   */
  function shop($r) {
    if(isset($r['cena_aktualni'])) $cena = $r['cena_aktualni'];
    if(isset($r['cena_nakupni'])) $cena = $r['cena_nakupni'];
    if(!isset($cena)) throw new Exception('Nelze načíst cenu předmětu');
    if(!($typ = $r['typ'])) throw new Exception('Nenačten typ předmetu');

    // aplikace možných slev
    if($typ == Shop::PREDMET) {
      // hack podle názvu
      if($r['nazev'] == 'Kostka' && $this->slevaKostky) {
        self::aplikujSlevu($cena, $this->slevaKostky);
      } elseif($r['nazev'] == 'Placka' && $this->slevaPlacky) {
        self::aplikujSlevu($cena, $this->slevaPlacky);
      }
    } elseif($typ == Shop::TRICKO && mb_stripos($r['nazev'], 'červené') !== false) {
      if($this->slevaTrickaTyp & self::CERVENE)
        self::aplikujSlevu($cena, $this->slevaTricka);
    } elseif($typ == Shop::TRICKO && mb_stripos($r['nazev'], 'modré') !== false) {
      if($this->slevaTrickaTyp & self::MODRE)
        self::aplikujSlevu($cena, $this->slevaTricka);
    } elseif($typ == Shop::TRICKO) {
      if($this->slevaTrickaTyp & self::NORMALNI)
        self::aplikujSlevu($cena, $this->slevaTricka);
    } elseif($typ == Shop::UBYTOVANI && $this->u->maPravo(P_UBYTOVANI_ZDARMA)) {
      $cena = 0;
    } elseif($typ == Shop::UBYTOVANI && $r['ubytovani_den'] == 0 && $this->u->maPravo(P_UBYTOVANI_STREDA_ZDARMA)) {
      $cena = 0;
    } elseif($typ == Shop::UBYTOVANI && $r['ubytovani_den'] == 4 && $this->u->maPravo(P_UBYTOVANI_NEDELE_ZDARMA)) {
      $cena = 0;
    } elseif($typ == Shop::JIDLO) {
      if($this->u->maPravo(P_JIDLO_ZDARMA)) $cena = 0;
      elseif($this->u->maPravo(P_JIDLO_SLEVA) && strpos($r['nazev'], 'Snídaně') === false) $cena -= 20;
    }

    return (float)$cena;
  }

}
