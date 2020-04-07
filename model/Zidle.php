<?php declare(strict_types=1);

namespace Gamecon;

class Zidle extends \DbObject
{
  public const ORGANIZATOR = 2; // (zdarma),Člen organizačního týmu GC
  public const VYPRAVEC = 6; //,Organizátor aktivit na GC
  public const ZAZEMI = 7; //,"Členové zázemí GC (kuchařky, …)"
  public const INFOPULT = 8; //,Operátor infopultu
  public const VYPRAVECSKA = 9; // skupina,"Organizátorská skupina pořádající na GC (dodavatelé, …)"
  public const PARTNER = 13; //,"Vystavovatelé, lidé od deskovek, atp."
  public const CESTNY = 15; // organizátor,Bývalý organizátor GC
  public const ADMIN = 16; //,Spec. židle pro úpravy databáze NEPOUŽÍVAT
  public const DOBROVOLNIK = 17; // senior,Dobrovolník dlouhodobě spolupracující s GC
  public const STREDECNI = 18; // noc zdarma,""
  public const NEDELNI = 19; // noc zdarma,""
  public const SPRAVCE = 20; // financí GC,"Organizátor, který může nakládat s financemi GC"
  public const ORGANIZATOR_S_BONUSY_1 = 21; // (s bonusy 1),""
  public const ORGANIZATOR_S_BONUSY_2 = 22; // (s bonusy 2),""

  /**
   * @param int[] $zidle
   * @return bool
   */
  public static function jeVypravec(array $zidle): bool {
    return in_array(self::VYPRAVEC, $zidle, false);
  }

  /**
   * @param int[] $zidle
   * @return bool
   */
  public static function jeOrganizator(array $zidle): bool {
    $idZidli = array_map(static function ($idZidle) {
      return (int)$idZidle;
    }, $zidle);
    $maZidle = array_intersect($idZidli, [self::ORGANIZATOR, self::ORGANIZATOR_S_BONUSY_1, self::ORGANIZATOR_S_BONUSY_2]);
    return count($maZidle) > 0;
  }
}
