<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\Sitemap;
use PHPUnit\Framework\TestCase;

class SitemapTest extends TestCase
{
    /**
     * @test
     */
    public function kazdaStrankaMaVMapeSvouKanonickouAdresuJednou(): void
    {
        $sitemap = new Sitemap('https://gamecon.cz', ['', 'program', 'program/', 'blog/clanek', 'program']);

        self::assertSame(
            ['https://gamecon.cz/', 'https://gamecon.cz/program', 'https://gamecon.cz/blog/clanek'],
            $this->adresy($sitemap->getXml()),
        );
    }

    /**
     * @test
     */
    public function adresaSeVXmlEscapuje(): void
    {
        $xml = (new Sitemap('https://gamecon.cz', ['hledani?a=1&b=2']))->getXml();

        self::assertStringContainsString('<loc>https://gamecon.cz/hledani?a=1&amp;b=2</loc>', $xml);
        self::assertSame(['https://gamecon.cz/hledani?a=1&b=2'], $this->adresy($xml));
    }

    /**
     * @test
     */
    public function mapaJeXmlSJmennymProstoremSitemap(): void
    {
        $xml = (new Sitemap('https://gamecon.cz', ['program']))->getXml();

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $urlset = new \SimpleXMLElement($xml);
        self::assertSame('urlset', $urlset->getName());
        self::assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $urlset->getNamespaces()['']);
    }

    /**
     * @test
     */
    public function mapaBezStranekJeStalePlatnyXml(): void
    {
        $xml = (new Sitemap('https://gamecon.cz', []))->getXml();

        self::assertSame([], $this->adresy($xml));
    }

    /**
     * @return list<string>
     */
    private function adresy(string $xml): array
    {
        $adresy = [];
        foreach ((new \SimpleXMLElement($xml))->url as $url) {
            $adresy[] = (string) $url->loc;
        }

        return $adresy;
    }
}
