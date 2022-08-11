<?php

use Gamecon\Shop\Shop;

/**
 * Třída zodpovědná za stanovení / prezentaci cen a slev věcí
 */
class Cenik
{

    private $u;
    private $slevaKostky = 0;
    private $slevaPlacky = 0;
    private $jakychkoliTricekZdarma = 0;
    private $modrychTricekZdarma = 0;
    private $textySlevExtra = [];

    /**
     * Zobrazitelné texty k právům (jen statické). Nestatické texty nutno řešit
     * ručně. V polích se případně udává, které právo daný index „přebíjí“.
     */
    private static $textySlev = [
        P_KOSTKA_ZDARMA           => 'kostka zdarma',
        P_PLACKA_ZDARMA           => 'placka zdarma',
        P_UBYTOVANI_ZDARMA        => 'ubytování zdarma',
        P_UBYTOVANI_STREDA_ZDARMA => ['ubytování ve středu zdarma', P_UBYTOVANI_ZDARMA],
        P_JIDLO_ZDARMA            => 'jídlo zdarma',
        P_JIDLO_SLEVA             => ['jídlo se slevou', P_JIDLO_ZDARMA],
        P_DVE_TRICKA_ZDARMA       => 'dvě jakákoli trička zdarma',
    ];

    /**
     * Konstruktor
     * @param Uzivatel $u pro kterého uživatele se cena počítá
     * @param float $sleva celková sleva získaná za pořádané aktivity
     */
    public function __construct(Uzivatel $u, $sleva) {
        $this->u = $u;

        if ($u->maPravo(P_KOSTKA_ZDARMA)) {
            $this->slevaKostky = 25;
        }
        if ($u->maPravo(P_PLACKA_ZDARMA)) {
            $this->slevaPlacky = 25;
        }
        if ($u->maPravo(P_DVE_TRICKA_ZDARMA)) {
            $this->jakychkoliTricekZdarma = 2;
        }
        if ($sleva >= MODRE_TRICKO_ZDARMA_OD && $u->maPravo(P_TRICKO_ZA_SLEVU_MODRE)) {
            $this->modrychTricekZdarma = 1;
            $this->textySlevExtra[]    = 'modré tričko zdarma';
        }
    }

    /**
     * Sníží $cena o částku $sleva až do nuly. Změnu odečte i z $sleva.
     */
    public static function aplikujSlevu(&$cena, &$sleva): array {
        if ($sleva <= 0) { // nedělat nic
            return ['cena' => $cena, 'sleva' => $sleva];
        }
        if ($sleva <= $cena) {
            $cena  -= $sleva;
            $sleva = 0;
        } else { // $sleva > $cena
            $sleva -= $cena;
            $cena  = 0;
        }
        return ['cena' => $cena, 'sleva' => $sleva];
    }

    /**
     * Vrátí pole s popisy obecných slev uživatele (typicky procentuálních na
     * aktivity)
     * @todo možnost (zvážit) použití objektu Sleva, který by se uměl aplikovat
     */
    public function slevyObecne() {
        return ['nic'];
    }

    /**
     * Vrátí pole s popisy speciálních slev a extra možností uživatele (typicky
     * vypravěčských, věci se slevami nebo zdarma apod.)
     * @todo vypravěčská sleva s číslem apod. (migrovat z financí)
     */
    public function slevySpecialni() {
        $u     = $this->u;
        $slevy = [];

        // standardní slevy vyplývající z práv
        foreach (self::$textySlev as $pravo => $text) {
            // přeskočení práv, která mohou být přebita + normalizace textu
            if (is_array($text)) {
                $zahrnuteVPravu = $text[1];
                if ($u->maPravo($zahrnuteVPravu)) {
                    // pokud má návštěník například právo na "jídlo zdarma", tak je zbytečné právo na "jídlo zdarma ve středu"
                    continue;
                }
                $text = $text[0];
            }
            // přidání infotextu o slevě
            if ($u->maPravo($pravo)) {
                $slevy[] = $text;
            }
        }

        // přidání extra slev vypočítaných za chodu
        $slevy = array_merge($slevy, $this->textySlevExtra);

        return $slevy;
    }

    /**
     * @param array $r
     * @return float cena věci v e-shopu pro daného uživatele
     */
    public function shop(array $r): float {
        if (isset($r['cena_aktualni'])) {
            $cena = $r['cena_aktualni'];
        }
        if (isset($r['cena_nakupni'])) {
            $cena = $r['cena_nakupni'];
        }
        if (!isset($cena)) {
            throw new Exception('Nelze načíst cenu předmětu');
        }
        if (!($typ = $r['typ'])) {
            throw new Exception('Nenačten typ předmetu');
        }

        // aplikace možných slev
        if ($typ == Shop::PREDMET) {
            // hack podle názvu
            if (mb_stripos($r['nazev'], 'Kostka') !== false && $this->slevaKostky) {
                ['cena' => $cena, 'sleva' => $this->slevaKostky] = self::aplikujSlevu($cena, $this->slevaKostky);
            } elseif (mb_stripos($r['nazev'], 'Placka') !== false && $this->slevaPlacky) {
                ['cena' => $cena, 'sleva' => $this->slevaPlacky] = self::aplikujSlevu($cena, $this->slevaPlacky);
            }
        } elseif ($typ == Shop::TRICKO && mb_stripos($r['nazev'], 'modré') !== false && $this->modrychTricekZdarma > 0) {
            $cena = 0;
            $this->modrychTricekZdarma--;
        } elseif ($typ == Shop::TRICKO && $this->jakychkoliTricekZdarma > 0) {
            $cena = 0;
            $this->jakychkoliTricekZdarma--;
        } elseif ($typ == Shop::UBYTOVANI && $this->u->maPravo(P_UBYTOVANI_ZDARMA)) {
            $cena = 0;
        } elseif ($typ == Shop::UBYTOVANI && $r['ubytovani_den'] == 0 && $this->u->maPravo(P_UBYTOVANI_STREDA_ZDARMA)) {
            $cena = 0;
        } elseif ($typ == Shop::UBYTOVANI && $r['ubytovani_den'] == 4 && $this->u->maPravo(P_UBYTOVANI_NEDELE_ZDARMA)) {
            $cena = 0;
        } elseif ($typ == Shop::JIDLO) {
            if ($this->u->maPravo(P_JIDLO_ZDARMA)) $cena = 0;
            elseif ($this->u->maPravo(P_JIDLO_SLEVA) && strpos($r['nazev'], 'Snídaně') === false) $cena -= 20;
        }

        return (float)$cena;
    }

}
