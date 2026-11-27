<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Krátký popis zařízení z hlavičky User-Agent („Chrome, Android“), aby uživatel i admin poznali, které zařízení
 * je které. Celá hlavička se neukládá.
 */
final class PopisZarizeni
{
    public const string NEZNAME = 'neznámé zařízení';

    /**
     * Pořadí rozhoduje: Edge a Opera se hlásí i jako Chrome, Chrome i jako Safari.
     */
    private const array PROHLIZECE = [
        'Edg'            => 'Edge',
        'OPR/'           => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'Firefox'        => 'Firefox',
        'FxiOS'          => 'Firefox',
        'CriOS'          => 'Chrome',
        'Chrome'         => 'Chrome',
        'Safari'         => 'Safari',
    ];

    /**
     * Pořadí rozhoduje: Android se hlásí i jako Linux, iPhone i jako Mac OS X.
     */
    private const array SYSTEMY = [
        'Android'   => 'Android',
        'iPhone'    => 'iOS',
        'iPad'      => 'iOS',
        'iPod'      => 'iOS',
        'Windows'   => 'Windows',
        'CrOS'      => 'ChromeOS',
        'Macintosh' => 'macOS',
        'Linux'     => 'Linux',
    ];

    public static function zUserAgenta(string $userAgent): string
    {
        $casti = array_filter([
            self::najdi($userAgent, self::PROHLIZECE),
            self::najdi($userAgent, self::SYSTEMY),
        ]);

        return $casti === []
            ? self::NEZNAME
            : implode(', ', $casti);
    }

    /**
     * @param array<string, string> $znamky
     */
    private static function najdi(string $userAgent, array $znamky): ?string
    {
        foreach ($znamky as $znamka => $nazev) {
            if (str_contains($userAgent, $znamka)) {
                return $nazev;
            }
        }

        return null;
    }
}
