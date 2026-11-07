<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class SitemapWebuTest extends AbstractTestWeb
{
    /**
     * @return string[]
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return array_merge(
            parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
                INSERT INTO stranky (url_stranky, obsah, redirect)
                VALUES ('stranka-pro-sitemapu', '# Stránka pro sitemapu', 0),
                       ('presmerovana-stranka', 'stranka-pro-sitemapu', 1)
                SQL,
                <<<'SQL'
                INSERT INTO novinky (typ, vydat, url, nazev, autor, text)
                VALUES (2, '2020-01-01 00:00:00', 'vydany-blog', 'Vydaný blog', 'Autor', 'Text blogu')
                SQL,
            ],
        );
    }

    /**
     * @test
     *
     * @dataProvider provideAdresySitemapy
     */
    public function sitemapaJeXmlNaObouAdresach(string $cesta): void
    {
        $sitemapa = $this->stahniVerejnouStranku($cesta);

        self::assertSame(200, $sitemapa['status']);
        self::assertStringStartsWith('application/xml', $sitemapa['contentType']);
        self::assertSouborProVyhledavaceJeKesovatelny($sitemapa);
        $adresy = $this->adresyZeSitemapy($sitemapa['telo']);
        self::assertSame($adresy, array_values(array_unique($adresy)));
        foreach (['/web/', '/web/program', '/web/larpy', '/web/stranka-pro-sitemapu', '/web/blog/vydany-blog'] as $ocekavanyKonec) {
            self::assertNotSame(
                [],
                array_filter($adresy, static fn (string $adresa) => str_ends_with($adresa, $ocekavanyKonec)),
                "Sitemapa neobsahuje adresu končící {$ocekavanyKonec}",
            );
        }
        self::assertSame(
            [],
            array_filter($adresy, static fn (string $adresa) => str_ends_with($adresa, '/web/presmerovana-stranka')),
            'Sitemapa obsahuje přesměrování',
        );
    }

    public static function provideAdresySitemapy(): array
    {
        return [
            'v koreni domeny' => ['sitemap.xml'],
            'v adresari webu' => ['web/sitemap.xml'],
        ];
    }

    /**
     * @test
     */
    public function kazdaAdresaZeSitemapyOdpovidaSeSvouKanonickouAdresou(): void
    {
        $adresy = $this->adresyZeSitemapy($this->stahniVerejnouStranku('web/sitemap.xml')['telo']);

        self::assertNotSame([], $adresy);
        foreach ($adresy as $adresa) {
            // adresa je vidět zvenku, test se k ní dostane přes Apache v kontejneru
            $stranka = $this->stahniVerejnouStranku(ltrim((string) parse_url($adresa, PHP_URL_PATH), '/'));

            self::assertSame(200, $stranka['status'], "Adresa ze sitemapy {$adresa} nevrátila 200");
            self::assertSame(
                1,
                preg_match('~<link rel="canonical" href="([^"]+)">~', $stranka['telo'], $shoda),
                "Adresa ze sitemapy {$adresa} nemá kanonickou adresu",
            );
            self::assertSame($adresa, html_entity_decode($shoda[1]), "Kanonická adresa se liší od adresy ze sitemapy {$adresa}");
        }
    }

    /**
     * @return list<string>
     */
    private function adresyZeSitemapy(string $xml): array
    {
        $adresy = [];
        foreach ((new \SimpleXMLElement($xml))->url as $url) {
            $adresy[] = (string) $url->loc;
        }

        return $adresy;
    }
}
