<?php

namespace Gamecon\Dev;

/**
 * Spárovací cookie pro magické přihlášení do archivu ({@see CrossSiteLogin}).
 *
 * Drží náhodný nonce a je scope `.gamecon.cz`, aby ji prohlížeč automaticky poslal
 * i na `NNNN.gamecon.cz`. NENÍ to přihlášení — sama o sobě nikoho nepřihlásí; je to
 * jen „důkaz stejného prohlížeče": archiv přihlásí jen když se nonce z této cookie
 * shoduje s nonce zapečeným v podepsaném `?gcsso=` tokenu. Sdílená URL v cizím
 * prohlížeči tuhle cookie nemá → mismatch → nepřihlásí.
 *
 * PHP 5.6-kompatibilní varianta (archivní ročníky 2015-2021 běží na PHP 5.6/7.3):
 * setcookie() options-array (PHP 7.3+) tu není, takže používáme poziční formu a
 * SameSite propašujeme připojením za path (starý trik, který prohlížeče respektují).
 */
final class SsoParovaciCookie
{
    const JMENO = 'gc_sso_pair';

    /**
     * O něco déle než TTL tokenu ({@see CrossSiteLogin::TTL_SEKUND}), aby cookie
     * přežila proklik přes bránu i případné přesměrování a token nikdy nevypršel
     * „dřív" než jeho párovací polovina.
     */
    const TTL_SEKUND = 600;

    /**
     * Nastaví spárovací cookie s daným nonce. Scope `.gamecon.cz` (aby dorazila i
     * na archiv), HttpOnly, SameSite=Lax (musí přijít při top-level navigaci na
     * archiv), Secure jen po HTTPS.
     *
     * @param string $nonce
     * @return void
     */
    public static function nastav($nonce)
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        // SameSite za path: poziční setcookie() v PHP 5.6 nemá samostatný argument,
        // ale prohlížeče berou "; SameSite=Lax" připojené k path. Path je vždy '/'.
        $path = '/; SameSite=Lax';

        setcookie(
            self::JMENO,
            $nonce,
            time() + self::TTL_SEKUND,
            $path,
            self::cookieDomena(),
            $secure,
            true
        );
    }

    /**
     * `.gamecon.cz` na produkčních subdoménách (sdílí se napříč admin/archiv),
     * jinak prázdno = host-only (prohlížeč jinak širší doménu odmítne).
     *
     * @return string
     */
    private static function cookieDomena()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        // Odřízneme případný port.
        $dily = explode(':', $host, 2);
        $host = $dily[0];

        $pripona = 'gamecon.cz';
        $delkaPripony = strlen($pripona);

        return substr($host, -$delkaPripony) === $pripona ? '.gamecon.cz' : '';
    }

    /**
     * @return string|null
     */
    public static function precti()
    {
        $nonce = isset($_COOKIE[self::JMENO]) ? $_COOKIE[self::JMENO] : null;

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
