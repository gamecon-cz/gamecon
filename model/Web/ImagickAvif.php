<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Zápis a čtení AVIF přes Imagick. Dev obraz a ostrá mají jiný ImageMagick (7.1 proti 6.9) a ten starší zapíše AVIF
 * s průhledností špatně, AVIF čte v YCbCr a s náhodnou průhledností, takže se průhledné AVIF zapisuje jen tam, kde to
 * zkouška potvrdí, viz @see self::isAvifWritable().
 */
class ImagickAvif
{
    /** 0 je nejpomalejší a nejmenší soubor, 9 nejrychlejší; šestka je přijatelná i pro náhledy, které se generují za běhu */
    private const string RYCHLOST_KODERU = '6';
    /** Stupnice kvality AVIF a WebP se liší; dvojice WebP => AVIF dávají podobný soubor i výsledek (SSIM, PSNR) */
    private const array KVALITA_PODLE_WEBP = [80 => 62, 85 => 70, 92 => 82, 98 => 90, 100 => 100];
    /** Zkouška se zopakuje, kdyby se mezitím změnila knihovna, kterou verze ImageMagicku nepozná (například libheif) */
    private const int PLATNOST_ZKOUSKY_SEKUND = 3600;
    /** Od téhle verze ImageMagicku (`Imagick::getVersion()['versionNumber']`, 7.0.0) se AVIF s průhledností zapisuje i čte správně */
    private const int PRVNI_VERZE_S_PRUHLEDNYM_AVIF = 0x700;
    /** Průhlednost v AVIF je pomocný obraz s tímhle řetězcem v bloku `auxC`, který je na začátku souboru */
    private const string ZNACKA_PRUHLEDNOSTI = 'auxiliary:alpha';
    private const int BAJTU_PRO_HLEDANI_PRUHLEDNOSTI = 65536;

    /** @var (callable(): array{neprusvitny: bool, prusvitny: bool})|null */
    private static $zkouska = null;
    /** @var array{neprusvitny: bool, prusvitny: bool}|null */
    private static ?array $vysledekZkousky = null;
    private static ?bool $starsiImagickProTesty = null;

    /**
     * Kvalita AVIF, která odpovídá kvalitě WebP (nebo JPEG) z téhle stupnice.
     */
    public static function getKvalitaAvifPodleWebp(int $kvalitaWebp): int
    {
        $predchozi = null;
        foreach (self::KVALITA_PODLE_WEBP as $webp => $avif) {
            if ($kvalitaWebp <= $webp) {
                if ($predchozi === null) {
                    return max(1, $avif - ($webp - $kvalitaWebp));
                }
                [$predchoziWebp, $predchoziAvif] = $predchozi;

                return (int) round($predchoziAvif + ($avif - $predchoziAvif) * ($kvalitaWebp - $predchoziWebp) / ($webp - $predchoziWebp));
            }
            $predchozi = [$webp, $avif];
        }

        return 100;
    }

    /**
     * Nastaví formát a kvalitu zápisu. Zapisovač AVIF čte kvalitu z nastavení zápisu, `setImageCompressionQuality()`
     * (kterou čte WebP) ignoruje a použil by výchozích 50.
     */
    public static function nastavZapis(\Imagick $imagick, int $kvalita): void
    {
        $imagick->setImageFormat('AVIF');
        $imagick->setCompressionQuality($kvalita);
        $imagick->setOption('heic:speed', self::RYCHLOST_KODERU);
    }

