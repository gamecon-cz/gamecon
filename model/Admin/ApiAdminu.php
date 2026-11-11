<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * Endpointy admin API (admin/scripts/api/*.php) jsou jen pro toho, kdo v adminu smí aspoň jednu stránku
 * (@see PristupDoAdminu): nepřihlášenému admin/index.php odpoví 401, přihlášenému bez přístupu do adminu (účastník bez
 * práv) 403, obojí dřív, než endpoint načte. Endpoint z @see BEZ_PRIHLASENI se načte vždy a o přístupu rozhoduje sám.
 */
final class ApiAdminu
{
    /**
     * Endpoint => proč ho smí volat i nepřihlášený. Další přidávej jen vědomě: endpoint dostane nepřihlášeného
     * a hlídat ho musí sám.
     */
    public const array BEZ_PRIHLASENI = [
        'preview-vstup'   => 'Volá ho brána preview, aby zjistila, jestli relace patří přihlášenému organizátorovi;'
            . ' nepřihlášenému musí odpovědět, že přihlášený není. Bez platného tokenu brány vrací 404.',
        'programManifest' => 'Vrací veřejná data programu, stejná jako web/api/programManifest. Volá ho stránka'
            . ' program-obecny, která jde v adminu otevřít i bez přihlášení.',
    ];

    public static function isBezPrihlaseni(string $endpoint): bool
    {
        return array_key_exists($endpoint, self::BEZ_PRIHLASENI);
    }

    /**
     * HTTP kód, kterým admin API požadavek odmítne, nebo null, když endpoint načte.
     *
     * @param bool $maPristupDoAdminu @see PristupDoAdminu::isPovolen(), admin/index.php ho zjišťuje jednou pro API
     *                                i zvláštní stránky
     */
    public static function getKodOdmitnuti(string $endpoint, bool $jePrihlasen, bool $maPristupDoAdminu): ?int
    {
        if (self::isBezPrihlaseni($endpoint)) {
            return null;
        }
        if (! $jePrihlasen) {
            return 401;
        }

        return $maPristupDoAdminu
            ? null
            : 403;
    }

    /**
     * Odmítnutí jako JSON `{"error": …}`, stejné od brány API i od endpointu, který odmítá sám.
     */
    public static function odmitni(int $kod): void
    {
        $chyba = match ($kod) {
            401     => '401 Unauthorized',
            403     => '403 Forbidden',
            default => (string) $kod,
        };
        http_response_code($kod);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $chyba,
        ], JSON_THROW_ON_ERROR);
    }
}
