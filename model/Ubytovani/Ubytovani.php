<?php

declare(strict_types=1);

namespace Gamecon\Ubytovani;

use App\Structure\AccommodationSqlStructure;
use Gamecon\Ubytovani\SqlStruktura\UbytovaniSqlStruktura as Sql;

/**
 * Doctrine @see Accommodation
 * Legacy SQL structure @see UbytovaniSqlStruktura
 * Doctrine SQL structure @see AccommodationSqlStructure
 */
class Ubytovani extends \DbObject
{
    protected static $tabulka = Sql::UBYTOVANI_TABULKA;
    protected static $pk      = Sql::ID_UZIVATELE;

}
