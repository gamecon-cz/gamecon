<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

/**
 * Warns the designer when a text would be hard to read on its background. It only warns, the designer decides.
 * Contrast as WCAG 2 defines it, 4.5:1 is the minimum for ordinary text (level AA).
 */
class KontrastBarevWebu
{
    public const float MINIMALNI_KONTRAST = 4.5;
    private const string BILA = '#ffffff';

    /**
     * @return list<string> one sentence for each pair of colours that is not readable enough
     */
    public static function varovani(BarvyWebu $barvy): array
    {
        $varovani = [];
        foreach (self::dvojice($barvy) as [$popredi, $pozadi, $popis]) {
            $kontrast = self::kontrast($popredi, $pozadi);
            if ($kontrast < self::MINIMALNI_KONTRAST) {
                $varovani[] = sprintf(
                    '%s má kontrast jen %s:1, pro čitelnost je potřeba aspoň %s:1.',
                    $popis,
                    number_format(floor($kontrast * 10) / 10, 1, ',', ''),
                    number_format(self::MINIMALNI_KONTRAST, 1, ',', ''),
                );
            }
        }

        return $varovani;
    }

    /**
     * @return float from 1 (the same colour) to 21 (black on white)
     */
    public static function kontrast(string $barva1, string $barva2): float
    {
        $jas1 = self::relativniJas($barva1);
        $jas2 = self::relativniJas($barva2);

        return (max($jas1, $jas2) + 0.05) / (min($jas1, $jas2) + 0.05);
    }

    /**
     * The pairs of text and background the modern look actually uses.
     *
     * @return list<array{0: string, 1: string, 2: string}> text, background, description
     */
    private static function dvojice(BarvyWebu $barvy): array
    {
        $hodnota = static fn (BarvaWebuEnum $barva): string => $barvy->getHodnota($barva);

        return [
            [self::BILA, $hodnota(BarvaWebuEnum::CERVENA), 'Bílý text na hlavní barvě (tlačítka, naléhavé oznámení)'],
            [self::BILA, $hodnota(BarvaWebuEnum::CERVENA_TMAVA), 'Bílý text na tmavší hlavní barvě (tlačítka po najetí myší)'],
            [self::BILA, $hodnota(BarvaWebuEnum::INKOUST), 'Bílý text na tmavé (hlavička, úvod titulky, patička)'],
            [$hodnota(BarvaWebuEnum::CERVENA), $hodnota(BarvaWebuEnum::PLOCHA), 'Hlavní barva jako text na pozadí stránky'],
            [$hodnota(BarvaWebuEnum::TEXT), $hodnota(BarvaWebuEnum::PLOCHA), 'Text na pozadí stránky'],
            [$hodnota(BarvaWebuEnum::TEXT), $hodnota(BarvaWebuEnum::KREMOVA), 'Text na světlém pozadí'],
            [$hodnota(BarvaWebuEnum::TEXT), $hodnota(BarvaWebuEnum::ORANZOVA), 'Text na doplňkové barvě (upozornění)'],
            [$hodnota(BarvaWebuEnum::TEXT_TLUMENY), $hodnota(BarvaWebuEnum::PLOCHA), 'Tlumený text na pozadí stránky'],
            [$hodnota(BarvaWebuEnum::TEXT_TLUMENY), $hodnota(BarvaWebuEnum::KREMOVA), 'Tlumený text na světlém pozadí'],
        ];
    }

    /**
     * @param string $barva '#rrggbb'
     */
    private static function relativniJas(string $barva): float
    {
        $slozky = array_map(
            static function (string $hex): float {
                $slozka = hexdec($hex) / 255;

                return $slozka <= 0.04045
                    ? $slozka / 12.92
                    : (($slozka + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($barva, '#'), 2),
        );

        return 0.2126 * $slozky[0] + 0.7152 * $slozky[1] + 0.0722 * $slozky[2];
    }
}
