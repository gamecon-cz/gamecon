<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Odkaz, který notifikace po kliknutí otevře: jen cesta na webu GameConu (`program/muj`), nikdy celá adresa.
 * Notifikace vypadá jako zpráva od GameConu, a tak nesmí vést jinam, ani spustit skript (`javascript:`).
 * Totéž znovu kontroluje service worker při kliknutí (web/sw.js).
 */
final class OdkazNotifikace
{
    public const int MAX_DELKA = 500;

    /**
     * Znaky cesty, dotazu a kotvy adresy. Dvojtečka, zpětné lomítko, mezera ani řídicí znak mezi nimi nejsou.
     */
    private const string VZOR = '~^[A-Za-z0-9\-._\~!$&\'()*+,;=@%/?#]*$~D';

    /**
     * @return string cesta bez úvodního lomítka, prázdná pro úvodní stránku
     *
     * @throws ChybaPushNotifikace
     */
    public static function normalizuj(?string $odkaz): string
    {
        $odkaz = trim((string) $odkaz);
        if ($odkaz === '') {
            return '';
        }
        // `//jinde.cz` je adresa jiného webu, `..` vede z webu ven (lokálně do adminu na stejné doméně)
        if (strlen($odkaz) > self::MAX_DELKA
            || str_starts_with($odkaz, '//')
            || preg_match(self::VZOR, $odkaz) !== 1
            || preg_match('~(^|/)\.\.(/|$|[?#])~', $odkaz) === 1
            // zakódovaná tečka, lomítko a zpětné lomítko: prohlížeč `%2e%2e` bere jako `..`
            || preg_match('~%(2e|2f|5c)~i', $odkaz) === 1
        ) {
            throw new ChybaPushNotifikace('Odkaz musí být cesta na webu GameConu, třeba „program/muj“, ne celá adresa.');
        }

        return ltrim($odkaz, '/');
    }

    /**
     * Celá adresa na webu tohoto prostředí.
     *
     * @param string $odkaz cesta z {@see self::normalizuj()}
     */
    public static function getUrl(string $urlWebu, string $odkaz): string
    {
        return rtrim($urlWebu, '/') . '/' . $odkaz;
    }
}
