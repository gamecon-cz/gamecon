<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Zmenší nahraný obrázek na rozměr, který web potřebuje, převede ho do jiného formátu a zbaví ho metadat včetně
 * polohy z foťáku. Co se s kterým souborem stane, rozhoduje @see NahravaniObrazku, tady je jen samotná úprava.
 */
final class OptimalizaceObrazku
{
    /** Čtečku a zapisovač určuje formát zjištěný z obsahu, ne název souboru, ImageMagick tak nezkouší SVG, PDF a další */
    private const array KODEKY = [
        NahravaniObrazku::JPG  => 'jpeg',
        NahravaniObrazku::PNG  => 'png',
        NahravaniObrazku::WEBP => 'webp',
    ];
    /** Kolik bloků souboru se prohlíží při hledání animace nebo druhu komprese, hlavička je vždy na začátku */
    private const int MAX_BLOKU = 32;

    public function __construct(
        private readonly int $maxRozmerPx = OBRAZEK_MAX_ROZMER_PX,
        private readonly int $kvalita = OBRAZEK_WEBP_KVALITA,
    ) {
    }

    public function maxRozmerPx(): int
    {
        return $this->maxRozmerPx;
    }

    public function jeVetsiNezPotrebuje(int $sirka, int $vyska): bool
    {
        return max($sirka, $vyska) > $this->maxRozmerPx;
    }

    /**
     * Animovaný PNG a WebP se nezmenšují, ImageMagick by z nich nechal jen první snímek.
     */
    public function jeAnimovany(string $cesta, string $mime): bool
    {
        return match ($mime) {
            NahravaniObrazku::PNG  => $this->prvniDulezityBlok($cesta, false, ['acTL', 'IDAT', 'IEND']) === 'acTL',
            NahravaniObrazku::WEBP => in_array($this->prvniDulezityBlok($cesta, true, ['ANIM', 'ANMF', 'VP8 ', 'VP8L']), ['ANIM', 'ANMF'], true),
            default                => false,
        };
    }

