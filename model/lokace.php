<?php

/**
 * @method static Lokace|null zId($id, bool $zCache = false)
 * @method static Lokace[] zVsech(bool $zCache = false)
 */
class Lokace extends DbObject
{

    protected static $tabulka = 'akce_lokace';
    protected static $pk      = 'id_lokace';

    public function __toString()
    {
        $casti = array_filter([$this->r['nazev'], $this->r['dvere']]);
        return implode(', ', $casti);
    }

    public function nazev()
    {
        return $this->r['nazev'];
    }

    public function dvere()
    {
        return $this->r['dvere'];
    }

    public function poznamka()
    {
        return $this->r['poznamka'];
    }

    public function poradi(): int
    {
        return (int)$this->r['poradi'];
    }

    public function rok(): int
    {
        return (int)$this->r['rok'];
    }

    public function id(): int
    {
        return (int)parent::id();
    }
}
