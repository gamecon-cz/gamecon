<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Obsluha `?gcsso=` z URL pro admin i veřejný web: přihlásí podle tokenu
 * ({@see ArchivSsoPrihlaseni}) a přesměruje na tutéž URL bez něj.
 */
final class SsoPrihlaseniZUrl
{
    public static function zpracuj(?\Uzivatel $prihlaseny): void
    {
        $token = get('gcsso');
        if ($token === null) {
            return;
        }

        $cil = self::prihlasPodleTokenu(
            $token,
            self::getOverovaciKlic(
                defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '',
                defined('GAMECON_SSO_SECRET') ? GAMECON_SSO_SECRET : '',
                jsmeNaPreview(),
            ),
            SsoParovaciCookie::precti(),
            $prihlaseny,
            getCurrentUrlWithQuery(['gcsso' => null]),
            URL_WEBU,
        );
        if ($cil !== null) {
            back($cil);
        }
    }

    /**
     * Ostrá, beta a lokál ověřovací klíč nemají: token tam nikoho nepřihlásí, tak se požadavek nechá být.
     *
     * @return string|null kam přesměrovat, null = nic se nezpracovalo
     */
    public static function prihlasPodleTokenu(
        mixed $token,
        string $overovaciKlic,
        ?string $nonceZCookie,
        ?\Uzivatel $prihlaseny,
        string $urlBezTokenu,
        string $nouzovaUrl,
    ): ?string {
        if (! self::isTokenZUrl($token) || $overovaciKlic === '') {
            return null;
        }

        (new ArchivSsoPrihlaseni($overovaciKlic))->prihlas((string) $token, $nonceZCookie, $prihlaseny);

        return self::getBezpecnyCil($urlBezTokenu, $nouzovaUrl);
    }

    /**
     * Archiv ověřuje klíčem svého ročníku, preview masterem (podepisuje i ověřuje samo sebe).
     * Ostrá a beta master mají, ale ověřovat jím nesmí: token podepsaný masterem by tam přihlásil kohokoli.
     */
    public static function getOverovaciKlic(string $klicRocniku, string $master, bool $jsmeNaPreview): string
    {
        if ($klicRocniku !== '') {
            return $klicRocniku;
        }

        return $jsmeNaPreview
            ? $master
            : '';
    }

    /**
     * Veřejný web přijme cokoli, i `?gcsso[]=x`, což je pole, ne token.
     */
    public static function isTokenZUrl(mixed $hodnota): bool
    {
        return is_string($hodnota) && $hodnota !== '';
    }

    /**
     * Cesta na tomtéž hostu, jinak nouzová URL: z požadavku na `///host` vychází prázdný cíl a `back()`
     * by cíl hádal podle Refereru.
     */
    public static function getBezpecnyCil(string $cil, string $nouzovaUrl): string
    {
        $jeCestaNaTomtoHostu = str_starts_with($cil, '/')
            && ! str_starts_with($cil, '//')
            && ! str_starts_with($cil, '/\\');

        return $jeCestaNaTomtoHostu
            ? $cil
            : $nouzovaUrl;
    }
}
