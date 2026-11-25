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
        } catch (\Exception) {
            return $this->getUrl();
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
        } catch (\Exception) {
            return $this->getUrl();
        }
    }
}
