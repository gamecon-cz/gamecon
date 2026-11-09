<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubory z adresáře obsahu, na které odkazuje text stránky nebo novinky.
 */
final class OdkazyVTextu
{
    /** Kolik bajtů před odkazem se čte, aby se poznalo, jestli je obrázek vložený do textu, nebo je odkaz ke stažení */
    private const int BAJTU_PRED_ODKAZEM = 400;

    /**
     * Pozná `soubory/obsah/…` v Markdownu i HTML, relativně i s doménou, i zakódované pro URL.
     *
     * @param list<string> $pripony přípony bez tečky, velikost písmen se nerozlišuje
     * @return list<string> cesty vůči adresáři obsahu
     */
    public static function getCesty(string $text, array $pripony): array
    {
        $cesty = [];
        foreach (self::getOdkazy($text, $pripony) as $odkaz) {
            $cesty[$odkaz->cesta] = $odkaz->cesta;
        }

        return array_values($cesty);
    }

    /**
     * Každý odkaz zvlášť, i s tím, jestli je soubor v textu vložený (obrázek), nebo jen odkazovaný.
     *
     * @param list<string> $pripony přípony bez tečky, velikost písmen se nerozlišuje
     * @return list<OdkazVTextu>
     */
    public static function getOdkazy(string $text, array $pripony): array
    {
        if (preg_match_all(self::getVzor($pripony), $text, $shody, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        $odkazy = [];
        foreach ($shody as $shoda) {
            $cesta = self::normalizujCestu($shoda['cesta'][0] . '.' . $shoda['pripona'][0]);
            if ($cesta === null) {
                continue;
            }
            $odkazy[] = new OdkazVTextu($cesta, self::isVlozeny($text, (int) $shoda[0][1]));
        }

        return $odkazy;
    }

    /**
     * Změní příponu odkazů na zadané soubory, zbytek odkazu (doména, kódování, `?v=2`) nechá.
     *
     * @param list<string> $pripony přípony bez tečky, velikost písmen se nerozlišuje
     * @param array<string, string> $nahrady cesta vůči adresáři obsahu, jak ji vrací getCesty() => nová přípona bez tečky
     * @param bool $jenVlozene přepíše jen obrázky vložené do textu, odkaz na soubor ke stažení nechá
     */
    public static function nahradPripony(string $text, array $pripony, array $nahrady, bool $jenVlozene = false): string
    {
        $vysledek = preg_replace_callback(
            self::getVzor($pripony),
            static function (array $shoda) use ($text, $nahrady, $jenVlozene): string {
                [$odkaz, $pozice] = $shoda[0];
                $cesta = self::normalizujCestu($shoda['cesta'][0] . '.' . $shoda['pripona'][0]);
                if ($cesta === null || !isset($nahrady[$cesta]) || ($jenVlozene && !self::isVlozeny($text, (int) $pozice))) {
                    return $odkaz;
                }

                return substr($odkaz, 0, -strlen($shoda['pripona'][0])) . $nahrady[$cesta];
            },
            $text,
            -1,
            $pocet,
            PREG_OFFSET_CAPTURE,
        );

        // text s nevalidním UTF-8 regulární výraz s `u` nepřečte, ten se nechá, jak je
        return $vysledek ?? $text;
    }

    /**
     * @param list<string> $pripony
     */
    private static function getVzor(array $pripony): string
    {
        return sprintf(
            '~soubory/obsah/+(?<cesta>[^\s"\'()<>\[\]?#\\\\]+?)\.(?<pripona>(?:%s))(?=[\s"\'()<>\[\]?#]|$)~iu',
            implode('|', array_map(static fn (string $pripona): string => preg_quote($pripona, '~'), $pripony)),
        );
    }

    /**
     * @return string|null cesta vůči adresáři obsahu; null, když vede ven z adresáře nebo ke skrytému souboru
     */
    private static function normalizujCestu(string $cesta): ?string
    {
        // %5C se po dekódování mění na zpětné lomítko, které služba bere jako oddělovač adresářů
        $segmenty = array_values(array_filter(
            explode('/', str_replace('\\', '/', rawurldecode($cesta))),
            static fn (string $segment): bool => $segment !== '',
        ));
        // `..` ani skryté soubory nenabízíme, služba by je stejně odmítla
        foreach ($segmenty as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }

        return implode('/', $segmenty);
    }

    /**
     * Obrázek vložený do textu: Markdown `![](…)`, HTML `<img src>` (`<source>`) a CSS `url(…)`. Cokoli jiného, tedy
     * i odkaz, jehož zápis se nepozná, je soubor ke stažení, který by se při změně formátu čtenáři rozbil.
     */
    private static function isVlozeny(string $text, int $pozice): bool
    {
        $predchozi = substr($text, max(0, $pozice - self::BAJTU_PRED_ODKAZEM), min(self::BAJTU_PRED_ODKAZEM, $pozice));
        // doména a lomítka před `soubory/`, aby zůstal jen zápis, který odkaz uvozuje
        $zacatek = (string) preg_replace('~(?:(?:https?:)?//[^\s"\'()<>\[\]/]+)?(?:\.{0,2}/)*$~i', '', $predchozi);

        return preg_match('~!\[[^\]]*\]\(\s*<?$~', $zacatek) === 1
            || preg_match('~<(?:img|source)\b[^>]*\b(?:src|srcset)\s*=\s*["\']?\s*$~i', $zacatek) === 1
            || preg_match('~\burl\(\s*["\']?$~i', $zacatek) === 1;
    }
}
