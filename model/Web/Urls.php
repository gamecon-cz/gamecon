<?php

declare(strict_types=1);

namespace Gamecon\Web;

class Urls
{
    public static function urlAdminAktivity(): string
    {
        return URL_ADMIN . '/' . basename(__DIR__ . '/../../admin/scripts/modules/aktivity/aktivity.php', '.php');
    }

    /**
     * Tip: bez ID aktivity dostaneme vzor URL pro detail aktivity
     */
    public static function urlAdminDetailAktivity(?int $idAktivity): string
    {
        $urlAdminDetailAktivity = basename(__DIR__ . '/../../admin/scripts/modules/aktivity/upravy.php', '.php')
            . '?aktivitaId=' . (string) $idAktivity;

        return self::urlAdminAktivity() . '/' . $urlAdminDetailAktivity;
    }

    /**
     * The request path (REQUEST_URI) as a link that cannot leave this site: it always starts with a single `/`.
     * From //other.site/x (also with `\`, spaces or control characters, which browsers read the same way) the link
     * would point to another site, from http://other.site/x (a request in absolute form) as well.
     */
    public static function getCestaNaTomtoWebu(string $requestUri): string
    {
        return '/' . ltrim($requestUri, "\x00..\x20/\\");
    }
}
