<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniStavySqlStruktura as Sql;

/**
 * For Doctrine entity equivalent @see \App\Entity\ActivityRegistrationState
 *
 * @method static AkcePrihlaseniStavy|null zId($id, bool $zCache = false)
 */
class AkcePrihlaseniStavy extends \DbObject
{
    public const PRIHLASEN_ID           = 0;
    public const DORAZIL_ID             = 1;
    public const DORAZIL_NAHRADNIK_ID   = 2;
    public const NEDORAZIL_ID           = 3;
    public const POZDE_ZRUSIL_ID        = 4;
    public const NAHRADNIK_WATCHLIST_ID = 5;

    protected static $tabulka = Sql::AKCE_PRIHLASENI_STAVY_TABULKA;
    protected static $pk      = Sql::ID_STAVU_PRIHLASENI;

    public function nazev(): string
    {
        return $this->r[Sql::NAZEV];
    }

    public function platbaProcent(): int
    {
        return (int)$this->r[Sql::PLATBA_PROCENT];
    }
}
