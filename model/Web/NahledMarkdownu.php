<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Náhled textu stránky nebo novinky v editoru se vykresluje na stránce administrace, ne na webu.
 */
final class NahledMarkdownu
{
    /**
     * Administrace má `<base>` na svůj adresář, takže by se obrázek `soubory/…` z textu v náhledu hledal tam, ne na webu.
     *
     * @see dbff-markdown-direct.xtpl stejná úprava živého náhledu
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
