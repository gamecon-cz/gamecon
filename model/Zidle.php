<?php declare(strict_types=1);

namespace Gamecon;

class Zidle extends \DbObject
{
    protected static $tabulka = 'r_zidle_soupis';

    /**
     * Konstanty jsou kopie SQL tabulky `r_zidle_soupis`
     */
    public const ORGANIZATOR            = 2; // (zdarma) Člen organizačního týmu GC
    public const VYPRAVEC               = 6; // Organizátor aktivit na GC
    public const ZAZEMI                 = 7; // Členové zázemí GC (kuchařky, …)
    public const INFOPULT               = 8; // Operátor infopultu
    public const VYPRAVECSKA_SKUPINA    = 9; // Organizátorská skupina pořádající na GC (dodavatelé, …)
    public const PARTNER                = 13; // Vystavovatelé, lidé od deskovek, atp.
    public const CESTNY_ORGANIZATOR     = 15; // Bývalý organizátor GC
    public const ADMIN                  = 16; // Spec. židle pro úpravy databáze. NEPOUŽÍVAT.
    public const DOBROVOLNIK_SENIOR     = 17; // Dobrovolník dlouhodobě spolupracující s GC
    public const STREDECNI_NOC_ZDARMA   = 18;
    public const NEDELNI_NOC_ZDARMA     = 19;
    public const SPRAVCE_FINANCI_GC     = 20; // Organizátor, který může nakládat s financemi GC
    public const ORGANIZATOR_S_BONUSY_1 = 21;
    public const ORGANIZATOR_S_BONUSY_2 = 22;
    public const NEODHLASOVAT           = 23;
    public const HERMAN                 = 24;
    public const BRIGADNIK              = 25;

    public const PRIHLASEN_NA_LETOSNI_GC = ZIDLE_PRIHLASEN;
    public const PRITOMEN_NA_LETOSNIM_GC = ZIDLE_PRITOMEN;
    public const ODJEL_Z_LETOSNIHO_GC    = ZIDLE_ODJEL;

    public const UDALOST_PRIHLASEN = 'přihlášen';
    public const UDALOST_PRITOMEN  = 'přítomen';
    public const UDALOST_ODJEL     = 'odjel';

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
     * @param int[] $idsZidli
     * @return bool
     */
    public static function obsahujiVypravece(array $idsZidli): bool {
        return in_array(self::VYPRAVEC, $idsZidli, false);
    }

    /**
     * @param int[] $idsZidli
     * @return bool
     */
    public static function obsahujiOrganizatora(array $idsZidli): bool {
        $idZidli = array_map(static function ($idZidle) {
            return (int)$idZidle;
        }, $idsZidli);
        $maZidle = array_intersect($idZidli, self::dejIdZidliSOrganizatory());
        return count($maZidle) > 0;
    }

    /**
     * @param int[] $idsZidli
     * @return bool
     */
    public static function obsahujiPartnera(array $idsZidli): bool {
        return in_array(self::PARTNER, $idsZidli, false);
    }

    /**
     * @return int[]
     */
    public static function dejIdZidliSOrganizatory(): array {
        return [self::ORGANIZATOR, self::ORGANIZATOR_S_BONUSY_1, self::ORGANIZATOR_S_BONUSY_2];
    }

    public static function nazevZidle(int $zidle): string {
        switch ($zidle) {
            case 2 :
                return 'Organizátor (zdarma)';
            case 6 :
                return 'Vypravěč';
            case 7 :
                return 'Zázemí';
            case 8 :
                return 'Infopult';
            case 9 :
                return 'Vypravěčská skupina';
            case 13 :
                return 'Partner';
            case 15 :
                return 'Čestný organizátor';
            case 16 :
                return 'Admin';
            case 17 :
                return 'Dobrovolník senior';
            case 18 :
                return 'Středeční noc zdarma';
            case 19 :
                return 'Nedělní noc zdarma';
            case 20 :
                return 'Správce financí GC';
            case 21 :
                return 'Organizátor (s bonusy 1)';
            case 22 :
                return 'Organizátor (s bonusy 2)';
            case 23 :
                return 'Neodhlašovat';
            case 24 :
                return 'Herman';
            default :
                $rok     = self::rokDleZidle($zidle);
                $udalost = self::udalostDleZidle($zidle);

                return "GC{$rok} {$udalost}";
        }
    }

    public static function rokDleZidle(int $zidleUcastiNagGc): int {
        if (!self::jeToUdalostNaGc($zidleUcastiNagGc)) {
            throw new \LogicException("Role (židle) s ID $zidleUcastiNagGc v sobě nemá ročník");
        }
        return (int)(abs($zidleUcastiNagGc) / 100) + 2000;
    }

    public static function udalostDleZidle(int $zidleUcastiNagGc): string {
        if (!self::jeToUdalostNaGc($zidleUcastiNagGc)) {
            throw new \LogicException("Role (židle) s ID $zidleUcastiNagGc v sobě nemá ročník a tím ani událost");
        }
        switch (abs($zidleUcastiNagGc) % 100) {
            case 1 :
                return self::UDALOST_PRIHLASEN;
            case 2 :
                return self::UDALOST_PRITOMEN;
            case 3 :
                return self::UDALOST_ODJEL;
            default :
                throw new \RuntimeException("Role (židle) s ID $zidleUcastiNagGc v sobě má neznámou událost");
        }
    }

    public static function jeToUdalostNaGc(int $zidle): bool {
        return $zidle < 0;
    }
}
