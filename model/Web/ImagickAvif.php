<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Zápis a čtení AVIF přes Imagick. ImageMagick 7.1 (dev obraz) zapisuje a čte AVIF správně. 6.9.11 (ostrá na Ubuntu
 * 22.04) zapíše AVIF s průhledností špatně, čte ho v YCbCr a s náhodnou průhledností; 6.9.12 s libheif 1.17 (Ubuntu
 * 24.04) průhlednost zapisuje i čte správně. Průhledné AVIF se proto zapisuje jen tam, kde to zkouška potvrdí, ne podle
 * verze, viz @see self::isAvifWritable().
 */
class ImagickAvif
{
    /** 0 je nejpomalejší a nejmenší soubor, 9 nejrychlejší; šestka je přijatelná i pro náhledy, které se generují za běhu */
    private const string RYCHLOST_KODERU = '6';
    /** Stupnice kvality AVIF a WebP se liší; dvojice WebP => AVIF dávají podobný soubor i výsledek (SSIM, PSNR) */
    private const array KVALITA_PODLE_WEBP = [80 => 62, 85 => 70, 92 => 82, 98 => 90, 100 => 100];
    /** Zkouška se zopakuje, kdyby se mezitím změnila knihovna, kterou verze ImageMagicku nepozná (například libheif) */
    private const int PLATNOST_ZKOUSKY_SEKUND = 3600;
    /** Od téhle verze ImageMagicku (`Imagick::getVersion()['versionNumber']`, 7.0.0) dekodér AVIF nevrací náhodnou průhlednost */
    private const int PRVNI_VERZE_S_DETERMINISTICKYM_DEKODEREM = 0x700;
    /** Dekodér ImageMagicku 6 vrací někdy náhodnou průhlednost, takže jedno kolečko s průhledností může projít náhodou (na 22.04 jen každé druhé) */
    private const int OPAKOVANI_ZKOUSKY_PRUHLEDNOSTI_NA_STARSIM_IMAGICKU = 10;
    /** Průhlednost v AVIF je pomocný obraz s tímhle řetězcem v bloku `auxC`, který je na začátku souboru */
    private const string ZNACKA_PRUHLEDNOSTI = 'auxiliary:alpha';
    private const int BAJTU_PRO_HLEDANI_PRUHLEDNOSTI = 65536;

    /** @var (callable(): array{neprusvitny: bool, prusvitny: bool})|null */
    private static $zkouska = null;
    /** @var array{neprusvitny: bool, prusvitny: bool}|null */
    private static ?array $vysledekZkousky = null;
    private static ?bool $starsiImagickProTesty = null;
    /** @var (callable(\Imagick, bool): string)|null */
    private static $zapisProTesty = null;

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
     * průhlednost vypíná jen na ImageMagicku 6, kde je to nutné. AVIF s průhledností a velkým profilem tak na ImageMagicku 6
     * o průhlednost přijde, i na 6.9.12, kde zkouška projde.
     */
    public static function isTransparent(string $cesta): bool
    {
        $zacatek = @file_get_contents($cesta, false, null, 0, self::BAJTU_PRO_HLEDANI_PRUHLEDNOSTI);

        return is_string($zacatek) && self::maZnackuPruhlednosti($zacatek);
    }

    /**
     * Jestli začátek souboru nese označení pomocného obrazu s průhledností. Zapisovač, který průhlednost zahodí,
     * ho nezapíše.
     */
    public static function maZnackuPruhlednosti(string $obsah): bool
    {
        return str_contains(substr($obsah, 0, self::BAJTU_PRO_HLEDANI_PRUHLEDNOSTI), self::ZNACKA_PRUHLEDNOSTI);
    }

    /**
     * ImageMagick 6, jehož dekodér AVIF vrací někdy náhodnou průhlednost. Co zvládne zapsat, říká zkouška, ne tahle metoda.
     */
    public static function isStarsiImagick(): bool
    {
        return self::$starsiImagickProTesty
            ?? \Imagick::getVersion()['versionNumber'] < self::PRVNI_VERZE_S_DETERMINISTICKYM_DEKODEREM;
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
     * Pro testy: nahradí zápis obrázku ve zkoušce. Zapisovač dostane obrázek s nastaveným zápisem a jestli má průhlednost
     * a vrátí bajty souboru, třeba takový, který průhlednost zahodí; null vrátí skutečný zápis.
     *
     * @param (callable(\Imagick, bool): string)|null $zapis
     */
    public static function setZapisProTesty(?callable $zapis): void
    {
        self::$zapisProTesty   = $zapis;
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
     * Skutečná zkouška bez mezipaměti. Průhlednost se nerozhoduje podle verze ImageMagicku, ale podle toho, co zapíše
     * a přečte: 6.9.11 ji zapíše špatně, 6.9.12 správně. Jedno kolečko s průhledností by na ImageMagicku 6 mohlo projít
     * náhodou (jeho dekodér vrací někdy náhodnou průhlednost), proto se tam opakuje.
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
            'prusvitny'   => self::projdeKolecekSPruhlednosti(),
        ];
    }

    private static function projdeKolecekSPruhlednosti(): bool
    {
        $opakovani = self::isStarsiImagick()
            ? self::OPAKOVANI_ZKOUSKY_PRUHLEDNOSTI_NA_STARSIM_IMAGICKU
            : 1;
        for ($pokus = 0; $pokus < $opakovani; $pokus++) {
            if (!self::projdeKolecek(true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Zapíše a přečte obrázek 16 × 16 px: vlevo červená, vpravo modrá, nebo průhledná. Barva pixelu se musí dost
     * podobat a u průhledného obrázku musí soubor nést označení průhlednosti a průhledný pixel musí zůstat průhledný.
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
            $obsah = (self::$zapisProTesty ?? static fn (\Imagick $zapisovany): string => $zapisovany->getImageBlob())($obrazek, $pravaPolovinaPruhledna);
            // Zapisovač, který průhlednost zahodí, nenechá v souboru označení. Podle pixelů by se to na ImageMagicku 6 s náhodnou
            // průhledností při čtení poznat nedalo spolehlivě.
            if ($pravaPolovinaPruhledna && !self::maZnackuPruhlednosti($obsah)) {
                return false;
            }

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
