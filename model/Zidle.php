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
    $maZidle = array_intersect($idZidli, [self::ORGANIZATOR, self::ORGANIZATOR_S_BONUSY_1, self::ORGANIZATOR_S_BONUSY_2]);
    return count($maZidle) > 0;
  }
}
