<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Base64 pro adresy (RFC 4648, znaky `-` a `_`, bez doplnění `=`), ve kterém prohlížeč posílá klíče subscription
 * a ve kterém jsou VAPID klíče.
 */
final class Base64Url
{
    private const string VZOR = '/^[A-Za-z0-9_-]+$/D';

    /**
     * Kolik bajtů řetězec nese, null když to není base64url.
     */
    public static function getDelkaPoDekodovani(string $hodnota): ?int
    {
        $dekodovano = self::dekoduj($hodnota);

        return $dekodovano === null
            ? null
            : strlen($dekodovano);
    }

    /**
     * @return string|null bajty, null když to není base64url
     */
    public static function dekoduj(string $hodnota): ?string
    {
        if (preg_match(self::VZOR, $hodnota) !== 1) {
            return null;
        }
        $dekodovano = base64_decode(strtr($hodnota, '-_', '+/'), true);

        return $dekodovano === false
            ? null
            : $dekodovano;
    }
}
