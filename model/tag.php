<?php

use Gamecon\KategorieTagu;

/**
 * Tag aktivity
 *
 * For Doctrine entity equivalent @see \App\Entity\Tag
 *
 * @method static Tag|null zId($id, bool $zCache = false)
 * @method static Tag[] zVsech(bool $zCache = false)
 */
class Tag extends DbObject
{

    public const MALOVANI = 12445; // Malování
    public const UNIKOVKA = 12444; // Únikovka

    protected static $tabulka = 'sjednocene_tagy';
    protected static $pk      = 'id';

    protected static function dotaz($where)
    {
        $where = preg_replace('~\bid\b~', 'sjednocene_tagy.id', $where);

        return 'SELECT sjednocene_tagy.* FROM sjednocene_tagy '
            . 'JOIN kategorie_sjednocenych_tagu ON kategorie_sjednocenych_tagu.id = sjednocene_tagy.id_kategorie_tagu '
            . $where
            . ' ORDER BY kategorie_sjednocenych_tagu.poradi, sjednocene_tagy.nazev';
    }

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

    public function katregorieTagu(): KategorieTagu
    {
        return KategorieTagu::zId($this->idKategorieTagu());
    }
}
