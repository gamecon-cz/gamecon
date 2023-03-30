<?php

declare(strict_types=1);

namespace Gamecon\Web;

class Urls
{
    public static function urlAdminAktivity(): string {
        return URL_ADMIN . '/' . basename(__DIR__ . '/../../admin/scripts/modules/aktivity/aktivity.php', '.php');
    }

    /**
     * Tip: bez ID aktivity dostaneme vzor URL pro detail aktivity
     */
    public static function urlAdminDetailAktivity(?int $idAktivity): string {
        $urlAdminDetailAktivity = basename(__DIR__ . '/../../admin/scripts/modules/aktivity/upravy.php', '.php')
            . '?aktivitaId=' . (string)$idAktivity;
        return self::urlAdminAktivity() . '/' . $urlAdminDetailAktivity;
    }
}
