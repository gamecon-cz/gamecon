<?php

declare(strict_types=1);

namespace Gamecon\Cas;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;

/**
 * Datum pro účastníka v jazyce požadavku (nebo v předaném jazyce).
 *
 * `DateTimeCz::format()` vrací dny vždy česky a zůstává tak schválně: jeho výstup slouží
 * i jako klíč (slugy dnů programu, párování v importu). Text pro člověka se skládá tady.
 */
final class DatumVJazyce
{
    /** „pátek" / „Friday" */
    public static function denVTydnu(\DateTimeInterface $datum, ?Jazyk $jazyk = null): string
    {
        return self::format($datum, 'EEEE', $jazyk);
    }

    /** „1. července" / „1 July" */
    public static function denAMesic(\DateTimeInterface $datum, ?Jazyk $jazyk = null): string
    {
        return self::format($datum, self::vzorDneAMesice($jazyk), $jazyk);
    }

    /** „1. července 14:00" / „1 July 14:00" */
    public static function denAMesicSCasem(\DateTimeInterface $datum, ?Jazyk $jazyk = null): string
    {
        return self::format($datum, self::vzorDneAMesice($jazyk) . ' HH:mm', $jazyk);
    }

    private static function vzorDneAMesice(?Jazyk $jazyk): string
    {
        return match ($jazyk ?? AktualniJazyk::jazyk()) {
            Jazyk::CESTINA    => 'd. MMMM',
            Jazyk::ANGLICTINA => 'd MMMM',
        };
    }

    private static function format(\DateTimeInterface $datum, string $vzor, ?Jazyk $jazyk): string
    {
        $formatter = new \IntlDateFormatter(
            ($jazyk ?? AktualniJazyk::jazyk())->locale(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $datum->getTimezone(),
            \IntlDateFormatter::GREGORIAN,
            $vzor,
        );

        return (string)$formatter->format($datum);
    }
}
