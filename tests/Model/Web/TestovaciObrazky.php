<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

/**
 * Obrázky, které GD ani Imagick samy neumí vyrobit: hlavička s jinými rozměry, než má obsah, a EXIF orientace.
 * Levá půlka každého obrázku je červená, pravá modrá, takže je vidět, jak se s ním otočilo.
 */
trait TestovaciObrazky
{
    /**
     * JPEG, jehož hlavička hlásí jiné rozměry. `getimagesize()` v něm vidí obří obrázek, kdo by ho ale dekódoval,
     * zabral by paměť na celé rozměry.
     */
    private static function jpgSRozmeryVHlavicce(int $sirka, int $vyska): string
    {
        $jpeg = self::jpgBajty(16, 16);
        $pozice = 2;
        while ($pozice + 4 <= strlen($jpeg)) {
            if (ord($jpeg[$pozice + 1]) === 0xC0) {
                // FF C0, délka segmentu (2 bajty), přesnost (1 bajt), výška (2 bajty), šířka (2 bajty)
                return substr_replace($jpeg, pack('nn', $vyska, $sirka), $pozice + 5, 4);
            }
            $pozice += 2 + unpack('n', $jpeg, $pozice + 2)[1];
        }

        throw new \LogicException('V JPEG chybí hlavička SOF0.');
    }

    /**
     * Fotka z telefonu: pixely vodorovně a v EXIFu orientace, podle které se má před zobrazením otočit.
     *
     * @param int $orientace hodnota EXIF Orientation, 6 je „otočit o 90° po směru hodin“
     */
    private static function jpgSOrientaci(int $orientace, int $sirka = 40, int $vyska = 20): string
    {
        $jpeg = self::jpgBajty($sirka, $vyska);
        $tiff = 'II' . pack('vV', 42, 8)
            . pack('v', 1)
            . pack('vvVvv', 0x0112, 3, 1, $orientace, 0)
            . pack('V', 0);
        $exif = "Exif\0\0" . $tiff;

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
    }

    private static function jpgBajty(int $sirka, int $vyska): string
    {
        $obrazek = imagecreatetruecolor($sirka, $vyska);
        imagefilledrectangle($obrazek, 0, 0, intdiv($sirka, 2) - 1, $vyska - 1, 0xFF0000);
        imagefilledrectangle($obrazek, intdiv($sirka, 2), 0, $sirka - 1, $vyska - 1, 0x0000FF);
        ob_start();
        imagejpeg($obrazek, null, 95);

        return (string) ob_get_clean();
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private static function barvaPixelu(string $soubor, int $sloupec, int $radek): array
    {
        $barva = (new \Imagick($soubor))->getImagePixelColor($sloupec, $radek)->getColor();

        return [
            'r' => $barva['r'],
            'g' => $barva['g'],
            'b' => $barva['b'],
        ];
    }

    private static function jeCervena(string $soubor, int $sloupec, int $radek): bool
    {
        $barva = self::barvaPixelu($soubor, $sloupec, $radek);

        return $barva['r'] > 200 && $barva['b'] < 60;
    }

    private static function jeModra(string $soubor, int $sloupec, int $radek): bool
    {
        $barva = self::barvaPixelu($soubor, $sloupec, $radek);

        return $barva['b'] > 200 && $barva['r'] < 60;
    }

    private static function souborNahledu(string $urlNahledu): string
    {
        $soubor = CACHE . '/img/' . basename((string) strtok($urlNahledu, '?'));
        self::assertFileExists($soubor);

        return $soubor;
    }
}
