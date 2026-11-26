<?php declare(strict_types=1);

namespace Gamecon;

use Gamecon\Aktivita\SqlStruktura\KategorieSjednocenychTaguSqlStruktura;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;

/**
 * For Doctrine entity equivalent @see \App\Entity\CategoryTag
 */
class KategorieTagu extends \DbObject
{
    /** štítky „Věk: 15+“ a podobně, viz Tag::getMinimalniVek() */
    public const OMEZENI_VEK = 50;

    protected static $tabulka = 'kategorie_sjednocenych_tagu';
    protected static $pk      = 'id';

    public function nazev(): string
    {
        return $this->r['nazev'];
    }

    /**
     * Název pro čtenáře v jazyce stránky (nebo v předaném), bez anglického názvu český.
     * {@see nazev()} zůstává český, je to klíč.
     */
    public function getNazevVJazyce(?Jazyk $jazyk = null): string
    {
        if (($jazyk ?? AktualniJazyk::jazyk()) === Jazyk::ANGLICTINA) {
            $anglicky = trim((string) ($this->r[KategorieSjednocenychTaguSqlStruktura::NAZEV_EN] ?? ''));
            if ($anglicky !== '') {
                return $anglicky;
            }
        }

        return $this->nazev();
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
