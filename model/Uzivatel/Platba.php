<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use DbObject;
use Gamecon\Uzivatel\PlatbySqlStruktura as Sql;

class Platba extends DbObject
{
    protected static $tabulka = Sql::PLATBY_TABULKA;
    protected static $pk = Sql::ID;
}
