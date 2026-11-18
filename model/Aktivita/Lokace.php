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

    /**
     * Pořadí místností ze stránky Místnosti, při shodě podle ID. Podle něj se řadí místnosti aktivity
     * a vybírá automatická hlavní místnost (@see HlavniLokace), v SQL `ORDER BY lokace.poradi, lokace.id_lokace`.
     */
    public static function porovnejPodlePoradi(
        Lokace $a,
        Lokace $b,
    ): int {
        return [$a->poradi(), $a->id()] <=> [$b->poradi(), $b->id()];
    }

    public function __toString()
    {
        return $this->rozsirenyNazev();
    }

    public function rozsirenyNazev(): string
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

    public function apiLokace() {
        $res = [
            "id" => $this->id(),
            "poradi" => $this->poradi(),
            "nazev" => $this->rozsirenyNazev(),
        ];
        return $res;
    }
}