    /**
     * ImageMagick 6 vrací AVIF v YCbCr bez převodu do RGB, barvy by pak byly úplně jiné (PSNR kolem 10 dB), a u AVIF bez
     * průhlednosti k němu přidá průhlednost s náhodnými hodnotami, kterou je potřeba vypnout. Převod obrázku, který už je
     * v sRGB, nic nezmění; průhlednost se vypíná jen na ImageMagicku 6, kde je to potřeba.
     *
     * @param string|null $cesta soubor, ze kterého se obrázek načetl; podle něj se pozná, jestli průhlednost má
     */
    public static function pripravPoNacteni(\Imagick $imagick, ?string $cesta = null): void
    {
        if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_YCBCR) {
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
        }
        if (self::isStarsiImagick() && $cesta !== null && !self::isTransparent($cesta) && $imagick->getImageAlphaChannel()) {
            // ImageMagick 6 konstantu pro vypnutí jmenuje jinak
            $imagick->setImageAlphaChannel(
                defined('Imagick::ALPHACHANNEL_OFF')
                    ? \Imagick::ALPHACHANNEL_OFF
                    : \Imagick::ALPHACHANNEL_DEACTIVATE,
            );
        }
    }

    /**
     * Jestli AVIF v souboru nese průhlednost; soubor, který se nepodaří přečíst, ji nemá. Označení leží na začátku
     * souboru, ale za barevným profilem, takže u obrázku s velkým profilem ho tohle hledání nenajde; proto se podle něj
     * průhlednost vypíná jen na ImageMagicku 6, kde je to nutné.
     */
    public static function isTransparent(string $cesta): bool
    {
        $zacatek = @file_get_contents($cesta, false, null, 0, self::BAJTU_PRO_HLEDANI_PRUHLEDNOSTI);

        return is_string($zacatek) && str_contains($zacatek, self::ZNACKA_PRUHLEDNOSTI);
    }

    /**
     * ImageMagick 6 (ostrá), který AVIF s průhledností zapisuje i čte špatně.
     */
    public static function isStarsiImagick(): bool
    {
        return self::$starsiImagickProTesty
            ?? \Imagick::getVersion()['versionNumber'] < self::PRVNI_VERZE_S_PRUHLEDNYM_AVIF;
    }

    /**
     * Pro testy: tváří se jako starší nebo novější ImageMagick, null vrátí skutečnou verzi.
     */
    public static function setStarsiImagickProTesty(?bool $jeStarsi): void
    {
        self::$starsiImagickProTesty = $jeStarsi;
        // výsledek zkoušky, který už vznikl z jiné verze, by se jinak držel do konce procesu
        self::$vysledekZkousky       = null;
    }

    /**
     * Jestli tenhle ImageMagick zapíše AVIF tak, že se z něj přečte stejný obrázek. Obrázek bez průhlednosti (JPEG)
     * a s ní (PNG, GIF, ale i WebP a AVIF) se posuzuje zvlášť, protože na ostré projde jen ten první.
     * Výsledek se pamatuje do konce skriptu a hodinu v souboru (zkouška trvá desítky milisekund a ptá se na ni každé
     * zobrazení náhledu); co zkoušku neprojde, se zapisuje jako dřív, do WebP.
     */
    public static function isAvifWritable(bool $sPruhlednosti): bool
    {
        self::$vysledekZkousky ??= self::$zkouska !== null
            ? (self::$zkouska)()
            : self::nactiNebo();

        return self::$vysledekZkousky[$sPruhlednosti ? 'prusvitny' : 'neprusvitny'];
    }

    /**
     * Soubor s výsledkem zkoušky. Jméno nese verzi ImageMagicku a jméno stroje, takže nový obraz Dockeru (nový kontejner)
     * ani nový ImageMagick na ostré nenavážou na výsledek toho předchozího.
     */
    public static function getSouborMezipameti(): string
    {
        return SPEC . '/imagick-avif-' . md5(\Imagick::getVersion()['versionString'] . phpversion('imagick') . php_uname('n')) . '.json';
    }

    /**
     * Pro testy: nahradí zkoušku zadaným výsledkem, null vrátí skutečnou zkoušku.
     *
     * @param array{neprusvitny: bool, prusvitny: bool}|null $vysledek
     */
    public static function setZkouskaProTesty(?array $vysledek): void
    {
        self::$zkouska         = $vysledek === null
            ? null
            : static fn (): array => $vysledek;
        self::$vysledekZkousky = null;
    }

    /**
     * @return array{neprusvitny: bool, prusvitny: bool}
     */
    private static function nactiNebo(): array
    {
        $soubor = self::getSouborMezipameti();
        if (is_file($soubor) && time() - (int) filemtime($soubor) < self::PLATNOST_ZKOUSKY_SEKUND) {
            $ulozeny = json_decode((string) file_get_contents($soubor), true);
            if (is_array($ulozeny) && is_bool($ulozeny['neprusvitny'] ?? null) && is_bool($ulozeny['prusvitny'] ?? null)) {
                return ['neprusvitny' => $ulozeny['neprusvitny'], 'prusvitny' => $ulozeny['prusvitny']];
            }
        }
        $vysledek = self::provedZkousku();
        try {
            (new Filesystem())->dumpFile($soubor, json_encode($vysledek, JSON_THROW_ON_ERROR));
        } catch (IOException) {
            // bez mezipaměti se zkouška jen zopakuje při dalším požadavku
        }

        return $vysledek;
    }

    /**
     * Skutečná zkouška bez mezipaměti. Průhlednost se na ImageMagicku 6 nezkouší: jeho dekodér AVIF vrací u malých obrázků
     * náhodnou průhlednost, takže by zkouška jednou prošla a jednou ne.
     *
     * @return array{neprusvitny: bool, prusvitny: bool}
     */
    public static function provedZkousku(): array
    {
        if (!in_array('AVIF', \Imagick::queryFormats('AVIF'), true)) {
            return ['neprusvitny' => false, 'prusvitny' => false];
        }

        return [
            'neprusvitny' => self::projdeKolecek(false),
            'prusvitny'   => !self::isStarsiImagick() && self::projdeKolecek(true),
        ];
    }

    /**
     * Zapíše a přečte obrázek 16 × 16 px: vlevo červená, vpravo modrá, nebo průhledná. Barva pixelu se musí dost
     * podobat a u průhledného obrázku musí průhledný pixel zůstat průhledný.
     */
    private static function projdeKolecek(bool $pravaPolovinaPruhledna): bool
    {
        try {
            $obrazek = new \Imagick();
            $obrazek->newImage(16, 16, new \ImagickPixel($pravaPolovinaPruhledna ? 'transparent' : 'rgb(0,0,255)'));
            $cervena = new \ImagickDraw();
            $cervena->setFillColor(new \ImagickPixel('rgb(255,0,0)'));
            $cervena->rectangle(0, 0, 7, 15);
            $obrazek->drawImage($cervena);
            if (!$pravaPolovinaPruhledna) {
                // kreslení přidá průhlednost, kterou ImageMagick 6 do AVIF zapíše špatně; JPEG ji nemá
                $obrazek->setImageFormat('JPEG');
                $zJpeg = new \Imagick();
                $zJpeg->readImageBlob($obrazek->getImageBlob());
                $obrazek = $zJpeg;
            }
            self::nastavZapis($obrazek, 80);
            $obsah = $obrazek->getImageBlob();

            $nacteny = new \Imagick();
            $nacteny->readImageBlob($obsah);
            self::pripravPoNacteni($nacteny);
            if ($nacteny->getImageWidth() !== 16 || $nacteny->getImageHeight() !== 16) {
                return false;
            }
            $vlevo = $nacteny->getImagePixelColor(3, 8);
            $vpravo = $nacteny->getImagePixelColor(12, 8);
            $jeCervena = $vlevo->getColorValue(\Imagick::COLOR_RED) > 0.8 && $vlevo->getColorValue(\Imagick::COLOR_BLUE) < 0.25;

            return $pravaPolovinaPruhledna
                ? $jeCervena && $vlevo->getColorValue(\Imagick::COLOR_ALPHA) > 0.9 && $vpravo->getColorValue(\Imagick::COLOR_ALPHA) < 0.1
                : $jeCervena && $vpravo->getColorValue(\Imagick::COLOR_BLUE) > 0.8 && $vpravo->getColorValue(\Imagick::COLOR_RED) < 0.25;
        } catch (\ImagickException | \ImagickDrawException | \ImagickPixelException) {
            return false;
        }
    }
}
