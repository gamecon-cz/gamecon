<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Kam se má admin po přihlášení vrátit (`?returnTo=`): jen na některé preview (kterékoli `*.preview.gamecon.cz`,
 * i webmail), ne nutně na to, odkud brána poslala. Cokoli jiného se ignoruje, jinak by admin přesměroval kamkoli.
 */
final class NavratNaPreview
{
    public const string PARAMETR = 'returnTo';

    private const int NEJDELSI_URL = 2000;

    // https a jediný štítek před preview.gamecon.cz, bez portu a uživatele; za hostem jen / ? nebo #, dál jen
    // viditelné znaky ASCII bez zpětného lomítka, takže v URL není mezera ani nový řádek.
    // Brána preview v ansible (enter.go, returnTargetPattern) má stejný vzor a limit.
    private const string VZOR = '~^https://[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.preview\.gamecon\.cz(?:[/?#][\x21-\x5B\x5D-\x7E]*)?$~D';

    public static function getBezpecnaUrl(mixed $hodnota): ?string
    {
        if (! is_string($hodnota) || strlen($hodnota) > self::NEJDELSI_URL) {
            return null;
        }

        return preg_match(self::VZOR, $hodnota) === 1
            ? $hodnota
            : null;
    }

    /**
     * Kam poslat už přihlášeného, kdo otevřel odkaz z brány: jen při GET, požadavek jiného druhu se nechá být.
     */
    public static function getCilProPrihlaseneho(bool $jePrihlaseny, string $metoda, mixed $hodnota): ?string
    {
        return $jePrihlaseny && $metoda === 'GET'
            ? self::getBezpecnaUrl($hodnota)
            : null;
    }
}
