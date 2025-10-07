<?php

namespace Gamecon\Uzivatel;

use DbObject;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura;

/**
 * For Doctrine entity equivalent @see \App\Entity\UserBadge
 *
 * @method static array<Medailonek> zVsech()
 */
class Medailonek extends DbObject
{
    protected static $tabulka = MedailonekSqlStruktura::MEDAILONKY_TABULKA;
    protected static $pk      = MedailonekSqlStruktura::ID_UZIVATELE;

    public function drd(): string
    {
        return markdownNoCache($this->r[MedailonekSqlStruktura::DRD]);
    }

    public function oSobe(): string
    {
        return markdownNoCache($this->r[MedailonekSqlStruktura::O_SOBE]);
    }

    public function idUzivatele(): int
    {
        return (int)$this->r[MedailonekSqlStruktura::ID_UZIVATELE];
    }

}
