<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\SqlStruktura;

class UpominkaDluznikaLogSqlStruktura
{
    public const UPOMINKA_DLUZNIKA_LOG_TABULKA = 'upominka_dluznika_log';

    public const ID_LOG = 'id_log';
    public const ID_UZIVATELE = 'id_uzivatele';
    public const TYP_UPOMINKY = 'typ_upominky';
    public const DLUH = 'dluh';
    public const ROCNIK = 'rocnik';
    public const ODESLAL = 'odeslal';
    public const KDY = 'kdy';
}
