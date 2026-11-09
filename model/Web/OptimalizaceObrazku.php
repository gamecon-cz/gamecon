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
        NahravaniObrazku::AVIF => 'avif',
        // GIF se jen čte, nový obrázek se do něj nepřevádí
        NahravaniObrazku::GIF  => 'gif',
    ];
    /** Kolik bloků souboru se prohlíží při hledání animace nebo druhu komprese, hlavička je vždy na začátku */
    private const int MAX_BLOKU = 32;
    /** Kolik bloků a podbloků GIF se projde: snímek ve velkém rozlišení má desetitisíce podbloků po 255 bajtech */
    private const int MAX_BLOKU_GIF = 500_000;
    /** Úvodní blok `ftyp` s výčtem značek je dlouhý desítky bajtů, víc než tohle se nečte */
    private const int MAX_DELKA_FTYP = 4096;

    public function __construct(
        private readonly int $maxRozmerPx = OBRAZEK_MAX_ROZMER_PX,
        private readonly int $kvalitaAvif = OBRAZEK_AVIF_KVALITA,
        private readonly int $kvalitaWebp = OBRAZEK_WEBP_KVALITA,
    ) {
    }

    public function maxRozmerPx(): int
    {
        return $this->maxRozmerPx;
    }

    /**
     * Jestli se z obrázku v tomhle formátu dá zapsat AVIF; JPEG průhlednost mít nemůže, ostatní formáty ano.
     */
    public function isAvifWritableFrom(string $zdrojovyMime): bool
    {
        return ImagickAvif::isAvifWritable($zdrojovyMime !== NahravaniObrazku::JPG);
    }

    public function jeVetsiNezPotrebuje(int $sirka, int $vyska): bool
    {
        return max($sirka, $vyska) > $this->maxRozmerPx;
    }

    /**
     * Nejdelší šířka a výška, kterou ImageMagick přečte, podle `policy.xml` serveru (na ostré 16 000 px).
     *
     * @return array{sirka: float|int, vyska: float|int}
     */
    public function limityServeru(): array
    {
        return [
            'sirka' => \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_WIDTH),
            'vyska' => \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_HEIGHT),
        ];
    }

    public function jeDelsiNezServerPrecte(int $sirka, int $vyska): bool
    {
        $limity = $this->limityServeru();

        return $sirka > $limity['sirka'] || $vyska > $limity['vyska'];
    }

    /**
     * Animovaný GIF, PNG, WebP a AVIF se nezmenšují ani nepřevádějí, ImageMagick by z nich nechal jen první snímek. GIF,
     * jehož stavbu nejde přečíst, se bere jako animovaný, aby se nepřevedl.
     */
    public function jeAnimovany(string $cesta, string $mime): bool
    {
        return match ($mime) {
            NahravaniObrazku::PNG  => $this->prvniDulezityBlok($cesta, false, ['acTL', 'IDAT', 'IEND']) === 'acTL',
            NahravaniObrazku::WEBP => in_array($this->prvniDulezityBlok($cesta, true, ['ANIM', 'ANMF', 'VP8 ', 'VP8L']), ['ANIM', 'ANMF'], true),
            NahravaniObrazku::AVIF => $this->isAvifAnimated($cesta),
            NahravaniObrazku::GIF  => !$this->maJedinySnimek($cesta),
            default                => false,
        };
    }

    /**
     * Animovaný AVIF je posloupnost snímků a hlásí se značkou `avis` v úvodním bloku `ftyp`, buď jako hlavní, nebo jako
     * slučitelná. Nečitelný soubor se bere jako animovaný, aby se nepřevedl.
     */
    private function isAvifAnimated(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return true;
        }
        try {
            $hlavicka = (string) fread($soubor, 8);
            if (strlen($hlavicka) < 8 || substr($hlavicka, 4) !== 'ftyp') {
                return true;
            }
            $delkaBloku = unpack('N', $hlavicka)[1];
            if ($delkaBloku < 16) {
                return true;
            }
            // hlavní značka (4 bajty), verze (4 bajty) a seznam slučitelných značek po čtyřech bajtech
            $obsah = (string) fread($soubor, min($delkaBloku, self::MAX_DELKA_FTYP) - 8);

            return in_array('avis', str_split(substr($obsah, 0, 4) . substr($obsah, 8), 4), true);
        } finally {
            fclose($soubor);
        }
    }

    /**
     * GIF s jedním snímkem, který leží celý uvnitř obrazu. Bloky se procházejí bez ImageMagicku, takže GIF s miliony
     * snímků nic nealokuje; soubor useknutý uprostřed bloku nebo s neznámým blokem jedním snímkem není.
     */
    private function maJedinySnimek(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return false;
        }
        try {
            $hlavicka = (string) fread($soubor, 13);
            if (strlen($hlavicka) < 13 || !str_starts_with($hlavicka, 'GIF8')) {
                return false;
            }
            $obraz = unpack('vsirka/vvyska/Cpriznaky', $hlavicka, 6);
            $this->preskocBarevnouTabulku($soubor, $obraz['priznaky']);
            $snimku = 0;
            for ($zbyva = self::MAX_BLOKU_GIF; $zbyva > 0; --$zbyva) {
                $znacka = fread($soubor, 1);
                if ($znacka === '' || $znacka === false) {
                    // za celým snímkem chybí jen závěrečný znak, takže snímek je jediný
                    return $snimku === 1;
                }
                if ($znacka === ';') {
                    return $snimku === 1;
                }
                if ($znacka === '!') {
                    // rozšíření: značka druhu a podbloky
                    fseek($soubor, 1, SEEK_CUR);
                } elseif ($znacka === ',') {
                    $popis = (string) fread($soubor, 9);
                    if (strlen($popis) < 9) {
                        return false;
                    }
                    $snimek = unpack('vleva/vnahore/vsirka/vvyska/Cpriznaky', $popis);
                    if (
                        ++$snimku > 1
                        || $snimek['leva'] + $snimek['sirka'] > $obraz['sirka']
                        || $snimek['nahore'] + $snimek['vyska'] > $obraz['vyska']
                    ) {
                        return false;
                    }
                    $this->preskocBarevnouTabulku($soubor, $snimek['priznaky']);
                    // nejmenší velikost kódu LZW a za ní podbloky s obrazem
                    fseek($soubor, 1, SEEK_CUR);
                } else {
                    return false;
                }
                $podbloku = $this->preskocPodbloky($soubor, $zbyva);
                if ($podbloku === null) {
                    return false;
                }
                $zbyva -= $podbloku;
            }

            return false;
        } finally {
            fclose($soubor);
        }
    }

    private function maSnimekMensiNezObraz(\Imagick $imagick): bool
    {
        $obraz = $imagick->getImagePage();

        return $obraz['x'] !== 0
            || $obraz['y'] !== 0
            || $obraz['width'] > $imagick->getImageWidth()
            || $obraz['height'] > $imagick->getImageHeight();
    }

    /**
     * @param resource $soubor
     */
    private function preskocBarevnouTabulku($soubor, int $priznaky): void
    {
        if (($priznaky & 0x80) !== 0) {
            fseek($soubor, 3 * (1 << (($priznaky & 0x07) + 1)), SEEK_CUR);
        }
    }

    /**
     * @param resource $soubor
     * @return int|null počet přeskočených podbloků; null, když soubor končí uprostřed bloku nebo se nevejde do $nejvic
     */
    private function preskocPodbloky($soubor, int $nejvic): ?int
    {
        for ($pocet = 0; $pocet < $nejvic; ++$pocet) {
            $delka = fread($soubor, 1);
            if ($delka === '' || $delka === false) {
                return null;
            }
            if ($delka === "\x00") {
                return $pocet;
            }
            fseek($soubor, ord($delka), SEEK_CUR);
        }

        return null;
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
            if ($zdrojovyMime === NahravaniObrazku::AVIF) {
                ImagickAvif::pripravPoNacteni($imagick, $zdroj);
            }
            if ($zdrojovyMime === NahravaniObrazku::GIF && $this->maSnimekMensiNezObraz($imagick)) {
                // bez složení na plátno by se zapsal jen snímek, bez okraje, který GIF kolem něj má
                $naPlatne = $imagick->coalesceImages();
                $imagick->clear();
                $imagick = $naPlatne;
                $imagick->setImagePage(0, 0, 0, 0);
            }
            $kvalita = $this->kvalitaVystupu($imagick, $zdrojovyMime, $cilovyMime);
            // orientaci z EXIFu je potřeba použít dřív, než se metadata smažou
            $imagick->autoOrient();
            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }
            if ($cilovyMime === NahravaniObrazku::JPG && $imagick->getImageAlphaChannel()) {
                // JPEG průhlednost neumí a ImageMagick by průhledná místa zčernal
                $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
                $naBilem = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $imagick->clear();
                $imagick = $naBilem;
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
     * Nižší JPEG se neukládá s vyšší kvalitou, než jakou má: pevná kvalita by z něj udělala větší soubor, WebP, AVIF
     * i JPEG. Rezerva nad kvalitu zdroje by u JPEG zvětšila i výsledek, u hladkého obrázku i WebP.
     */
    private function kvalitaVystupu(\Imagick $imagick, string $zdrojovyMime, string $cilovyMime): int
    {
        $kvalita = $cilovyMime === NahravaniObrazku::AVIF
            ? $this->kvalitaAvif
            : $this->kvalitaWebp;
        $kvalitaZdroje = $zdrojovyMime === NahravaniObrazku::JPG
            ? $imagick->getImageCompressionQuality()
            : 0;

        return $kvalitaZdroje > 0
            ? min($kvalita, $kvalitaZdroje)
            : $kvalita;
    }

    /**
     * PNG a GIF jsou grafika a průhlednost, tu bezztrátově, stejně bezztrátový WebP; fotka z JPEG snese ztrátovou kompresi.
     */
    private function jeBezztratovyZdroj(string $zdroj, string $zdrojovyMime): bool
    {
        return in_array($zdrojovyMime, [NahravaniObrazku::PNG, NahravaniObrazku::GIF], true)
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
            NahravaniObrazku::AVIF => $this->nastavAvif($imagick, $kvalita, $bezztratove),
            NahravaniObrazku::WEBP => $this->nastavWebp($imagick, $kvalita, $bezztratove),
            NahravaniObrazku::JPG  => $this->nastavJpeg($imagick, $kvalita),
            NahravaniObrazku::PNG  => $this->nastavPng($imagick),
            default                => throw new \LogicException("Do formátu {$cilovyMime} se obrázek nepřevádí."),
        };
    }

    /**
     * Kvalita 100 je u AVIF bezztrátová; ikona o pár stovkách bajtů je v ní ale větší než jako PNG.
     */
    private function nastavAvif(\Imagick $imagick, int $kvalita, bool $bezztratove): void
    {
        ImagickAvif::nastavZapis($imagick, $bezztratove ? 100 : $kvalita);
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
