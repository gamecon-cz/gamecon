<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Odkaz z editoru na veřejnou stránku. Otevírá se v novém okně, aby se neztratil rozepsaný text, a ukáže poslední
 * uloženou verzi.
 */
final class OdkazNaWeb
{
    public static function html(string $url, string $popis): string
    {
        return '<p class="odkaz-na-web"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">'
            . '<i class="fa fa-external-link" aria-hidden="true"></i> ' . htmlspecialchars($popis) . '</a> '
            . '<span class="tip">(poslední uložená verze)</span></p>';
    }
}
