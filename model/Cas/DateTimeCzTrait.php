<?php

namespace Gamecon\Cas;

use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;
use Granam\RemoveDiacritics\RemoveDiacritics;

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
    public const FORMAT_DATUM_LETOS            = 'j. n.';
    public const FORMAT_DATUM_STANDARD         = 'j. n. Y';
    public const FORMAT_DATUM_A_CAS_STANDARD   = 'j. n. Y H:i:s';
    public const FORMAT_CAS_NA_MINUTY_STANDARD = 'j. n. Y H:i';
    public const FORMAT_ZACATEK_UDALOSTI       = 'j. n. Y \v H:i';
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

    protected static $dnyIndexovanePoradim = [
        1 => 'pondělí',
        2 => 'úterý',
        3 => 'středa',
        4 => 'čtvrtek',
        5 => 'pátek',
        6 => 'sobota',
        7 => 'neděle',
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

    protected static $zkratkyDnu = [
        1 => 'Po',
        2 => 'Út',
        3 => 'St',
        4 => 'Čt',
        5 => 'Pá',
        6 => 'So',
        7 => 'Ne',
    ];

    /**
     * Z 'Neděle' udělá 'Ne - Po'
     */
    public static function denNaPrelomDnuVeZkratkach(string $den, string $oddelovac = ' - '): string
    {
        $den              = trim($den);
        $denBezDiakritiky = RemoveDiacritics::toConstantLikeValue($den);
        $poradiDne        = array_search($denBezDiakritiky, self::$dnyVTydnuBezDiakritiky);
        if ($poradiDne === false) {
            return $den;
        }
        return static::poradiDneVTydnuNaPrelomDnuVeZkratkach(
            $poradiDne,
            // první písmeno bylo velké,tak to zachováme
            preg_match('~^[[:upper:]]~u', $den) !== 0,
            $oddelovac,
        );
    }

    /**
     * Z 'Neděle' udělá 'Ne - Po'
     */
    public static function poradiDneVTydnuNaPrelomDnuVeZkratkach(
        int    $poradiDneVTydnu,
        bool   $zacatekVelkymiPismeny,
        string $oddelovac = ' - ',
    ): string
    {
        $poradiNasledujicihoDne = self::poradiNasledujicihoDne($poradiDneVTydnu);
        $denZkratka             = self::$zkratkyDnu[$poradiDneVTydnu];
        $nasledujiciDenZkratka  = self::$zkratkyDnu[$poradiNasledujicihoDne];
        if ($zacatekVelkymiPismeny) {
            $denZkratka            = mb_ucfirst($denZkratka);
            $nasledujiciDenZkratka = mb_ucfirst($nasledujiciDenZkratka);
        } else {
            $denZkratka            = mb_strtolower($denZkratka);
            $nasledujiciDenZkratka = mb_strtolower($nasledujiciDenZkratka);
        }
        return $denZkratka . $oddelovac . $nasledujiciDenZkratka;
    }

    private static function poradiNasledujicihoDne(int $poradiDne): int
    {
        $poradiDnu = array_keys(self::$dnyVTydnuBezDiakritiky);
        if ($poradiDne < max($poradiDnu)) {
            return $poradiDne + 1;
        }
        return min($poradiDnu); // po neděli je pondělí, takže číslo 1
    }

    /**
     * @param string $dateTime
     * @param \DateTimeZone|null $timeZone
     * @return DateTimeCz|false
     */
    public static function createFromMysql(string $dateTime, \DateTimeZone $timeZone = null)
    {
        return static::createFromFormat('Y-m-d H:i:s', $dateTime, $timeZone);
    }

    public static function createFromFormat(string $format, string $datetime, ?\DateTimeZone $timezone = null): static|false
    {
        try {
            $dateTime = parent::createFromFormat($format, $datetime, $timezone);
            if ($dateTime === false) {
                throw new \RuntimeException();
            }
            return new static($dateTime->format(DATE_ATOM));
        } catch (\Throwable $throwable) {
            throw new InvalidDateTimeFormat(
                sprintf(
                    "Can not create %s from value %s using format '%s': %s",
                    static::class,
                    var_export($datetime, true),
                    var_export($format, true),
                    $throwable->getMessage(),
                )
            );
        }
    }

    public static function poradiDne(string $den): int
    {
        $hledadnyDenBezDiakritiky    = RemoveDiacritics::toConstantLikeValue($den);
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

    public static function formatujProSablonu($hodnota, array $parametry): string
    {
        $datum = $hodnota instanceof \DateTimeInterface
            ? $hodnota
            : static::createFromMysql((string)$hodnota);
        if (!$datum) {
            return (string)$hodnota;
        }
        foreach ($parametry as $parametr) {
            if (!is_string($parametr)) {
                continue;
            }
            $parametr = strtoupper(trim($parametr));
            if (!str_starts_with($parametr, 'FORMAT_')) {
                continue;
            }
            /** například @see \Gamecon\Cas\DateTimeCzTrait::FORMAT_ZACATEK_UDALOSTI */
            $classConstant = static::class . '::' . $parametr;
            if (!defined(static::class . '::' . $parametr)) {
                continue;
            }
            $format = constant($classConstant);
            return $datum->format($format);
        }
        return (string)$hodnota;
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
        return (string)parent::format('j. n. Y \v\e H:i');
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
    public function relativni(\DateTimeInterface $ted = null): string
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
            return $this->vPodleHodin() . ' ' . $this->format('G:i');
        }
        return $rozdilDni;
    }

    private function vPodleHodin(): string
    {
        $hodiny = $this->format('G');
        return match (substr($hodiny, 0, 1)) {
            '2', '3', '4', '5' => 've',
            default => 'v',
        };
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
