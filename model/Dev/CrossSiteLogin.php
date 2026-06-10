<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Magické přihlášení napříč subdoménami: hlavní admin (admin.gamecon.cz) podepíše
 * token vázaný na e-mail přihlášeného uživatele, archiv (NNNN.gamecon.cz) ho ověří
 * a uživatele podle e-mailu přihlásí — bez zadávání hesla.
 *
 * Token nese jen IDENTITU (e-mail) a NESMÍ sám o sobě nikoho přihlásit: podepsaný
 * odkaz se může sdílet jako každý jiný. Proto je k němu při ověření vyžadován ještě
 * spárovaný „nonce", který zná jen prohlížeč, co na odkaz klikl (cookie `gc_sso_pair`
 * scope `.gamecon.cz`). Token commituje na konkrétní nonce; archiv přihlásí jen když
 * se nonce z tokenu shoduje s nonce z cookie. Sdílená URL nonce v cizím prohlížeči
 * nemá → nepřihlásí. Viz {@see GateLink} (ten řeší jen průchod bránou,
 * ne přihlášení) a admin/scripts/prihlaseni.php (ověřovací strana).
 *
 * Podpis stojí na `SECRET_CRYPTO_KEY`, který je BAJTOVĚ STEJNÝ na ostré i ve všech
 * archivních images (viz audit v CLAUDE.md) — archiv tak umí ověřit, co hlavní admin
 * podepsal, bez zavádění nového tajemství.
 *
 * Formát tokenu (`?gcsso=`):
 *
 *     gcsso = base64url(email "|" expiry "|" nonce) "." base64url(HMAC_SHA256(payload, secret))
 *
 * `expiry` jsou ASCII číslice unixového času; HMAC se počítá nad celým payloadem
 * (e-mail|expiry|nonce). base64url = RFC 4648 §5 bez `=`.
 *
 * Když secret není nastavený (lokální vývoj), {@see self::podepis} vrátí prázdný
 * řetězec a volající `?gcsso=` nepřipojí — feature je prostě vypnutá.
 */
final class CrossSiteLogin
{
    /**
     * Krátká platnost: token je jednorázový proklik z administračního rozcestníku,
     * ne dlouhé sezení. Stačí na přesměrování přes bránu a přihlášení v archivu;
     * leaknutý odkaz je tak po pár minutách mrtvý.
     */
    public const TTL_SEKUND = 5 * 60;

    private const ODDELOVAC_PAYLOADU = '|';

    /**
     * Vrátí hodnotu pro `?gcsso=` (samotný token, bez prefixu) podepsanou pro daný
     * e-mail a nonce. Prázdný řetězec, pokud secret není nastavený — volající pak
     * `?gcsso=` nepřipojuje.
     */
    public static function podepis(
        string $email,
        string $nonce,
        string $secret,
        ?int $ted = null,
    ): string {
        if ($secret === '') {
            return '';
        }

        $expiry = (string) (($ted ?? time()) + self::TTL_SEKUND);
        $payload = $email . self::ODDELOVAC_PAYLOADU . $expiry . self::ODDELOVAC_PAYLOADU . $nonce;
        $podpis = hash_hmac('sha256', $payload, $secret, true);

        return self::base64Url($payload) . '.' . self::base64Url($podpis);
    }

    /**
     * Ověří token: správný podpis a nevypršelá platnost. Vrátí ověřený e-mail +
     * nonce, nebo null při jakékoli nesrovnalosti (špatný formát, neplatný podpis,
     * vypršení). Shodu nonce s cookie kontroluje volající.
     */
    public static function over(string $token, string $secret): ?OvereneSso
    {
        if ($secret === '') {
            return null;
        }
        if (! str_contains($token, '.')) {
            return null;
        }

        [$payloadKodovany, $podpisKodovany] = explode('.', $token, 2);
        $payload = self::base64UrlDekoduj($payloadKodovany);
        $podpis = self::base64UrlDekoduj($podpisKodovany);
        if ($payload === null || $podpis === null) {
            return null;
        }

        $ocekavanyPodpis = hash_hmac('sha256', $payload, $secret, true);
        if (! hash_equals($ocekavanyPodpis, $podpis)) {
            return null;
        }

        $casti = explode(self::ODDELOVAC_PAYLOADU, $payload);
        if (count($casti) !== 3) {
            return null;
        }
        [$email, $expiry, $nonce] = $casti;

        if (! ctype_digit($expiry) || (int) $expiry < time()) {
            return null;
        }
        if ($email === '' || $nonce === '') {
            return null;
        }

        return new OvereneSso($email, $nonce);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDekoduj(string $data): ?string
    {
        $dekodovano = base64_decode(strtr($data, '-_', '+/'), true);

        return $dekodovano === false ? null : $dekodovano;
    }
}
