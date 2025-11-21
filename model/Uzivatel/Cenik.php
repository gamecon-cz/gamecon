<?php

namespace Gamecon\Uzivatel;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\SqlStruktura\NakupySqlStruktura as NakupySql;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as PredmetySql;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Dto\PriceAfterDiscountDto;
use Uzivatel;

/**
 * Třída zodpovědná za stanovení / prezentaci cen a slev věcí
 */
class Cenik
{
    private int   $zbyvajicichMoznychKostekZdarma = 1;
    private int   $zbyvajicichMoznychPlacekZdarma = 1;
    private ?int  $jakychkoliTricekZdarma         = null;
    private ?int  $modrychTricekZdarma            = null;
    private array $textySlevExtra                 = [];

    /**
     * Sníží $cena o částku $sleva až do nuly. Změnu odečte i z $sleva.
     * @return array{cena: float, sleva: float} aktualizované hodnoty
     */
    public static function aplikujSlevu(
        &$cena,
        &$sleva,
    ): array {
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

        return ['cena' => (float)$cena, 'sleva' => (float)$sleva];
    }

    /**
     * Konstruktor
     * @param Uzivatel $u pro kterého uživatele se cena počítá
     * @param int|float|callable<int|float> $sleva celková sleva získaná za pořádané aktivity
     */
    public function __construct(
        private readonly Uzivatel           $u,
        private readonly Finance            $finance,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function getTextySlev(): array
    {
        /**
         * Zobrazitelné texty k právům (jen statické). Nestatické texty nutno řešit
         * ručně. V polích se případně udává, které právo daný index „přebíjí“.
         */
        $texty                             = [
            Pravo::KOSTKA_ZDARMA                     => 'kostka zdarma',
            Pravo::PLACKA_ZDARMA                     => 'placka zdarma',
            Pravo::UBYTOVANI_ZDARMA                  => 'ubytování zdarma',
            Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA    => ['ubytování ve středu zdarma', Pravo::UBYTOVANI_ZDARMA],
            Pravo::JIDLO_ZDARMA                      => 'jídlo zdarma',
            Pravo::JIDLO_SE_SLEVOU                   => ['jídlo se slevou', Pravo::JIDLO_ZDARMA],
            Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA        => 'dvě jakákoli trička zdarma',
            Pravo::JAKEKOLIV_TRICKO_ZDARMA           => ['jedno jakékoliv tričko zdarma', Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA],
            Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA      => 'modré tričko se slevou',
            Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC => 'můžeš si objednat ubytování i pro jedinou noc',
            Pravo::MODRE_TRICKO_ZDARMA               => 'modré tričko zdarma za dosažení bonusu %d',
        ];
        $bonus                             = $this->systemoveNastaveni->modreTrickoZdarmaOd();
        $texty[Pravo::MODRE_TRICKO_ZDARMA] = sprintf(
            $texty[Pravo::MODRE_TRICKO_ZDARMA],
            $bonus,
        );

        return $texty;
    }

    public function cenaKostky(array $r): int
    {
        $cena          = (int)$r[PredmetySql::CENA_AKTUALNI];
        $slevaNaKostku = $this->slevaNaKostku($r, $cena, false);

        return $cena - $slevaNaKostku;
    }

    private function slevaNaKostku(
        array $r,
              $cena,
        bool  $omezPocet = true,
    ): int {
        if ($omezPocet && $this->zbyvajicichMoznychKostekZdarma <= 0) {
            return 0;
        }
        if (!$this->u->maPravoNaKostkuZdarma()) {
            return 0;
        }
        if (!$this->maObjednanouLetosniKostku($r)) {
            return 0;
        }
        if ($omezPocet) {
            $this->zbyvajicichMoznychKostekZdarma--;
        }

        return (int)$cena;
    }

    private function maObjednanouLetosniKostku(array $r): bool
    {
        if (!Predmet::jeToKostka($r[PredmetySql::KOD_PREDMETU])) {
            return false;
        }
        $letosniKostka = Predmet::letosniKostka($this->systemoveNastaveni->rocnik());
        if (!$letosniKostka) {
            return false;
        }

        return (int)$letosniKostka->id() === (int)$r[PredmetySql::ID_PREDMETU];
    }

    public function cenaPlacky(array $r): int
    {
        $cena          = (int)$r[PredmetySql::CENA_AKTUALNI];
        $slevaNaPlacku = $this->slevaNaPlacku($r, $cena, false);

        return $cena - $slevaNaPlacku;
    }

    private function slevaNaPlacku(
        array $r,
              $cena,
        bool  $omezPocet = true,
    ): int {
        if ($omezPocet && $this->zbyvajicichMoznychPlacekZdarma <= 0) {
            return 0;
        }
        if (!$this->u->maPravoNaPlackuZdarma()) {
            return 0;
        }
        if (!$this->maObjednanouLetosniPlacku($r)) {
            return 0;
        }
        if ($omezPocet) {
            $this->zbyvajicichMoznychPlacekZdarma--;
        }

        return (int)$cena;
    }

    private function maObjednanouLetosniPlacku(array $r): bool
    {
        if (!Predmet::jeToPlacka($r[PredmetySql::KOD_PREDMETU])) {
            return false;
        }
        $letosniPlacka = Predmet::letosniPlacka($this->systemoveNastaveni->rocnik());
        if (!$letosniPlacka) {
            return false;
        }

        return (int)$letosniPlacka->id() === (int)$r[PredmetySql::ID_PREDMETU];
    }

    /**
     * Vrátí pole s popisy obecných slev uživatele (typicky procentuálních na
     * aktivity)
     * @todo možnost (zvážit) použití objektu Sleva, který by se uměl aplikovat
     */
    public function slevyObecne()
    {
        return ['nic'];
    }

    /**
     * Vrátí pole s popisy speciálních slev a extra možností uživatele (typicky
     * vypravěčských, věci se slevami nebo zdarma apod.)
     * @todo vypravěčská sleva s číslem apod. (migrovat z financí)
     * @return array<string>
     */
    public function slevySpecialni(): array
    {
        $u     = $this->u;
        $slevy = [];
        $texty = $this->getTextySlev();

        // standardní slevy vyplývající z práv
        foreach ($texty as $pravo => $text) {
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

    public function puvodniCena(array $r): float
    {
        if (isset($r[NakupySql::CENA_NAKUPNI])) {
            return (float)$r[NakupySql::CENA_NAKUPNI];
        }
        if (isset($r[PredmetySql::CENA_AKTUALNI])) {
            trigger_error(
                sprintf(
                    "Chybí nákupní cena v záznamu nákupu předmětu s ID '%s'",
                    $r[PredmetySql::ID_PREDMETU] ?? 'neznámé',
                ),
                E_USER_WARNING,
            );

            return (float)$r[PredmetySql::CENA_AKTUALNI];
        }
        throw new \RuntimeException('Nelze načíst cenu předmětu s ID ' . ($r[PredmetySql::ID_PREDMETU] ?? 'neznámé'));
    }

    /**
     * @param array $r
     * @return PriceAfterDiscountDto cena věci v e-shopu pro daného uživatele
     */
    public function cena(array $r): PriceAfterDiscountDto
    {
        $cena = $this->puvodniCena($r);
        if (!($typ = $r[PredmetySql::TYP])) {
            throw new \RuntimeException('Nenačten typ předmetu');
        }

        // aplikace možných slev
        if ($typ == TypPredmetu::PREDMET) {
            // hack podle názvu
            if (Predmet::jeToKostka($r[PredmetySql::KOD_PREDMETU])) {
                $slevaKostky = $this->slevaNaKostku($r, $cena);

                $cenaSeSlevou = self::aplikujSlevu($cena, $slevaKostky);

                return new PriceAfterDiscountDto(
                    finalPrice: $cenaSeSlevou['cena'],
                    discount: $cenaSeSlevou['sleva'],
                );
            } elseif (Predmet::jeToPlacka($r[PredmetySql::KOD_PREDMETU])) {
                $slevaPlacky = $this->slevaNaPlacku($r, $cena);

                $cenaSeSlevou = self::aplikujSlevu($cena, $slevaPlacky);

                return new PriceAfterDiscountDto(
                    finalPrice: $cenaSeSlevou['cena'],
                    discount: $cenaSeSlevou['sleva'],
                );
            }
        } elseif ($typ == TypPredmetu::TRICKO && Predmet::jeToModre($r[PredmetySql::NAZEV]) && $this->modrychTricekZdarma() > 0) {
            $this->modrychTricekZdarma($this->modrychTricekZdarma() - 1);

            return new PriceAfterDiscountDto(
                finalPrice: 0.0,
                discount: $cena,
            );
        } elseif ($typ == TypPredmetu::TRICKO && $this->jakychkolivTricekZdarma() > 0) {
            $this->jakychkolivTricekZdarma($this->jakychkolivTricekZdarma() - 1);

            return new PriceAfterDiscountDto(
                finalPrice: 0.0,
                discount: $cena,
            );
        } elseif ($typ == TypPredmetu::UBYTOVANI) {
            if ($this->u->maPravoNaUbytovaniZdarma()
                || ($r[PredmetySql::UBYTOVANI_DEN] == DateTimeGamecon::PORADI_HERNIHO_DNE_STREDA && $this->u->maPravo(Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA))
                || ($r[PredmetySql::UBYTOVANI_DEN] == DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK && $this->u->maPravo(Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA))
                || ($r[PredmetySql::UBYTOVANI_DEN] == DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK && $this->u->maPravo(Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA))
                || ($r[PredmetySql::UBYTOVANI_DEN] == DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA && $this->u->maPravo(Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA))
                || ($r[PredmetySql::UBYTOVANI_DEN] == DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE && $this->u->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA))
            ) {
                return new PriceAfterDiscountDto(
                    finalPrice: 0.0,
                    discount: $cena,
                );
            }
        } elseif ($typ == TypPredmetu::JIDLO) {
            if ($this->u->maPravoNaJidloZdarma()) {
                return new PriceAfterDiscountDto(
                    finalPrice: 0.0,
                    discount: $cena,
                );
            } elseif ($this->u->maPravo(Pravo::JIDLO_SE_SLEVOU)) {
                $sleva = $this->systemoveNastaveni->slevaOrguNaJidloCastka();

                return new PriceAfterDiscountDto(
                    finalPrice: $cena - $sleva,
                    discount: $sleva,
                );
            }
        }

        return new PriceAfterDiscountDto(
            finalPrice: $cena,
            discount: 0.0,
        );
    }

    private function jakychkolivTricekZdarma(?int $jakychkoliTricekZdarma = null): int
    {
        if ($jakychkoliTricekZdarma !== null) {
            $this->jakychkoliTricekZdarma = $jakychkoliTricekZdarma;
        } elseif ($this->jakychkoliTricekZdarma === null) {
            $this->jakychkoliTricekZdarma = 0;
            if ($this->u->maPravo(Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA)) {
                $this->jakychkoliTricekZdarma = 2;
            } elseif ($this->u->maPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA)) {
                $this->jakychkoliTricekZdarma = 1;
            }
        }

        return $this->jakychkoliTricekZdarma;
    }

    private function modrychTricekZdarma(?int $modrychTricekZdarma = null): int
    {
        if ($modrychTricekZdarma !== null) {
            $this->modrychTricekZdarma = $modrychTricekZdarma;
        } elseif ($this->modrychTricekZdarma === null) {
            $this->modrychTricekZdarma = $this->finance->maximalniPocetModrychTricekZdarma();
        }

        return $this->modrychTricekZdarma;
    }
}
