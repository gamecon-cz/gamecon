<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Text stránky nebo novinky a adresář v `soubory/obsah`, kam se ukládají obrázky a soubory nahrané k němu.
 * Obrázky i soubory pod editorem jdou do stejného adresáře.
 */
final class KontextTextu
{
    public function __construct(
        public readonly string $text,
        public readonly string $adresarProNove,
        public readonly string $kCemu,
    ) {
    }

    public static function proStranku(\Stranka $stranka): self
    {
        $segmenty = array_filter(
            array_map('slugify', explode('/', (string) $stranka->url())),
            static fn (string $segment): bool => $segment !== '',
        );

        return new self(
            (string) ($stranka->raw()['obsah'] ?? ''),
            'stranky/' . ($segmenty !== [] ? implode('/', $segmenty) : 'stranka-' . $stranka->id()),
            'k této stránce',
        );
    }

    public static function proNovinku(\Novinka $novinka): self
    {
        $jeBlog = (int) $novinka->typ() === \Novinka::BLOG;
        $url = slugify((string) $novinka->url());

        return new self(
            (string) ($novinka->raw()['text'] ?? ''),
            ($jeBlog ? 'blog/' : 'novinky/') . ($url !== '' ? $url : 'novinka-' . $novinka->id()),
            $jeBlog ? 'k tomuto příspěvku' : 'k této novince',
        );
    }
}
