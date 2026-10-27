<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * Word diff of an original and a proofread text. A proofreading changes a comma here and a letter there,
 * so the text is compared by words, runs of whitespace and single punctuation marks.
 */
class RozdilTextu
{
    public const STEJNE = '=';
    public const SMAZANO = '-';
    public const PRIDANO = '+';

    /**
     * @return array<array{0: string, 1: string}> pieces as [kind, text], consecutive pieces of a kind joined
     *                                            and within a change the deleted piece before the added one
     */
    public static function porovnej(string $puvodni, string $novy): array
    {
        $a = self::rozdelNaSlova($puvodni);
        $b = self::rozdelNaSlova($novy);

        // a proofreading keeps most of the text, the common start and end need no table
        $zacatek = 0;
        while ($zacatek < count($a) && $zacatek < count($b) && $a[$zacatek] === $b[$zacatek]) {
            ++$zacatek;
        }
        $konec = 0;
        while ($konec < count($a) - $zacatek && $konec < count($b) - $zacatek
            && $a[count($a) - 1 - $konec] === $b[count($b) - 1 - $konec]
        ) {
            ++$konec;
        }

        $kusy = [];
        foreach (array_slice($a, 0, $zacatek) as $slovo) {
            $kusy[] = [self::STEJNE, $slovo];
        }
        array_push(
            $kusy,
            ...self::porovnejStredy(
                array_slice($a, $zacatek, count($a) - $zacatek - $konec),
                array_slice($b, $zacatek, count($b) - $zacatek - $konec),
            ),
        );
        foreach (array_slice($a, count($a) - $konec) as $slovo) {
            $kusy[] = [self::STEJNE, $slovo];
        }

        return self::spoj($kusy);
    }

    /**
     * Inline HTML with `<del>` and `<ins>`, meant for an element with `white-space: pre-wrap`.
     */
    public static function html(string $puvodni, string $novy): string
    {
        $html = '';
        foreach (self::porovnej($puvodni, $novy) as [$druh, $text]) {
            if ($druh === self::STEJNE) {
                $html .= htmlspecialchars($text, ENT_QUOTES);
                continue;
            }
            // a deleted or added space or line end would not be seen
            $viditelny = trim($text) === ''
                ? str_replace([' ', "\n"], ['·', "↵\n"], $text)
                : $text;
            $znacka = $druh === self::SMAZANO
                ? 'del'
                : 'ins';
            $html .= "<{$znacka}>" . htmlspecialchars($viditelny, ENT_QUOTES) . "</{$znacka}>";
        }

        return $html;
    }

    /**
     * Share of the original text, in characters, that the change deletes or adds, 0.0 to 1.0 and more.
     */
    public static function podilZmen(string $puvodni, string $novy): float
    {
        $zmeneno = 0;
        foreach (self::porovnej($puvodni, $novy) as [$druh, $text]) {
            if ($druh !== self::STEJNE) {
                $zmeneno += mb_strlen($text);
            }
        }

        return $zmeneno / max(1, mb_strlen($puvodni));
    }

    /**
     * @return array<string>
     */
    private static function rozdelNaSlova(string $text): array
    {
        return preg_split('~(\s+|[^\p{L}\p{N}\s])~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Longest common subsequence of words.
     *
     * @param array<string> $a
     * @param array<string> $b
     *
     * @return array<array{0: string, 1: string}>
     */
    private static function porovnejStredy(array $a, array $b): array
    {
        $delkaA = count($a);
        $delkaB = count($b);
        // $spolecne[$i][$j] = length of the common subsequence of $a from $i and $b from $j
        $spolecne = array_fill(0, $delkaA + 1, array_fill(0, $delkaB + 1, 0));
        for ($i = $delkaA - 1; $i >= 0; --$i) {
            for ($j = $delkaB - 1; $j >= 0; --$j) {
                $spolecne[$i][$j] = $a[$i] === $b[$j]
                    ? $spolecne[$i + 1][$j + 1] + 1
                    : max($spolecne[$i + 1][$j], $spolecne[$i][$j + 1]);
            }
        }

        $kusy = [];
        $i = 0;
        $j = 0;
        while ($i < $delkaA && $j < $delkaB) {
            if ($a[$i] === $b[$j]) {
                $kusy[] = [self::STEJNE, $a[$i]];
                ++$i;
                ++$j;
            } elseif ($spolecne[$i + 1][$j] >= $spolecne[$i][$j + 1]) {
                $kusy[] = [self::SMAZANO, $a[$i]];
                ++$i;
            } else {
                $kusy[] = [self::PRIDANO, $b[$j]];
                ++$j;
            }
        }
        for (; $i < $delkaA; ++$i) {
            $kusy[] = [self::SMAZANO, $a[$i]];
        }
        for (; $j < $delkaB; ++$j) {
            $kusy[] = [self::PRIDANO, $b[$j]];
        }

        return $kusy;
    }

    /**
     * @param array<array{0: string, 1: string}> $kusy
     *
     * @return array<array{0: string, 1: string}>
     */
    private static function spoj(array $kusy): array
    {
        $spojene = [];
        $zmena = [
            self::SMAZANO => '',
            self::PRIDANO => '',
        ];
        $stejnych = '';
        // the empty unchanged piece at the end writes out a change the text ends with
        foreach ([...$kusy, [self::STEJNE, '']] as [$druh, $text]) {
            if ($druh !== self::STEJNE) {
                if ($stejnych !== '') {
                    $spojene[] = [self::STEJNE, $stejnych];
                    $stejnych = '';
                }
                $zmena[$druh] .= $text;
                continue;
            }
            foreach ($zmena as $druhZmeny => $textZmeny) {
                if ($textZmeny !== '') {
                    $spojene[] = [$druhZmeny, $textZmeny];
                }
            }
            $zmena = [
                self::SMAZANO => '',
                self::PRIDANO => '',
            ];
            $stejnych .= $text;
        }
        if ($stejnych !== '') {
            $spojene[] = [self::STEJNE, $stejnych];
        }

        return $spojene;
    }
}
