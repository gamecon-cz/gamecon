<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubory z adresáře obsahu, na které odkazuje text stránky nebo novinky.
 */
final class OdkazyVTextu
{
    /**
     * Pozná `soubory/obsah/…` v Markdownu i HTML, relativně i s doménou, i zakódované pro URL.
     *
     * @param list<string> $pripony přípony bez tečky, velikost písmen se nerozlišuje
     * @return list<string> cesty vůči adresáři obsahu
     */
    public static function getCesty(string $text, array $pripony): array
    {
        preg_match_all(
            sprintf(
                '~soubory/obsah/+([^\s"\'()<>\[\]?#\\\\]+?\.(?:%s))(?=[\s"\'()<>\[\]?#]|$)~iu',
                implode('|', array_map(static fn (string $pripona): string => preg_quote($pripona, '~'), $pripony)),
            ),
            $text,
            $shody,
        );
        $odkazy = [];
        foreach ($shody[1] as $cesta) {
            // %5C se po dekódování mění na zpětné lomítko, které služba bere jako oddělovač adresářů
            $segmenty = array_values(array_filter(
                explode('/', str_replace('\\', '/', rawurldecode($cesta))),
                static fn (string $segment): bool => $segment !== '',
            ));
            // `..` ani skryté soubory nenabízíme, služba by je stejně odmítla
            foreach ($segmenty as $segment) {
                if (str_starts_with($segment, '.')) {
                    continue 2;
                }
            }
            $cesta = implode('/', $segmenty);
            $odkazy[$cesta] = $cesta;
        }

        return array_values($odkazy);
    }
}
