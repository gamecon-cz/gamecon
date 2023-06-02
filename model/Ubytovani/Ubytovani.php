<?php

declare(strict_types=1);

namespace Gamecon\Ubytovani;

use Gamecon\Ubytovani\SqlStruktura\UbytovaniSqlStruktura as Sql;

class Ubytovani extends \DbObject
{
    protected static $tabulka = Sql::UBYTOVANI_TABULKA;
    protected static $pk      = Sql::ID_UZIVATELE;

}
