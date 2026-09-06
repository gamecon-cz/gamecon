<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\SqlStruktura\NakupySqlStruktura as NakupySql;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as PredmetySql;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Dto\PriceAfterDiscountDto;

/**
 * Třída zodpovědná za stanovení / prezentaci cen a slev věcí
 */
class Cenik
{
    private int $zbyvajicichMoznychKostekZdarma = 1;
    private int $zbyvajicichMoznychPlacekZdarma = 1;
    private ?int $jakychkoliTricekZdarma = null;
    private ?int $bonusovychTricekZdarma = null;
    private array $textySlevExtra = [];
    /**
     * @var \App\Discount\DiscountRule[]|null
     */
    private ?array $pravidla = null;
    /**
     * @var int[]|null
     */
    private ?array $prava = null;
    /**
     * @var array<string, float>|null
     */
    private ?array $nastaveniSlev = null;

    /**
     * Sníží $cena o částku $sleva až do nuly. Změnu odečte i z $sleva.
     *
     * @return array{cena: float, sleva: float} aktualizované hodnoty
     */
    public static function aplikujSlevu(
        &$cena,
        &$sleva,
    ): array {
        if ($sleva <= 0) { // nedělat nic
            return [
                'cena'  => $cena,
                'sleva' => $sleva,
            ];
        }
        if ($sleva <= $cena) {
            $cena -= $sleva;
            $sleva = 0;
        } else { // $sleva > $cena
            $sleva -= $cena;
            $cena = 0;
        }

        return [
            'cena'  => (float) $cena,
            'sleva' => (float) $sleva,
        ];
    }

