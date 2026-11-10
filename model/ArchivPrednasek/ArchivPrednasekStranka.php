<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

use Gamecon\Aktivita\TypAktivity;

/**
 * Address of the public archive: a page under the line of lectures (/prednasky/archiv), served by the module
 * web/moduly/archivprednasek.php through a branch of the router in web/index.php.
 */
final class ArchivPrednasekStranka
{
    public const string PODSTRANKA = 'archiv';
    public const string MODUL = 'archivprednasek';

    public static function cesta(TypAktivity $liniePrednasek): string
    {
        return $liniePrednasek->url() . '/' . self::PODSTRANKA;
    }

    /**
     * @return string|null null when the line of lectures does not exist
     */
    public static function cestaZDatabaze(): ?string
    {
        $liniePrednasek = TypAktivity::zId(TypAktivity::PREDNASKA);

        return $liniePrednasek
            ? self::cesta($liniePrednasek)
            : null;
    }
}
