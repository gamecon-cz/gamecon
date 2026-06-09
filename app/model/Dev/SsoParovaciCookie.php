<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Spárovací cookie pro magické přihlášení do archivu ({@see CrossSiteLogin}).
 *
 * Drží náhodný nonce a je scope `.gamecon.cz`, aby ji prohlížeč automaticky poslal
 * i na `NNNN.gamecon.cz`. NENÍ to přihlášení — sama o sobě nikoho nepřihlásí; je to
 * jen „důkaz stejného prohlížeče": archiv přihlásí jen když se nonce z této cookie
 * shoduje s nonce zapečeným v podepsaném `?gcsso=` tokenu. Sdílená URL v cizím
 * prohlížeči tuhle cookie nemá → mismatch → nepřihlásí. Produkce ani archiv tuhle
 * cookie nečtou nikde jinde než v ověření SSO, takže její únik sám o sobě nic nedá.
 *
 * Scope `.gamecon.cz` je záměrně širší než host-scoped JWT cookie (viz
 * nastaveni/jwt-bridge.php) — ale na rozdíl od ní nenese identitu.
 */
final class SsoParovaciCookie
{
    public const JMENO = 'gc_sso_pair';

    /**
     * O něco déle než TTL tokenu ({@see CrossSiteLogin::TTL_SEKUND}), aby cookie
     * přežila proklik přes bránu i případné přesměrování a token nikdy nevypršel
     * „dřív" než jeho párovací polovina.
     */
    public const TTL_SEKUND = 10 * 60;

    /**
     * Nastaví spárovací cookie s daným nonce. Scope `.gamecon.cz` (aby dorazila i
     * na archiv), HttpOnly, SameSite=Lax (musí přijít při top-level navigaci na
     * archiv), Secure jen po HTTPS. Mimo gamecon.cz (lokál, preview na jiném hostu)
     * spadne na host-only cookie — feature stejně cílí jen na ostré subdomény.
     */
    public static function nastav(string $nonce): void
    {
        setcookie(self::JMENO, $nonce, [
            'expires'  => time() + self::TTL_SEKUND,
            'path'     => '/',
            'domain'   => self::cookieDomena(),
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * `.gamecon.cz` na produkčních subdoménách (sdílí se napříč admin/archiv),
     * jinak prázdno = host-only (prohlížeč jinak širší doménu odmítne).
     */
    private static function cookieDomena(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Odřízneme případný port.
        $host = explode(':', $host, 2)[0];

        return str_ends_with($host, 'gamecon.cz') ? '.gamecon.cz' : '';
    }

    public static function precti(): ?string
    {
        $nonce = $_COOKIE[self::JMENO] ?? null;

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
