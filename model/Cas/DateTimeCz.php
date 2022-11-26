<?php

namespace Gamecon\Cas;

use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;

/**
 * Datum a čas s českými názvy dnů a měsíců + další vychytávky
 */
class DateTimeCz extends \DateTime
{
    public const PONDELI = 'pondělí';
    public const UTERY   = 'úterý';
    public const STREDA  = 'středa';
    public const CTVRTEK = 'čtvrtek';
    public const PATEK   = 'pátek';
    public const SOBOTA  = 'sobota';
    public const NEDELE  = 'neděle';

    public const FORMAT_DB                     = 'Y-m-d H:i:s';
    public const FORMAT_DATUM_DB               = 'Y-m-d';
    public const FORMAT_DATUM_STANDARD         = 'j. n. Y';
    public const FORMAT_DATUM_A_CAS_STANDARD   = 'j. n. Y H:i:s';
    public const FORMAT_CAS_NA_MINUTY_STANDARD = 'j. n. Y H:i';
    public const FORMAT_CAS_SOUBOR             = 'Y-m-d_H-i-s';

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
        'January'   => 'ledna',
        'February'  => 'února',
        'March'     => 'března',
        'April'     => 'dubna',
        'May'       => 'května',
        'June'      => 'června',
        'July'      => 'července',
        'August'    => 'srpna',
        'September' => 'září',
        'October'   => 'října',
        'November'  => 'listopadu',
        'December'  => 'prosince',
    ];

    protected static $dnyVTydnuBezDiakritiky = [
        1 => 'pondeli',
        2 => 'utery',
        3 => 'streda',
        4 => 'ctvrtek',
        5 => 'patek',
        6 => 'sobota',
        7 => 'nedele',
    ];

    public static function createFromMysql(string $dateTime, \DateTimeZone $timeZone = null) {
        return static::createFromFormat('Y-m-d H:i:s', $dateTime, $timeZone);
    }

    public static function createFromFormat($format, $time, $timezone = null): \DateTime|false {
        try {
            $dateTime = parent::createFromFormat($format, $time, $timezone);
            if ($dateTime === false) {
                throw new \RuntimeException();
            }
            return new static($dateTime->format(DATE_ATOM));
        } catch (\Throwable $throwable) {
            throw new InvalidDateTimeFormat(
                sprintf(
                    "Can not create %s from value %s using format '%s': %s",
                    static::class,
                    var_export($time, true),
                    var_export($format, true),
                    $throwable->getMessage()
                )
            );
        }
    }

    public static function poradiDne(string $den): int {
        $hledanyDenMalymiPismeny     = mb_strtolower($den);
        $hledadnyDenBezDiakritiky    = odstranDiakritiku($hledanyDenMalymiPismeny);
        $poradiDnuZacinajicichStejne = [];
        foreach (self::$dnyVTydnuBezDiakritiky as $poradiDneVTydnu => $denVTydnuBezDiakritiky) {
            if (strpos($denVTydnuBezDiakritiky, $hledadnyDenBezDiakritiky) === 0) {
                $poradiDnuZacinajicichStejne[] = $poradiDneVTydnu;
            }
        }
        if (count($poradiDnuZacinajicichStejne) === 1) {
            return reset($poradiDnuZacinajicichStejne);
        }
        throw new \RuntimeException("Unknown czech day name '$den'");
    }

    public static function dejDnyVTydnu(): array {
        return self::$dny;
    }

    /**
     * @param \DateInterval $interval
     * Obalovací fce, umožňuje vložit přímo řetězec pro konstruktor DateIntervalu
     */
    public function add(\DateInterval $interval): \DateTime {
        if ($interval instanceof \DateInterval) {
            return parent::add($interval);
        }
        return parent::add(new \DateInterval($interval));
    }

    /** Formát data s upravenými dny česky */
    public function format($f): string {
        return strtr(parent::format($f), static::$dny);
    }

    /** Vrací formát kompatibilní s mysql */
    public function formatDb() {
        return parent::format(self::FORMAT_DB);
    }

    /** Vrací formát kompatibilní s mysql */
    public function formatDatumDb() {
        return parent::format(self::FORMAT_DATUM_DB);
    }

    /**
     * Vrací běžně používaný formát data - tvar d. m. yyyy
     *
     * @return string
     */
    public function formatDatumStandard() {
        return parent::format(self::FORMAT_DATUM_STANDARD);
    }

    /**
     * Vrací běžně používaný formát data a času s přesností na minuty - tvar d. m. yyyy 16:46
     *
     * @return string
     */
    public function formatCasNaMinutyStandard() {
        return parent::format(self::FORMAT_CAS_NA_MINUTY_STANDARD);
    }

    /**
     * Vrací běžně používaný formát data a času - tvar d. m. yyyy 16:46:33
     *
     * @return string
     */
    public function formatCasStandard() {
        return parent::format(self::FORMAT_DATUM_A_CAS_STANDARD);
    }

    public function formatCasSoubor(): string {
        return parent::format(self::FORMAT_CAS_SOUBOR);
    }

    /** Vrací blogový/dopisový formát */
    public function formatBlog() {
        return strtr(parent::format('j. F Y'), static::$mesice);
    }

    /** Zvýší časový údaj o jeden den. Upravuje objekt. */
    public function plusDen() {
        $this->add(new \DateInterval('P1D'));
    }

    /** Jestli je tento okamžik před okamžikem $d2 */
    public function pred($d2) {
        if ($d2 instanceof \DateTime) {
            return $this->getTimestamp() < $d2->getTimestamp();
        }
        return $this->getTimestamp() < strtotime($d2);
    }

    /**
     * Jestli je tento okamžik po okamžiku $d2
     * @param \DateTimeInterface|string
     */
    public function po($d2): bool {
        if ($d2 instanceof \DateTimeInterface) {
            return $this->getTimestamp() > $d2->getTimestamp();
        }
        return $this->getTimestamp() > strtotime($d2);
    }

    /** Vrací relativní formát času vůči současnému okamžiku */
    public function relativni(): string {
        $rozdil = time() - $this->getTimestamp();
        if ($rozdil < 0) {
            return 'v budoucnosti';
        }
        if ($rozdil < 2) {
            return "před okamžikem";
        }
        if ($rozdil < 60) {
            return "před $rozdil sekundami";
        }
        if (round($rozdil / 60) === 1.0) {
            return 'před minutou';
        }
        if ($rozdil < 60 * 60) {
            return 'před ' . round($rozdil / 60) . ' minutami';
        }
        $dny = $this->rozdilDne(new static('now', $this->getTimezone()));
        if (!$dny) { // dnes
            return $this->format('G:i');
        }
        return $dny;
    }

    /**
     * Vrátí „včera“, „předevčírem“, „pozítří“ apod. (místo dnes vrací emptystring)
     */
    public function rozdilDne(\DateTimeInterface $od) {
        $od   = clone $od;
        $od   = $od->setTime(0, 0); // nutné znulování času pro funkční porovnání počtu dní
        $do   = clone $this;
        $do   = $do->setTime(0, 0);
        $diff = (int)$od->diff($do)->format('%r%a');
        switch ($diff) {
            case -2:
                return 'předevčírem';
            case -1:
                return 'včera';
            case 0:
                return '';
            case 1:
                return 'zítra';
            case 2:
                return 'pozítří';
            default:
                if ($diff < 0) {
                    return 'před ' . (-$diff) . ' dny';
                }
                if ($diff < 5) {
                    return "za $diff dny";
                }
                return "za $diff dní";
        }
    }

    /** Jestli tento den je stejný s $d2 v formátu \DateTime nebo string s časem */
    public function stejnyDen($d2): bool {
        if (!$d2) {
            return false;
        }
        if (!($d2 instanceof \DateTime)) {
            $d2 = new static($d2, $this->getTimezone());
        }
        return $this->format('Y-m-d') == $d2->format('Y-m-d');
    }

    /** Zaokrouhlí nahoru na nejbližší vyšší jednotku */
    public function zaokrouhlitNaHodinyNahoru(): DateTimeCz {
        if ($this->format('is') === '0000') { // neni co zaokrouhlovat
            return $this->modify($this->format('Y-m-d H:00:00'));
        }
        return $this->modify($this->format('Y-m-d H:00:00'))->add(new \DateInterval('PT1H'));
    }

}
