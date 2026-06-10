<?php

namespace Gamecon\Dev;

/**
 * Magické přihlášení napříč subdoménami: hlavní admin (admin.gamecon.cz) podepíše
 * token vázaný na `id_uzivatele` přihlášeného uživatele, archiv (NNNN.gamecon.cz)
 * ho ověří a uživatele podle ID přihlásí — bez zadávání hesla.
 *
 * Identitu nese ČÍSELNÉ ID, ne e-mail: ID je napříč ostrou i zmrazenými archivními
 * snapshoty stabilní, kdežto e-mail je proměnný a může se časem přiřadit jinému
 * člověku (→ přihlášení do cizího účtu).
 *
 * Token nese jen IDENTITU (ID) a NESMÍ sám o sobě nikoho přihlásit: podepsaný
 * odkaz se může sdílet jako každý jiný. Proto je k němu při ověření vyžadován ještě
 * spárovaný „nonce", který zná jen prohlížeč, co na odkaz klikl (cookie `gc_sso_pair`
 * scope `.gamecon.cz`). Token commituje na konkrétní nonce; archiv přihlásí jen když
 * se nonce z tokenu shoduje s nonce z cookie. Sdílená URL nonce v cizím prohlížeči
 * nemá → nepřihlásí.
 *
 * Podpis stojí na klíči ODVOZENÉM PRO DANÝ ROČNÍK z master tajemství
 * (`hash_hmac('sha256', (string) $rocnik, GAMECON_SSO_SECRET)`); master žije jen na
 * ostré, archiv dostane jen svůj odvozený klíč přes `-e GAMECON_SSO_KEY`. NE
 * `SECRET_CRYPTO_KEY` — ten šifruje osobní data a do zmrazeného archivu nepatří.
 *
 * Formát tokenu (`?gcsso=`):
 *
 *     gcsso = base64url(id "|" expiry "|" nonce) "." base64url(HMAC_SHA256(payload, secret))
 *
 * `id` i `expiry` jsou ASCII číslice; HMAC se počítá nad celým payloadem
 * (id|expiry|nonce). base64url = RFC 4648 §5 bez `=`.
 *
 * Když secret není nastavený (lokální vývoj), {@see self::podepis} vrátí prázdný
 * řetězec a volající `?gcsso=` nepřipojí — feature je prostě vypnutá.
 *
 * PHP 5.6-kompatibilní varianta (archivní ročníky 2015-2021 běží na PHP 5.6/7.3):
 * žádné scalar typehinty, návratové typy, `??`, `str_contains`, list-destructuring
 * v hranatých závorkách ani visibility u konstant.
 */
final class CrossSiteLogin
{
    /**
     * Krátká platnost: token je jednorázový proklik z administračního rozcestníku,
     * ne dlouhé sezení. Stačí na přesměrování přes bránu a přihlášení v archivu;
     * leaknutý odkaz je tak po pár minutách mrtvý.
     */
    const TTL_SEKUND = 300;

    const ODDELOVAC_PAYLOADU = '|';

    /**
     * Vrátí hodnotu pro `?gcsso=` (samotný token, bez prefixu) podepsanou pro dané
     * `id_uzivatele` a nonce. Prázdný řetězec, pokud secret není nastavený — volající
     * pak `?gcsso=` nepřipojuje.
     *
     * @param int         $idUzivatele
     * @param string      $nonce
     * @param string      $secret
     * @param int|null    $ted
     * @return string
     */
    public static function podepis($idUzivatele, $nonce, $secret, $ted = null)
    {
        if ($secret === '') {
            return '';
        }

        $ted = $ted === null ? time() : $ted;
        $expiry = (string) ($ted + self::TTL_SEKUND);
        $payload = ((int) $idUzivatele) . self::ODDELOVAC_PAYLOADU . $expiry . self::ODDELOVAC_PAYLOADU . $nonce;
        $podpis = hash_hmac('sha256', $payload, $secret, true);

        return self::base64Url($payload) . '.' . self::base64Url($podpis);
    }

    /**
     * Ověří token: správný podpis a nevypršelá platnost. Vrátí ověřené `id_uzivatele`
     * + nonce (objekt {@see OvereneSso}), nebo null při jakékoli nesrovnalosti (špatný
     * formát, neplatný podpis, vypršení). Shodu nonce s cookie kontroluje volající.
     *
     * @param string $token
     * @param string $secret
     * @return OvereneSso|null
     */
    public static function over($token, $secret)
    {
        if ($secret === '') {
            return null;
        }
        if (strpos($token, '.') === false) {
            return null;
        }

        $casti = explode('.', $token, 2);
        $payloadKodovany = $casti[0];
        $podpisKodovany = $casti[1];
        $payload = self::base64UrlDekoduj($payloadKodovany);
        $podpis = self::base64UrlDekoduj($podpisKodovany);
        if ($payload === null || $podpis === null) {
            return null;
        }

        $ocekavanyPodpis = hash_hmac('sha256', $payload, $secret, true);
        if (! hash_equals($ocekavanyPodpis, $podpis)) {
            return null;
        }

        $dily = explode(self::ODDELOVAC_PAYLOADU, $payload);
        if (count($dily) !== 3) {
            return null;
        }
        $idUzivatele = $dily[0];
        $expiry = $dily[1];
        $nonce = $dily[2];

        if (! ctype_digit($expiry) || (int) $expiry < time()) {
            return null;
        }
        if (! ctype_digit($idUzivatele) || (int) $idUzivatele <= 0 || $nonce === '') {
            return null;
        }

        return new OvereneSso((int) $idUzivatele, $nonce);
    }

    private static function base64Url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDekoduj($data)
    {
        $dekodovano = base64_decode(strtr($data, '-_', '+/'), true);

        return $dekodovano === false ? null : $dekodovano;
    }
}
