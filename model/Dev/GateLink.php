<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Podepíše „gate" token a připojí ho jako `?gate=` k URL preview / archivního
 * webu, aby proklik z adminu prošel přes Caddy bránu bez basic-auth dialogu.
 *
 * Caddy bránu chrání basic auth; před ní ale sedí `gate-validator` (viz ansible
 * repo, role `gate_validator`), který přijme podepsaný expirující token, vymění
 * ho za session cookie a pustí dál. Token NENÍ heslo — je to HMAC podpis času
 * expirace, takže leaknutý odkaz umře po {@see self::TTL_SEKUND} a nejde ručně
 * prodloužit.
 *
 * Formát tokenu (musí přesně odpovídat ověření v gate-validatoru):
 *
 *     gate = base64url(expiry_unix) "." base64url(HMAC_SHA256(expiry_unix, secret))
 *
 * `expiry_unix` jsou ASCII číslice unixového času; HMAC se počítá právě nad
 * těmito bajty (ne nad binárním integerem). base64url = RFC 4648 §5 bez `=`.
 *
 * Když secret není nastavený (lokální vývoj), vrací URL beze změny — odkaz pak
 * vede na čistou URL a uživatel projde přes basic-auth dialog jako dřív.
 */
final class GateLink
{
    /**
     * Platnost odkazu. Dost dlouho, aby běžné „otevři a proklikej se" sezení
     * nevypršelo; dost krátko, aby leaknutý odkaz (Slack, screenshot, sync
     * historie) byl do druhého dne mrtvý. Změna TTL je čistě na straně podpisu
     * — validator kontroluje jen `expiry > now`.
     */
    public const TTL_SEKUND = 24 * 3600;

    public static function podepis(string $url, string $secret, ?int $ted = null): string
    {
        if ($secret === '') {
            return $url;
        }

        $expiry = (string) (($ted ?? time()) + self::TTL_SEKUND);
        $podpis = hash_hmac('sha256', $expiry, $secret, true);
        $token = self::base64Url($expiry) . '.' . self::base64Url($podpis);
        $oddelovac = str_contains($url, '?') ? '&' : '?';

        return $url . $oddelovac . 'gate=' . $token;
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
