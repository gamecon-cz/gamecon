<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Which look of the public web a visitor sees. Most visitors are not logged in, so the choice belongs
 * to the browser (a cookie), not to an account. Whoever has not chosen gets the classic look.
 */
class VzhledWebu
{
    /** ?vzhled=moderni / ?vzhled=klasicky switches the look and remembers it */
    public const string GET_KLIC = 'vzhled';
    /** ?nahledVzhledu=moderni-b shows one page in that look and remembers nothing (frames of web/moduly/vzhledy.php) */
    public const string GET_KLIC_NAHLEDU = 'nahledVzhledu';
    public const string COOKIE = 'vzhledWebu';
    public const VzhledWebuEnum VYCHOZI = VzhledWebuEnum::KLASICKY;
    private const int PLATNOST_COOKIE_SEKUND = 365 * 24 * 3600;

    /**
     * @param array<string, mixed> $cookies typically $_COOKIE
     */
    public function __construct(private readonly array $cookies)
    {
    }

    public function getVzhled(): VzhledWebuEnum
    {
        // A value this version does not know (left by a newer one, or made up) counts as not chosen.
        return VzhledWebuEnum::tryFrom((string) ($this->cookies[self::COOKIE] ?? ''))
               ?? self::VYCHOZI;
    }

    /**
     * The look of the current page: the one from ?nahledVzhledu=… when it is known, otherwise the chosen one.
     *
     * @param array<string, mixed> $query typically $_GET
     */
    public function getVzhledStranky(array $query): VzhledWebuEnum
    {
        return VzhledWebuEnum::tryFrom((string) ($query[self::GET_KLIC_NAHLEDU] ?? ''))
               ?? $this->getVzhled();
    }

    /**
     * The URL of a page shown in the given look, without remembering it.
     */
    public static function getUrlNahledu(string $cesta, VzhledWebuEnum $vzhled): string
    {
        return $cesta . '?' . http_build_query([self::GET_KLIC_NAHLEDU => $vzhled->value]);
    }

    /**
     * Remembers a look chosen by ?vzhled=…, an unknown value changes nothing.
     *
     * @return VzhledWebuEnum|null the look just chosen, null when the value is unknown
     */
    public function zpracujVolbu(string $volba): ?VzhledWebuEnum
    {
        $vzhled = VzhledWebuEnum::tryFrom($volba);
        if ($vzhled !== null) {
            setcookie(self::COOKIE, $vzhled->value, [
                'expires'  => time() + self::PLATNOST_COOKIE_SEKUND,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }

        return $vzhled;
    }

    /**
     * The URL to switch to the given look from the current page: the current query with ?vzhled=…
     *
     * @param array<string, mixed> $query typically $_GET, without the 'req' added by the rewrite
     */
    public static function getUrlPrepnuti(string $cesta, array $query, VzhledWebuEnum $vzhled): string
    {
        // the chosen look ends a preview of another one
        unset($query[self::GET_KLIC_NAHLEDU]);
        $query[self::GET_KLIC] = $vzhled->value;

        return $cesta . '?' . http_build_query($query);
    }

    /**
     * The URL of the current page without ?vzhled=…, where to go back after switching.
     *
     * @param array<string, mixed> $query typically $_GET, without the 'req' added by the rewrite
     */
    public static function getUrlBezVolby(string $cesta, array $query): string
    {
        unset($query[self::GET_KLIC]);

        return $query === []
            ? $cesta
            : $cesta . '?' . http_build_query($query);
    }
}
