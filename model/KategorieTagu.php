<?php declare(strict_types=1);

namespace Gamecon;

class KategorieTagu extends \DbObject
{

    protected static $tabulka = 'kategorie_sjednocenych_tagu';
    protected static $pk      = 'id';

    public function nazev(): string
    {
        return $this->r['nazev'];
    }

    public function poradi(): int
    {
        return (int)$this->r['poradi'];
    }

    public function idHlavniKategorie(): ?int
    {
        return $this->r['id_hlavni_kategorie']
            ? (int)$this->r['id_hlavni_kategorie']
            : null;
    }

}
