<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * The only place that decides what a symbol is in an e-mail text; validation and substitution both rely on it.
 * `%jmeno%` is a variable, `%GC_BEZI_OD%` a system value, `%%` a literal percent sign (a bare one also works before a space).
 *
 * @see EmailovaSablonaValidator
 * @see EmailovaSablonaVykreslovac
 */
class RozkladSymbolu
{
    /**
     * @return list<Token>
     */
    public static function rozloz(string $text): array
    {
        $tokeny           = [];
        $rozpracovanyText = '';
        $delka            = strlen($text);
        $pozice           = 0;

        $ulozText = static function () use (&$tokeny, &$rozpracovanyText): void {
            if ($rozpracovanyText !== '') {
                $tokeny[]         = new Token(TypTokenuEnum::TEXT, $rozpracovanyText);
                $rozpracovanyText = '';
            }
        };

        while ($pozice < $delka) {
            if ($text[$pozice] !== '%') {
                $dalsiProcento    = strpos($text, '%', $pozice);
                $konec            = $dalsiProcento === false
                    ? $delka
                    : $dalsiProcento;
                $rozpracovanyText .= substr($text, $pozice, $konec - $pozice);
                $pozice           = $konec;
                continue;
            }

            $zaProcentem = substr($text, $pozice + 1);

            if (str_starts_with($zaProcentem, '%')) {
                $rozpracovanyText .= '%';
                $pozice           += 2;
                continue;
            }

            if (preg_match('~^([A-Za-z][A-Za-z0-9_]*)(%?)~', $zaProcentem, $shoda)) {
                $ulozText();
                $tokeny[] = new Token(
                    $shoda[2] === '%'
                        ? TypTokenuEnum::SYMBOL
                        : TypTokenuEnum::NEUZAVRENY_SYMBOL,
                    $shoda[1],
                );
                $pozice   += 1 + strlen($shoda[0]);
                continue;
            }

            if (preg_match('~^([0-9]+)~', $zaProcentem, $shoda)) {
                $ulozText();
                $tokeny[] = new Token(TypTokenuEnum::CISLICNY_SYMBOL, $shoda[1]);
                $pozice   += 1 + strlen($shoda[0]);
                continue;
            }

            $rozpracovanyText .= '%';
            ++$pozice;
        }
        $ulozText();

        return $tokeny;
    }

    /**
     * Blank also covers non-breaking spaces and other Unicode whitespace, as `String.trim()` in the editor does
     * (apart from U+0085). Text that cannot be matched, such as invalid UTF-8, counts as blank.
     */
    public static function jePrazdny(string $text): bool
    {
        return preg_match('~[^\s\x{FEFF}]~u', $text) !== 1;
    }

    public static function jePromenna(string $nazev): bool
    {
        return preg_match('~^[a-z][a-z0-9_]*$~', $nazev) === 1;
    }

    public static function jeSystemovaHodnota(string $nazev): bool
    {
        return preg_match('~^[A-Z][A-Z0-9_]*$~', $nazev) === 1;
    }
}
