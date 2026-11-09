<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Co se převádí do AVIF a jak se k tomu přijde, @see PrevodObrazkuNaAvif
 */
enum OblastPrevoduObrazkuEnum
{
    /** Obrázky vložené do textů webu (`soubory/obsah`), kterým se přepíšou odkazy */
    case TEXTY;
    /** Fotky uživatelů (`soubory/systemove/fotky/<id>.<přípona>`), které web najde podle id, žádný text na ně neodkazuje */
    case FOTKY_UZIVATELU;

    /**
     * @return list<string> přípony souborů, které se převádějí
     */
    public function getPripony(): array
    {
        return match ($this) {
            self::TEXTY            => ['jpg', 'jpeg', 'png'],
            self::FOTKY_UZIVATELU  => ['jpg', 'jpeg', 'png', 'webp'],
        };
    }

    /**
     * @return array<string, string> MIME typ zdroje => kodek, kterým ho ImageMagick čte
     */
    public function getKodeky(): array
    {
        return match ($this) {
            self::TEXTY            => [
                NahravaniObrazku::JPG => 'jpeg',
                NahravaniObrazku::PNG => 'png',
            ],
            self::FOTKY_UZIVATELU  => [
                NahravaniObrazku::JPG  => 'jpeg',
                NahravaniObrazku::PNG  => 'png',
                NahravaniObrazku::WEBP => 'webp',
            ],
        };
    }

    public function getPopisZdroju(): string
    {
        return match ($this) {
            self::TEXTY            => 'JPEG ani PNG',
            self::FOTKY_UZIVATELU  => 'JPEG, PNG ani WebP',
        };
    }

    /**
     * Jak se cesta k souboru začíná v textech a v adrese, vůči kořenu webu.
     */
    public function getPredponaVuciWebu(): string
    {
        return match ($this) {
            self::TEXTY            => 'soubory/obsah/',
            self::FOTKY_UZIVATELU  => 'soubory/systemove/fotky/',
        };
    }

    public function isZTextu(): bool
    {
        return $this === self::TEXTY;
    }
}