    public static function maUbytovaniZdarmaProDen(
        \Uzivatel $ucastnik,
        int $denUbytovani,
    ): bool {
        if ($ucastnik->maPravoNaUbytovaniZdarma()) {
            return true;
        }

        return match ($denUbytovani) {
            DateTimeGamecon::PORADI_HERNIHO_DNE_STREDA  => $ucastnik->maPravo(Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA),
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK => $ucastnik->maPravo(Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA),
            DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK   => $ucastnik->maPravo(Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA),
            DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA  => $ucastnik->maPravo(Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA),
            DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE  => $ucastnik->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA),
            default                                     => false,
        };
    }

    /**
     * Konstruktor
     *
     * @param \Uzivatel $u pro kterého uživatele se cena počítá
     */
    public function __construct(
        private readonly \Uzivatel $u,
        private readonly Finance $finance,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function getTextySlev(): array
    {
        /**
         * Zobrazitelné texty k právům (jen statické). Nestatické texty nutno řešit
         * ručně. V polích se případně udává, které právo daný index „přebíjí“.
         */
        $texty = [
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
            Pravo::MODRE_TRICKO_ZDARMA               => 'tričko zdarma za dosažení bonusu %d',
        ];
        $bonus = $this->systemoveNastaveni->modreTrickoZdarmaOd();
        $texty[Pravo::MODRE_TRICKO_ZDARMA] = sprintf(
            $texty[Pravo::MODRE_TRICKO_ZDARMA],
            $bonus,
        );

        return $texty;
    }

    public function cenaKostky(array $r): int
    {
        $cena = (int) $r[PredmetySql::CENA_AKTUALNI];
        $slevaNaKostku = $this->slevaNaKostku($r, $cena, false);

        return $cena - $slevaNaKostku;
    }

    private function slevaNaKostku(
        array $r,
        $cena,
        bool $omezPocet = true,
    ): int {
        if ($omezPocet && $this->zbyvajicichMoznychKostekZdarma <= 0) {
            return 0;
        }
        if (! $this->u->maPravoNaKostkuZdarma()) {
            return 0;
        }
        if (! $this->maObjednanouKostku($r)) {
            return 0;
        }
        if ($omezPocet) {
            --$this->zbyvajicichMoznychKostekZdarma;
        }

        return (int) $cena;
    }

    private function maObjednanouKostku(array $r): bool
    {
        return Predmet::jeToKostka($r[PredmetySql::KOD_PREDMETU]);
    }

    public function cenaPlacky(array $r): int
    {
        $cena = (int) $r[PredmetySql::CENA_AKTUALNI];
        $slevaNaPlacku = $this->slevaNaPlacku($r, $cena, false);

        return $cena - $slevaNaPlacku;
    }

    private function slevaNaPlacku(
        array $r,
        $cena,
        bool $omezPocet = true,
    ): int {
        if ($omezPocet && $this->zbyvajicichMoznychPlacekZdarma <= 0) {
            return 0;
        }
        if (! $this->u->maPravoNaPlackuZdarma()) {
            return 0;
        }
        if (! $this->maObjednanouPlacku($r)) {
            return 0;
        }
        if ($omezPocet) {
            --$this->zbyvajicichMoznychPlacekZdarma;
        }

        return (int) $cena;
    }

    private function maObjednanouPlacku(array $r): bool
    {
        return Predmet::jeToPlacka($r[PredmetySql::KOD_PREDMETU]);
    }

    /**
     * Vrátí pole s popisy obecných slev uživatele (typicky procentuálních na
     * aktivity)
     *
     * @todo možnost (zvážit) použití objektu Sleva, který by se uměl aplikovat
     */
    public function slevyObecne()
    {
        return ['nic'];
    }

    /**
     * Vrátí pole s popisy speciálních slev a extra možností uživatele (typicky
     * vypravěčských, věci se slevami nebo zdarma apod.)
     *
     * @todo vypravěčská sleva s číslem apod. (migrovat z financí)
     *
     * @return array<string>
     */
    public function slevySpecialni(): array
    {
        $u = $this->u;
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
            return (float) $r[NakupySql::CENA_NAKUPNI];
        }
        if (isset($r[PredmetySql::CENA_AKTUALNI])) {
            return (float) $r[PredmetySql::CENA_AKTUALNI];
        }
        throw new \RuntimeException('Nelze načíst cenu předmětu s ID ' . ($r[PredmetySql::ID_PREDMETU] ?? 'neznámé'));
    }

    /**
     * @return PriceAfterDiscountDto cena věci v e-shopu pro daného uživatele
     */
    public function cena(array $r): PriceAfterDiscountDto
    {
        $cena = $this->puvodniCena($r);
        if (! ($typ = $r[PredmetySql::TYP])) {
            throw new \RuntimeException('Nenačten typ předmetu');
        }

        $polozka = $this->polozkaProSlevy($r, $typ, $cena);
        if ($polozka === null) {
            return new PriceAfterDiscountDto(finalPrice: $cena, discount: 0.0);
        }

        // Jedna položka na volání, protože takové je rozhraní téhle metody. Pravidlo
        // "nejlevnější tričko v košíku" tím pádem nemá košík, ve kterém by hledalo —
        // vychází jen proto, že Finance::zapoctiShop posílá nákupy od nejlevnějšího
        // (ORDER BY nakupy.cena_nakupni), takže první tričko, které sem přijde, je to
        // nejlevnější. Až se storefront překlopí a přestane volat cena() po jedné,
        // předá se celý košík najednou a tahle závislost na pořadí zmizí.
        $slevy = (new \App\Discount\DiscountCalculation(
            $this->dostupnaPravidla(),
            $this->pravaUzivatele(),
            $this->hodnotyNastaveni(),
            (float) $this->finance->bonusZaVedeniAktivit(),
        ))->apply([$polozka]);

        $sleva = $slevy[$polozka->key] ?? null;
        if ($sleva === null) {
            return new PriceAfterDiscountDto(finalPrice: $cena, discount: 0.0);
        }

        $this->zapoctiVycerpani($sleva->ruleCode);

        return new PriceAfterDiscountDto(
            finalPrice: $sleva->finalPrice,
            discount: $sleva->discountAmount,
        );
    }

    /**
     * Převede legacy řádek na položku, se kterou umí pracovat výpočet slev.
     *
     * Vrací null pro typy, na které žádné pravidlo nemíří — ušetří to načítání
     * pravidel u vstupného a proplácení bonusů.
     */
    private function polozkaProSlevy(array $r, $typ, float $cena): ?\App\Discount\DiscountableItem
    {
        $tag = \App\Enum\ProductTagCode::fromLegacyTyp((int) $typ);
        if ($tag === null) {
            return null;
        }

        $den = $r[PredmetySql::UBYTOVANI_DEN] ?? null;

        return new \App\Discount\DiscountableItem(
            key: (int) ($r[PredmetySql::ID_PREDMETU] ?? 0),
            productCode: (string) $r[PredmetySql::KOD_PREDMETU],
            price: $cena,
            tags: [$tag],
            accommodationDay: $den === null ? null : (int) $den,
        );
    }

    /**
     * Čítače zůstávají tady, protože výpočet slev je bezstavový a dostává vždy jednu
     * položku — nemá tedy jak si pamatovat, že tenhle kupující už kostku zdarma měl.
     */
    private function zapoctiVycerpani(string $kodPravidla): void
    {
        match ($kodPravidla) {
            'kostka_zdarma'   => $this->zbyvajicichMoznychKostekZdarma--,
            'placka_zdarma'   => $this->zbyvajicichMoznychPlacekZdarma--,
            'tricko_za_bonus' => $this->bonusovychTricekZdarma($this->bonusovychTricekZdarma() - 1),
            'jedno_tricko_zdarma', 'dve_tricka_zdarma' => $this->jakychkolivTricekZdarma($this->jakychkolivTricekZdarma() - 1),
            default => null,
        };
    }

    /**
     * Pravidla, na která kupujícímu ještě zbývá nárok. Vyčerpané se sem nedostanou,
     * takže výpočet je nemusí řešit.
     *
     * @return \App\Discount\DiscountRule[]
     */
    private function dostupnaPravidla(): array
    {
        $this->pravidla ??= (new \App\Discount\DiscountRuleLoader('dbFetchAll'))
            ->rulesForYear($this->systemoveNastaveni->rocnik());

        $zbyva = [
            'kostka_zdarma'       => $this->zbyvajicichMoznychKostekZdarma,
            'placka_zdarma'       => $this->zbyvajicichMoznychPlacekZdarma,
            'tricko_za_bonus'     => $this->bonusovychTricekZdarma(),
            'jedno_tricko_zdarma' => $this->jakychkolivTricekZdarma(),
            'dve_tricka_zdarma'   => $this->jakychkolivTricekZdarma(),
        ];

        return array_values(array_filter(
            $this->pravidla,
            static fn (\App\Discount\DiscountRule $pravidlo): bool => ($zbyva[$pravidlo->code] ?? 1) > 0,
        ));
    }

    /**
     * @return int[]
     */
    private function pravaUzivatele(): array
    {
        return $this->prava ??= (new \App\Discount\DiscountRuleLoader('dbFetchAll'))
            ->rightsOfUser($this->u->id());
    }

    /**
     * @return array<string, float>
     */
    private function hodnotyNastaveni(): array
    {
        return $this->nastaveniSlev ??= [
            \App\Discount\DiscountSetting::OrganizerMealDiscount->value   => (float) $this->systemoveNastaveni->slevaOrguNaJidloCastka(),
            \App\Discount\DiscountSetting::FreeShirtBonusThreshold->value => (float) $this->systemoveNastaveni->modreTrickoZdarmaOd(),
        ];
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

    private function bonusovychTricekZdarma(?int $bonusovychTricekZdarma = null): int
    {
        if ($bonusovychTricekZdarma !== null) {
            $this->bonusovychTricekZdarma = $bonusovychTricekZdarma;
        } elseif ($this->bonusovychTricekZdarma === null) {
            $this->bonusovychTricekZdarma = $this->finance->maximalniPocetBonusovychTricekZdarma();
        }

        return $this->bonusovychTricekZdarma;
    }
}
