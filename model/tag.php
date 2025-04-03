<?php

/**
 * Tag aktivity
 * @method static Tag|null zId($id, bool $zCache = false)
 * @method static Tag[] zVsech(bool $zCache = false)
 */
class Tag extends DbObject
{

    protected static $tabulka = 'sjednocene_tagy';
    protected static $pk      = 'id';

    public static function zNazvu(string $nazev): ?Tag
    {
        return static::zWhereRadek('sjednocene_tagy.nazev = ' . dbQv($nazev));
    }

    public function id(): int
    {
        return (int)parent::id();
    }

    public function nazev(): string
    {
        return $this->r['nazev'];
    }

    public function poznamka(): string
    {
        return $this->r['poznamka'];
    }

    public function idKategorieTagu(): int
    {
        return (int)$this->r['id_kategorie_tagu'];
    }

    public function katregorieTagu(): \Gamecon\KategorieTagu
    {
        return \Gamecon\KategorieTagu::zid($this->idKategorieTagu());
    }
}
