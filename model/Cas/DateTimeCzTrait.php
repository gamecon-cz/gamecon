<?php

namespace Gamecon\Cas;

use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;

/**
 * Datum a čas s českými názvy dnů a měsíců + další vychytávky
 * @method DateTimeCz add(\DateInterval $interval)
 * @method static DateTimeCz createFromInterface(\DateTimeInterface $object)
 */
trait DateTimeCzTrait
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

    /**
     * @param string $dateTime
     * @param \DateTimeZone|null $timeZone
     * @return DateTimeCz|false
     */
    public static function createFromMysql(string $dateTime, \DateTimeZone $timeZone = null)
    {
        return static::createFromFormat('Y-m-d H:i:s', $dateTime, $timeZone);
    }

    /**
     * @param $format
     * @param $time
     * @param $timezone
     * @return static|false
     */
    public static function createFromFormat($format, $time, $timezone = null): static|false
    {
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
                    $throwable->getMessage(),
                )
            );
        }
    }

    public static function poradiDne(string $den): int
    {
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

    public static function dejDnyVTydnu(): array
    {
        return self::$dny;
    }

    /** Formát data s upravenými dny česky */
    public function format($f): string
    {
        return strtr(parent::format($f), static::$dny);
    }

    /** Vrací formát kompatibilní s mysql */
    public function formatDb()
    {
        return parent::format(self::FORMAT_DB);
    }

    /** Vrací formát kompatibilní s mysql */
    public function formatDatumDb()
    {
        return parent::format(self::FORMAT_DATUM_DB);
    }

    /**
     * Vrací běžně používaný formát data - tvar d. m. yyyy
     *
     * @return string
     */
    public function formatDatumStandard()
    {
        return parent::format(self::FORMAT_DATUM_STANDARD);
    }

    public function formatDatumStandardZarovnaneHtml(): string
    {
        return $this->zarovnatProHtml($this->formatDatumStandard());
    }

    protected function zarovnatProHtml(string $datum): string
    {
        return preg_replace('~(^\d[.])~', '&nbsp;&nbsp;$1', $datum);
    }

    /**
     * Vrací běžně používaný formát data a času s přesností na minuty - tvar d. m. yyyy 16:46
     *
     * @return string
     */
    public function formatCasNaMinutyStandard()
    {
        return parent::format(self::FORMAT_CAS_NA_MINUTY_STANDARD);
    }

    /**
     * Vrací běžně používaný formát data a času - tvar d. m. yyyy 16:46:33
     *
     * @return string
     */
    public function formatCasStandard()
    {
        return parent::format(self::FORMAT_DATUM_A_CAS_STANDARD);
    }

    public function formatCasStandardZarovnaneHtml(): string
    {
        return $this->zarovnatProHtml($this->formatCasStandard());
    }

    public function formatCasSoubor(): string
    {
        return parent::format(self::FORMAT_CAS_SOUBOR);
    }

    /** Vrací blogový/dopisový formát */
    public function formatBlog()
    {
        return strtr(parent::format('j. F Y'), static::$mesice);
    }

    public function formatCasZacatekUdalosti(): string
    {
        return (string)parent::format('j. n. \v\e H:i');
    }

    /** Zvýší časový údaj o jeden den. Upravuje objekt. */
    public function plusDen()
    {
        $this->add(new \DateInterval('P1D'));
    }

    /** Jestli je tento okamžik před okamžikem $d2 */
    public function pred($d2)
    {
        if ($d2 instanceof \DateTime) {
            return $this->getTimestamp() < $d2->getTimestamp();
        }
        return $this->getTimestamp() < strtotime($d2);
    }

    /**
     * Jestli je tento okamžik po okamžiku $d2
     * @param \DateTimeInterface|string
     */
    public function po($d2): bool
    {
        if ($d2 instanceof \DateTimeInterface) {
            return $this->getTimestamp() > $d2->getTimestamp();
        }
        return $this->getTimestamp() > strtotime($d2);
    }

    /** Vrací relativní formát času vůči současnému okamžiku */
    public function relativni(\DateTimeImmutable $ted = null): string
    {
        $rozdil = ($ted?->getTimestamp() ?? time()) - $this->getTimestamp();
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
        $rozdilDni = $this->rozdilDni($ted ?? new static('now', $this->getTimezone()));
        if (!$rozdilDni) { // do 24 hodin
            return $this->format('G:i');
        }
        return $rozdilDni;
    }

    public function relativniVBudoucnu(\DateTimeInterface $ted = null): string
    {
        $rozdil = $this->getTimestamp() - ($ted?->getTimestamp() ?? time());
        if ($rozdil < 0) {
            return 'v minulosti';
        }
        if ($rozdil < 2) {
            return "za okamžik";
        }
        if ($rozdil < 60) {
            return "za $rozdil sekund";
        }
        if (round($rozdil / 60) === 1.0) {
            return 'za minutu';
        }
        if ($rozdil < 60 * 60) {
            return 'za ' . round($rozdil / 60) . ' minut';
        }
        $rozdilDni = $this->rozdilDni($ted ?? new static('now', $this->getTimezone()));
        if ($rozdilDni === '') { // do 24 hodin
            return 'v ' . $this->format('G:i');
        }
        return $rozdilDni;
    }

    /**
     * Vrátí „včera“, „předevčírem“, „pozítří“ apod. (místo dnes vrací emptystring)
     */
    public function rozdilDni(\DateTimeInterface $od): string
    {
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
                if ($diff === 7) {
                    return "za týden";
                }
                return "za $diff dní";
        }
    }

    /** Jestli tento den je stejný s $d2 v formátu \DateTime nebo string s časem */
    public function stejnyDen($d2): bool
    {
        if (!$d2) {
            return false;
        }
        if (!($d2 instanceof \DateTime)) {
            $d2 = new static($d2, $this->getTimezone());
        }
        return $this->format('Y-m-d') == $d2->format('Y-m-d');
    }

    /** Zaokrouhlí nahoru na nejbližší vyšší jednotku */
    public function zaokrouhlitNaHodinyNahoru(): DateTimeCzTrait
    {
        if ($this->format('is') === '0000') { // neni co zaokrouhlovat
            return $this->modify($this->format('Y-m-d H:00:00'));
        }
        return $this->modify($this->format('Y-m-d H:00:00'))->add(new \DateInterval('PT1H'));
    }

}