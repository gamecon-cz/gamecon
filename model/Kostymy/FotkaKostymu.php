<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Web\ZpracovaneObrazky;

final class FotkaKostymu
{
    public function __construct(
        private readonly int $id,
        private readonly string $soubor,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSoubor(): string
    {
        return $this->soubor;
    }

    public function getCesta(): string
    {
        return ZpracovaneObrazky::adresarFotekKostymu() . '/' . $this->soubor;
    }

    public function getUrl(): string
    {
        return URL_WEBU . '/soubory/systemove/kostymy/' . rawurlencode($this->soubor);
    }

    /**
     * Zmenšenina přes @see \Nahled (cache), a když ji nejde udělat (soubor, který ImageMagick nepřečte,
     * nezapisovatelná cache), původní soubor.
     */
    public function getUrlNahledu(int $sirka, int $vyska): string
    {
        try {
            return \Nahled::zeSouboru($this->getCesta())->pokryjOrez($sirka, $vyska)->url();
        } catch (\Exception $chyba) {
            return $this->getUrlPoChybeNahledu($chyba);
        }
    }

    /**
     * Zmenšenina pro `og:image` v JPEG: sociální sítě a chatovací aplikace AVIF nezobrazí a náhled z cache
     * (mimo WWW) @see \Gamecon\Web\Info::obrazekProSocialniSite() nepřevede. Původní soubor, když náhled nejde
     * udělat, převede.
     */
    public function getUrlNahleduProSocialniSite(int $sirka, int $vyska): string
    {
        try {
            return \Nahled::zeSouboru($this->getCesta())->pokryjOrez($sirka, $vyska)->jakoJpeg()->url();
        } catch (\Exception $chyba) {
            return $this->getUrlPoChybeNahledu($chyba);
        }
    }

    /**
     * Stránka s původním souborem funguje dál, příčina (třeba nezapisovatelná cache náhledů) ale patří do logu,
     * jinak by web potichu posílal fotky v plné velikosti. Do logu PHP, ne do výjimkovače: katalog by tam s každým
     * načtením přidal řádek za každou fotku. Chybějící soubor se nehlásí: na betě a preview s databází z ostré fotky
     * v databázi jsou, soubory ne, a log by zaplavily.
     */
    private function getUrlPoChybeNahledu(\Exception $chyba): string
    {
        if (is_file($this->getCesta())) {
            error_log(sprintf('Náhled fotky kostýmu %s nejde vytvořit, web dá původní soubor: %s', $this->soubor, $chyba->getMessage()));
        }

        return $this->getUrl();
    }
}