    /**
     * @param string $zdrojovyMime formát souboru na $zdroj
     * @param string $cilovyMime formát, ve kterém se soubor zapíše na $cil
     */
    public function zapis(
        string $zdroj,
        string $zdrojovyMime,
        string $cil,
        string $cilovyMime,
        int $sirkaZdroje,
        int $vyskaZdroje,
    ): void {
        $imagick = new \Imagick();
        try {
            if ($zdrojovyMime === NahravaniObrazku::JPG && max($sirkaZdroje, $vyskaZdroje) >= 2 * $this->maxRozmerPx) {
                // dekodér umí velký JPEG rovnou zmenšit na polovinu, čtvrtinu nebo osminu; menší obrázek by naopak zvětšil
                $imagick->setOption('jpeg:size', $this->maxRozmerPx . 'x' . $this->maxRozmerPx);
            }
            $imagick->readImage(self::KODEKY[$zdrojovyMime] . ':' . $zdroj);
            $kvalita = $this->kvalitaVystupu($imagick, $zdrojovyMime);
            // orientaci z EXIFu je potřeba použít dřív, než se metadata smažou
            $imagick->autoOrient();
            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }
            if ($this->jeVetsiNezPotrebuje($imagick->getImageWidth(), $imagick->getImageHeight())) {
                $imagick->thumbnailImage($this->maxRozmerPx, $this->maxRozmerPx, true);
            }
            $this->odstranMetadata($imagick);
            $this->nastavVystup($imagick, $cilovyMime, $kvalita, $this->jeBezztratovyZdroj($zdroj, $zdrojovyMime));
            // ImageMagick by v názvu souboru vyhodnocoval `%d` a podobné zápisy, adresář s nimi by pak nešel zapsat
            $obsah = $imagick->getImageBlob();
        } catch (\ImagickException $imagickException) {
            error_log('Úprava obrázku selhala: ' . $imagickException->getMessage());

            throw new NahravaniObrazkuException(
                'Obrázek se nepodařilo zpracovat, nejspíš je poškozený. Zkus ho prosím uložit znovu, třeba jako JPG.',
                previous: $imagickException,
            );
        } finally {
            $imagick->clear();
        }
        if (@file_put_contents($cil, $obsah) === false) {
            throw new NahravaniObrazkuException('Soubor se nepodařilo uložit.');
        }
    }

    /**
     * Nižší JPEG se neukládá s vyšší kvalitou, než jakou má, jen by se zbytečně zvětšil.
     */
    private function kvalitaVystupu(\Imagick $imagick, string $zdrojovyMime): int
    {
        $kvalitaZdroje = $zdrojovyMime === NahravaniObrazku::JPG
            ? $imagick->getImageCompressionQuality()
            : 0;

        return $kvalitaZdroje > 0
            ? min($this->kvalita, $kvalitaZdroje)
            : $this->kvalita;
    }

    /**
     * PNG je grafika a průhlednost, tu bezztrátově, stejně bezztrátový WebP; fotka z JPEG snese ztrátovou kompresi.
     */
    private function jeBezztratovyZdroj(string $zdroj, string $zdrojovyMime): bool
    {
        return $zdrojovyMime === NahravaniObrazku::PNG
            || ($zdrojovyMime === NahravaniObrazku::WEBP && $this->jeBezztratovyWebp($zdroj));
    }

    private function jeBezztratovyWebp(string $cesta): bool
    {
        return $this->prvniDulezityBlok($cesta, true, ['VP8 ', 'VP8L', 'ANMF']) === 'VP8L';
    }

    /**
     * Projde bloky souboru od jeho hlavičky a vrátí typ prvního z $dulezite, null když žádný nenajde. Blok PNG má
     * délku (4 bajty) před typem a za daty kontrolní součet, blok RIFF (WebP) typ před délkou (little endian)
     * a data zarovnaná na sudý počet bajtů.
     *
     * @param list<string> $dulezite
     */
    private function prvniDulezityBlok(string $cesta, bool $riff, array $dulezite): ?string
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return null;
        }
        try {
            fseek($soubor, $riff ? 12 : 8);
            for ($blok = 0; $blok < self::MAX_BLOKU; ++$blok) {
                $hlavicka = (string) fread($soubor, 8);
                if (strlen($hlavicka) < 8) {
                    return null;
                }
                $typ = substr($hlavicka, $riff ? 0 : 4, 4);
                if (in_array($typ, $dulezite, true)) {
                    return $typ;
                }
                $delka = unpack($riff ? 'V' : 'N', $hlavicka, $riff ? 4 : 0)[1];
                fseek($soubor, $riff ? $delka + ($delka & 1) : $delka + 4, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($soubor);
        }
    }

    /**
     * Zůstane jen barevný profil, bez něj by se fotka z telefonu s širokým gamutem zbarvila jinak.
     */
    private function odstranMetadata(\Imagick $imagick): void
    {
        foreach ($imagick->getImageProfiles('*', false) as $profil) {
            if (!in_array($profil, ['icc', 'icm'], true)) {
                $imagick->removeImageProfile($profil);
            }
        }
    }

    private function nastavVystup(\Imagick $imagick, string $cilovyMime, int $kvalita, bool $bezztratove): void
    {
        $imagick->setImageFormat(self::KODEKY[$cilovyMime]);
        match ($cilovyMime) {
            NahravaniObrazku::WEBP => $this->nastavWebp($imagick, $kvalita, $bezztratove),
            NahravaniObrazku::JPG  => $this->nastavJpeg($imagick, $kvalita),
            NahravaniObrazku::PNG  => $this->nastavPng($imagick),
            default                => throw new \LogicException("Do formátu {$cilovyMime} se obrázek nepřevádí."),
        };
    }

    private function nastavWebp(\Imagick $imagick, int $kvalita, bool $bezztratove): void
    {
        $imagick->setOption('webp:lossless', $bezztratove ? 'true' : 'false');
        $imagick->setImageCompressionQuality($kvalita);
    }

    private function nastavJpeg(\Imagick $imagick, int $kvalita): void
    {
        $imagick->setImageCompressionQuality($kvalita);
        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
    }

    /**
     * Textové bloky nesou autora a podobná metadata a ImageMagick přidává vlastní data vytvoření.
     */
    private function nastavPng(\Imagick $imagick): void
    {
        $imagick->setOption('png:compression-level', '9');
        $imagick->setOption('png:exclude-chunk', 'date,time,text,zTXt,iTXt,eXIf');
    }
}
