<?php declare(strict_types=1);

namespace Gamecon;

use Gamecon\Role\Role;

/**
 * @method static Pravo|null zId($id, bool $zCache = false)
 */
class Pravo extends \DbObject
{
    protected static $tabulka = 'r_prava_soupis';
    protected static $pk      = 'id_prava';

    /**
     * Konstanty jsou kopie SQL tabulky `r_prava_soupis`
     */
    public const PORADANI_AKTIVIT                 = 4; // Uživatel může pořádat aktivity (je v nabídce pořadatelů aktivit a má v administraci nabídku „moje aktivity“)
    public const PREKRYVANI_AKTIVIT               = 5; // Smí mít zaregistrovaných víc aktivit v jeden čas
    public const PLNY_SERVIS                      = 7; // Uživatele kompletně platí a zajišťuje GC
    public const ZMENA_HISTORIE_AKTIVIT           = 8; // Může přihlašovat a odhlašovat lidi z aktivit, které už proběhly
    public const PRIHLASOVANI_NA_DOSUD_NEOTEVRENE = 9; // Může přihlašovat a odhlašovat lidi z aktivit, které ještě nejsou Aktivované

    // Práva pro panely v adminu
    public const ADMINISTRACE_INFOPULT      = 100;
    public const ADMINISTRACE_UBYTOVANI     = 101;
    public const ADMINISTRACE_AKCE          = 102;
    public const ADMINISTRACE_PREZENCE      = 103;
    public const ADMINISTRACE_REPORTY       = 104;
    public const ADMINISTRACE_WEB           = 105;
    public const ADMINISTRACE_PRAVA         = 106;
    public const ADMINISTRACE_STATISTIKY    = 107;
    public const ADMINISTRACE_FINANCE       = 108;
    public const ADMINISTRACE_MOJE_AKTIVITY = 109;
    public const ADMINISTRACE_NASTAVENI     = 110;
    public const ADMINISTRACE_PENIZE        = 111;

    public const PLACKA_ZDARMA    = 1002;
    public const KOSTKA_ZDARMA    = 1003;
    public const JIDLO_SE_SLEVOU  = 1004; // Může si objednávat jídlo se slevou
    public const JIDLO_ZDARMA     = 1005; // Může si objednávat jídlo zdarma
    public const UBYTOVANI_ZDARMA = 1008; // Má zdarma ubytování po celou dobu
    /**
     * modré tričko zdarma při slevě, jejíž hodnota je určená konstantou @see MODRE_TRICKO_ZDARMA_OD
     * @see \Gamecon\SystemoveNastaveni\SystemoveNastaveni::definujOdvozeneKonstanty
     */
    public const MODRE_TRICKO_ZDARMA                         = 1012;
    public const UBYTOVANI_STREDECNI_NOC_ZDARMA              = 1015;
    public const NERUSIT_AUTOMATICKY_OBJEDNAVKY              = 1016; // uživateli se při nezaplacení včas nebudou automaticky rušit objednávky
    public const UBYTOVANI_NEDELNI_NOC_ZDARMA                = 1018;
    public const CASTECNA_SLEVA_NA_AKTIVITY                  = 1019; // Sleva 40% na aktivity
    public const DVE_JAKAKOLI_TRICKA_ZDARMA                  = 1020;
    public const MUZE_OBJEDNAVAT_MODRA_TRICKA                = 1021; // Může si objednávat modrá trička
    public const MUZE_OBJEDNAVAT_CERVENA_TRICKA              = 1022; // Může si objednávat červená trička
    public const AKTIVITY_ZDARMA                             = 1023; // Sleva 100% na aktivity
    public const ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI = 1024; // V adminu v sekci statistiky v tabulce vlevo nahoře se tato role vypisuje
    public const VYPISOVAT_V_REPORTU_NEUBYTOVANYCH           = 1025; // V reportu Nepřihlášení a neubytovaní vypravěči se lidé na této roli vypisují
    public const TITUL_ORGANIZATOR                           = 1026; // V různých výpisech se označuje jako organizátor
    public const UNIKATNI_ROLE                               = 1027; // Uživatel může mít jen jednu roli s tímto právem (další názvy "jedinečná", "výlučná", "exkluzivní", "anti-role")
    public const BEZ_SLEVY_ZA_VEDENI_AKTIVIT                 = 1028; // Nedostává slevu za vedení aktivit ani účast na tech. aktivitách
    public const UBYTOVANI_CTVRTECNI_NOC_ZDARMA              = 1029;
    public const UBYTOVANI_PATECNI_NOC_ZDARMA                = 1030;
    public const UBYTOVANI_SOBOTNI_NOC_ZDARMA                = 1031;
    public const HROMADNA_AKTIVACE_AKTIVIT                   = 1032; // Může použít nebezpečné tlačítko "Aktivovat hromadně" u aktivit
    public const ZMENA_PRAV                                  = 1033;
    public const PROVADI_KOREKCE                             = 1034; // Může nastavit checkbox u aktivity o provedené korekci a nesmaže ho při úpravě textu

    public static function dejIdsVsechPrav(): array
    {
        static $idsVsechPrav;
        if ($idsVsechPrav === null) {
            $idsVsechPrav = (new \ReflectionClass(static::class))
                ->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        }
        return $idsVsechPrav;
    }

}
