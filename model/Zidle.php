<?php declare(strict_types=1);

namespace Gamecon;

class Zidle extends \DbObject
{
    protected static $tabulka = 'r_zidle_soupis';

    /**
     * Konstanty jsou kopie SQL tabulky `r_zidle_soupis`
     */
    public const ORGANIZATOR = 2; // (zdarma) Člen organizačního týmu GC
    public const VYPRAVEC = 6; // Organizátor aktivit na GC
    public const ZAZEMI = 7; // Členové zázemí GC (kuchařky, …)
    public const INFOPULT = 8; // Operátor infopultu
    public const VYPRAVECSKA_SKUPINA = 9; // Organizátorská skupina pořádající na GC (dodavatelé, …)
    public const PARTNER = 13; // Vystavovatelé, lidé od deskovek, atp.
    public const CESTNY_ORGANIZATOR = 15; // Bývalý organizátor GC
    public const ADMIN = 16; // Spec. židle pro úpravy databáze. NEPOUŽÍVAT.
    public const DOBROVOLNIK_SENIOR = 17; // Dobrovolník dlouhodobě spolupracující s GC
    public const STREDECNI_NOC_ZDARMA = 18;
    public const NEDELNI_NOC_ZDARMA = 19;
    public const SPRAVCE_FINANCI_GC = 20; // Organizátor, který může nakládat s financemi GC
    public const ORGANIZATOR_S_BONUSY_1 = 21;
    public const ORGANIZATOR_S_BONUSY_2 = 22;
    public const NEODHLASOVAT = 23;
    public const HERMAN = 24;

    public const PRIHLASEN_NA_LETOSNI_GC = (int)ZIDLE_PRIHLASEN;
    public const PRITOMEN_NA_LETOSNIM_GC = (int)ZIDLE_PRITOMEN;
    public const ODJEL_Z_LETOSNIHO_GC = (int)ZIDLE_ODJEL;

    /**
     * Přihlásil se, neboli registroval, na GameCon
     * @param int $rok
     * @return int
     */
    public static function prihlasenNaGcRoku(int $rok): int {
        return self::preProRok($rok) - 1;
    }

    /**
     * Prošel infopultem a byl na GameConu
     * @param int $rok
     * @return int
     */
    public static function pritomenNaGcRoku(int $rok): int {
        return self::preProRok($rok) - 2;
    }

    /**
     * Prošel infopultem na odchodu a odjel z GameConu
     * @param int $rok
     * @return int
     */
    public static function odjelZGcRoku(int $rok): int {
        return self::preProRok($rok) - 3;
    }

    private static function preProRok(int $rok): int {
        return -($rok - 2000) * 100; // předpona pro židle a práva vázaná na daný rok
    }

    /**
     * @param int[] $zidle
     * @return bool
     */
    public static function obsahujiVypravece(array $zidle): bool {
        return in_array(self::VYPRAVEC, $zidle, false);
    }

    /**
     * @param int[] $zidle
     * @return bool
     */
    public static function obsahujiOrganizatora(array $zidle): bool {
        $idZidli = array_map(static function ($idZidle) {
            return (int)$idZidle;
        }, $zidle);
        $maZidle = array_intersect($idZidli, self::dejIdZidliSOrganizatory());
        return count($maZidle) > 0;
    }

    /**
     * @return int[]
     */
    public static function dejIdZidliSOrganizatory(): array {
        return [self::ORGANIZATOR, self::ORGANIZATOR_S_BONUSY_1, self::ORGANIZATOR_S_BONUSY_2];
    }
}
