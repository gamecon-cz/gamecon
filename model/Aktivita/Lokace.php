<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\LokaceSqlStruktura as Sql;

/**
 * @method static Lokace|null zId($id, bool $zCache = false)
 * @method static Lokace[] zVsech(bool $zCache = false)
 */
class Lokace extends \DbObject
{
    protected static $tabulka = Sql::LOKACE_TABULKA;
    protected static $pk      = Sql::ID_LOKACE;

    public function __toString()
    {
        $casti = array_filter([$this->r[Sql::NAZEV], $this->r[Sql::DVERE]]);

        return implode(', ', $casti);
    }

    public function nazev(): ?string
    {
        return $this->r[Sql::NAZEV];
    }

    public function dvere(): ?string
    {
        return $this->r[Sql::DVERE];
    }

    public function poznamka(): ?string
    {
        return $this->r[Sql::POZNAMKA];
    }

    public function poradi(): int
    {
        return (int)$this->r[Sql::PORADI];
    }

    public function rok(): int
    {
        return (int)$this->r[Sql::ROK];
    }

    public function id(): int
    {
        return (int)parent::id();
    }
}
