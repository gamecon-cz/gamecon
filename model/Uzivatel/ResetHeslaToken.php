<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * Podepíše a ověří „magic link" token pro obnovu zapomenutého hesla.
 *
 * Token je bezestavový (žádná DB tabulka) — stejný princip jako
 * {@see \Gamecon\Dev\GateLink}: HMAC-SHA256 podpis přes payload, base64url
 * kódování. Payload nese ID uživatele, čas expirace a **otisk aktuálního
 * hashe hesla**.
 *
 * Otisk hesla dělá z odkazu jednorázový bez jakéhokoli stavu: jakmile si
 * uživatel heslo změní (ať už přes tento odkaz, nebo jinak), `heslo_md5` se
 * změní, otisk přestane sedět a starý odkaz je mrtvý. Tím se zároveň
 * zneplatní i případné starší, ještě nevyužité odkazy (nová žádost → nový
 * link, ale dokud se heslo nezmění, oba odkazy fungují — což je v pořádku,
 * vedou ke stejné akci).
 *
 * Formát tokenu:
 *
 *     payload "." base64url(HMAC_SHA256(payload, secret))
 *     payload = base64url(idUzivatele "|" expiry_unix "|" otiskHesla)
 *
 * base64url = RFC 4648 §5 bez `=` paddingu. HMAC se počítá nad ASCII bajty
 * payloadu (tj. nad už zakódovaným řetězcem).
 *
 * Secret je `APP_SECRET` (sdílený s ostatním Symfony kódem). Tahle třída ho
 * dostává jako parametr, aby zůstala čistě kryptografická a snadno
 * testovatelná bez DI/DB.
 */
final class ResetHeslaToken
{
    /**
     * Platnost odkazu. Krátká — odkaz na obnovu hesla se používá hned po
     * doručení mailu; po hodině je mrtvý, aby leaknutý odkaz neměl dlouhou
     * životnost.
     */
    public const TTL_SEKUND = 3600;

    public static function podepis(
        int $idUzivatele,
        string $hesloMd5,
        string $secret,
        ?int $ted = null,
    ): string {
        $expiry = ($ted ?? time()) + self::TTL_SEKUND;
        $payload = self::payload($idUzivatele, $expiry, $hesloMd5, $secret);

        return $payload . '.' . self::base64UrlEncode(self::hmac($payload, $secret));
    }

    /**
     * Ověří token proti aktuálnímu hashi hesla a vrátí ID uživatele, nebo
     * `null`, pokud je token neplatný / expirovaný / nesedí na aktuální heslo.
     */
    public static function over(
        string $token,
        string $hesloMd5,
        string $secret,
        ?int $ted = null,
    ): ?int {
        if (! str_contains($token, '.')) {
            return null;
        }
        [$payloadPart, $podpisPart] = explode('.', $token, 2);

        // Ověř podpis konstantně časově.
        $ocekavanyPodpis = self::hmac($payloadPart, $secret);
        $skutecnyPodpis = self::base64UrlDecode($podpisPart);
        if ($skutecnyPodpis === '' || ! hash_equals($ocekavanyPodpis, $skutecnyPodpis)) {
            return null;
        }

        $payload = self::base64UrlDecode($payloadPart);
        $casti = explode('|', $payload);
        if (count($casti) !== 3) {
            return null;
        }
        [$idStr, $expiryStr, $otisk] = $casti;

        // Expirace.
        $expiry = (int) $expiryStr;
        if (($ted ?? time()) >= $expiry) {
            return null;
        }

        // Vazba na aktuální heslo: otisk se musí shodovat s otiskem
        // současného hashe. Když si uživatel mezitím heslo změnil, neprojde.
        $ocekavanyOtisk = self::otiskHesla($hesloMd5, $secret);
        if (! hash_equals($ocekavanyOtisk, $otisk)) {
            return null;
        }

        return (int) $idStr;
    }

    private static function payload(int $idUzivatele, int $expiry, string $hesloMd5, string $secret): string
    {
        $raw = $idUzivatele . '|' . $expiry . '|' . self::otiskHesla($hesloMd5, $secret);

        return self::base64UrlEncode($raw);
    }

    private static function otiskHesla(string $hesloMd5, string $secret): string
    {
        // Plný HMAC hashe hesla (žádné zkracování — viz pravidla projektu).
        return hash_hmac('sha256', $hesloMd5, $secret);
    }

    private static function hmac(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret, true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false
            ? ''
            : $decoded;
    }
}
