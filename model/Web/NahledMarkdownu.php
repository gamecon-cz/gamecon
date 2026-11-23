<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Náhled textu stránky nebo novinky v editoru se vykresluje na stránce administrace, ne na webu.
 */
final class NahledMarkdownu
{
    /**
     * Náhled, který editor průběžně načítá při psaní. Bez cache (@see \markdown()): každá rozepsaná verze textu by v ní
     * zůstala.
     */
    public static function zTextu(string $text): string
    {
        return self::obrazkyNaWebu(markdownNoCache($text));
    }

    /**
     * Administrace má `<base>` na svůj adresář, takže by se obrázek `soubory/…` z textu v náhledu hledal tam, ne na webu.
     */
    public static function obrazkyNaWebu(string $html): string
    {
        return (string) preg_replace_callback(
            '~(<img\b[^>]*\ssrc=["\']?)soubory/~i',
            static fn (array $shoda): string => $shoda[1] . URL_WEBU . '/soubory/',
            $html,
        );
    }
}
