<?php

namespace Gamecon\Cas;

/**
 * Datum a čas s českými názvy dnů a měsíců + další vychytávky
 */

class DateTimeCz extends \DateTime
{
  public const PONDELI = 'pondělí';
  public const UTERY = 'úterý';
  public const STREDA = 'středa';
  public const CTVRTEK = 'čtvrtek';
  public const PATEK = 'pátek';
  public const SOBOTA = 'sobota';
  public const NEDELE = 'neděle';

  public const FORMAT_DB = 'Y-m-d H:i:s';

  protected static $dny = [
    'Monday'    => 'pondělí',
    'Tuesday'   => 'úterý',
    'Wednesday' => 'středa',
    'Thursday'  => 'čtvrtek',
    'Friday'    => 'pátek',
    'Saturday'  => 'sobota',
    'Sunday'    => 'neděle',
  ];

  protected static $mesice = [
    'January'   =>  'ledna',
    'February'  =>  'února',
    'March'     =>  'března',
    'April'     =>  'dubna',
    'May'       =>  'května',
    'June'      =>  'června',
    'July'      =>  'července',
    'August'    =>  'srpna',
    'September' =>  'září',
    'October'   =>  'října',
    'November'  =>  'listopadu',
    'December'  =>  'prosince',
  ];

  public static function poradiDne(string $den): int {
    $denBezDiakritiky = odstranDiakritiku($den);
    $prvniDvePismena = substr($denBezDiakritiky, 0, 2);
    switch ($prvniDvePismena) {
      case 'po' :
        return 1;
      case 'ut' :
        return 2;
      case 'st' :
        return 3;
      case 'c' :
        return 4;
      case 'pa' :
        return 5;
      case 'so' :
        return 6;
      case 'ne' :
        return 7;
      default :
        throw new \RuntimeException("Unknown czech day name '$den'");
    }
  }

  /**
   * Obalovací fce, umožňuje vložit přímo řetězec pro konstruktor DateIntervalu
   */
  function add($interval) {
    if($interval instanceof \DateInterval) {
      return parent::add($interval);
    }
    return parent::add(new \DateInterval($interval));
  }

  /** Formát data s upravenými dny česky */
  function format($f) {
    return strtr(parent::format($f), static::$dny);
  }

  /** Vrací formát kompatibilní s mysql */
  function formatDb() {
    return parent::format(self::FORMAT_DB);
  }

  /**
   * Vrací běžně používaný formát data - tvar d. m. yyyy
   *
   * @return string
   */
  function formatDatumStandard() {
    return parent::format('j. n. Y');
  }

  /**
   * Vrací běžně používaný formát data a času s přesností na minuty - tvar d. m. yyyy 16:46
   *
   * @return string
   */
  function formatCasNaMinutyStandard() {
    return parent::format('j. n. Y H:i');
  }

  /**
   * Vrací běžně používaný formát data a času - tvar d. m. yyyy 16:46:33
   *
   * @return string
   */
  function formatCasStandard() {
    return parent::format('j. n. Y H:i:s');
  }

  /** Vrací blogový/dopisový formát */
  function formatBlog() {
    return strtr(parent::format('j. F Y'), static::$mesice);
  }

  /** Zvýší časový údaj o jeden den. Upravuje objekt. */
  function plusDen() {
    $this->add(new \DateInterval('P1D'));
  }

  /** Jestli je tento okamžik před okamžikem $d2 */
  function pred($d2) {
    if($d2 instanceof \DateTime)
      return $this->getTimestamp() < $d2->getTimestamp();
    else
      return $this->getTimestamp() < strtotime($d2);
  }

  /** Jestli je tento okamžik po okamžiku $d2 */
  function po($d2) {
    if($d2 instanceof \DateTime)
      return $this->getTimestamp() > $d2->getTimestamp();
    else
      return $this->getTimestamp() > strtotime($d2);
  }

  /** Vrací relativní formát času vůči současnému okamžiku */
  function relativni() {
    $rozdil = time() - $this->getTimestamp();
    if($rozdil < 0)
      return 'v budoucnosti';
    if($rozdil < 60)
      return "před $rozdil vteřinami";
    if($rozdil < 60*60)
      return 'před '.round($rozdil/60).' minutami';
    if(!$dny = $this->rozdilDne(new static())) // dnes
      return $this->format('G:i');
    else
      return $dny;
  }

  /**
   * Vrátí „včera“, „předevčírem“, „pozítří“ apod. (místo dnes vrací emptystring)
   */
  function rozdilDne(\DateTime $od) {
    $od=clone $od; // nutné znulování času pro funkční porovnání počtu dní
    $od->setTime(0,0);
    $do=clone $this;
    $do->setTime(0,0);
    $diff=(int)$od->diff($do)->format('%r%a');
    switch($diff) {
      case -2: return 'předevčírem';
      case -1: return 'včera';
      case 0: return '';
      case 1: return 'zítra';
      case 2: return 'pozítří';
      default:
        if($diff<0) {
          return 'před '.(-$diff).' dny';
        }
        if($diff<5) {
          return "za $diff dny";
        }
        return "za $diff dní";
    }
  }

  /** Jestli tento den je stejný s $d2 v formátu \DateTime nebo string s časem */
  function stejnyDen($d2): bool {
    if(!($d2 instanceof \DateTime)) {
      $d2 = new static($d2);
    }
    return $this->format('Y-m-d') == $d2->format('Y-m-d');
  }

  /** Zaokrouhlí nahoru na nejbližší vyšší jednotku */
  function zaokrouhlitNaHodinyNahoru(): DateTimeCz {
    if($this->format('is')==='0000') { // neni co zaokrouhlovat
      return $this->modify($this->format('Y-m-d H:00:00'));
    }
    return $this->modify($this->format('Y-m-d H:00:00'))->add(new \DateInterval('PT1H'));
  }

}
