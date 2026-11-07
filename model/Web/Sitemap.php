<?php

declare(strict_types=1);

namespace Gamecon\Web;

class Sitemap
{
    /**
     * @param list<string> $cesty paths below the base URL, an empty one is the home page
     */
    public function __construct(
        private readonly string $zakladniUrl,
        private readonly array $cesty,
    ) {
    }

    public function getXml(): string
    {
        $adresy = array_unique(array_map(
            fn (string $cesta) => Info::getKanonickaUrlZCesty($this->zakladniUrl, $cesta),
            $this->cesty,
        ));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($adresy as $adresa) {
            $xml .= '  <url><loc>' . htmlspecialchars($adresa, ENT_XML1 | ENT_QUOTES) . "</loc></url>\n";
        }

        return $xml . "</urlset>\n";
    }
}
