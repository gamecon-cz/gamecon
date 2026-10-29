<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\NavratNaPreview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NavratNaPreviewTest extends TestCase
{
    #[DataProvider('provideUrlPreview')]
    public function testUrlPreviewProjde(string $url): void
    {
        self::assertSame($url, NavratNaPreview::getBezpecnaUrl($url));
    }

    public static function provideUrlPreview(): iterable
    {
        yield 'stránka preview' => ['https://1223-vytvorit-page-s-vypisem-v.preview.gamecon.cz/vypravaci'];
        yield 'bez cesty' => ['https://feature-x.preview.gamecon.cz'];
        yield 'kořen' => ['https://feature-x.preview.gamecon.cz/'];
        yield 'dotaz hned za hostem' => ['https://feature-x.preview.gamecon.cz?a=1'];
        yield 'dotaz, kódované znaky a fragment' => ['https://x.preview.gamecon.cz/program?a=1&b=%C5%A1#top'];
        yield 'preview s číslicí na začátku' => ['https://1274.preview.gamecon.cz/'];
        yield 'webmail a vseveda jsou také preview' => ['https://webmail.preview.gamecon.cz/'];
        yield 'zavináč až v cestě' => ['https://x.preview.gamecon.cz/a@evil.example'];
        yield 'dvojité lomítko v cestě' => ['https://x.preview.gamecon.cz//evil.example'];
        yield 'nejdelší povolená URL' => ['https://x.preview.gamecon.cz/' . str_repeat('a', 2000 - 29)];
        yield 'štítek o 63 znacích' => ['https://' . str_repeat('a', 63) . '.preview.gamecon.cz/'];
        yield 'štítek s pomlčkou uprostřed' => ['https://a-b.preview.gamecon.cz/'];
        yield 'hranice povolených znaků: ! [ ] ~' => ['https://x.preview.gamecon.cz/!a[b]c~d'];
        yield 'zavináč hned za otazníkem je dotaz, ne uživatel' => ['https://x.preview.gamecon.cz?@evil.example/'];
        yield 'fragment hned za hostem' => ['https://x.preview.gamecon.cz#a'];
        yield 'velká písmena v cestě' => ['https://x.preview.gamecon.cz/Program/ABC?Q=1'];
        yield 'kódovaný CR LF je jen text' => ['https://x.preview.gamecon.cz/a%0d%0aSet-Cookie:%20a=b'];
        yield 'štítek s xn--' => ['https://xn--a-9ga.preview.gamecon.cz/'];
    }

    #[DataProvider('provideCilProPrihlaseneho')]
    public function testCilProPrihlaseneho(bool $jePrihlaseny, string $metoda, mixed $hodnota, ?string $ocekavano): void
    {
        self::assertSame($ocekavano, NavratNaPreview::getCilProPrihlaseneho($jePrihlaseny, $metoda, $hodnota));
    }

    public static function provideCilProPrihlaseneho(): iterable
    {
        $url = 'https://x.preview.gamecon.cz/vypravaci';

        yield 'přihlášený, GET, URL preview' => [true, 'GET', $url, $url];
        yield 'nepřihlášený se nepřesměruje, formulář se zobrazí' => [false, 'GET', $url, null];
        yield 'přihlášený, ale POST (třeba odhlášení)' => [true, 'POST', $url, null];
        yield 'přihlášený, HEAD' => [true, 'HEAD', $url, null];
        yield 'přihlášený, metoda neznámá (CLI)' => [true, '', $url, null];
        yield 'přihlášený, get malými písmeny' => [true, 'get', $url, null];
        yield 'přihlášený, GET, cizí URL' => [true, 'GET', 'https://evil.example/', null];
        yield 'přihlášený, GET, bez parametru' => [true, 'GET', null, null];
        yield 'přihlášený, GET, pole' => [true, 'GET', [$url], null];
    }

    #[DataProvider('provideNebezpecnaHodnota')]
    public function testJinaHodnotaSeIgnoruje(mixed $hodnota): void
    {
        self::assertNull(NavratNaPreview::getBezpecnaUrl($hodnota));
    }

    public static function provideNebezpecnaHodnota(): iterable
    {
        yield 'chybí' => [null];
        yield 'pole z ?returnTo[]=' => [['https://x.preview.gamecon.cz/']];
        yield 'číslo' => [1];
        yield 'prázdný řetězec' => [''];
        yield 'http' => ['http://x.preview.gamecon.cz/'];
        yield 'bez schématu' => ['//x.preview.gamecon.cz/'];
        yield 'jen cesta' => ['/admin/'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'jiné schéma' => ['ftp://x.preview.gamecon.cz/'];
        yield 'velkými písmeny' => ['HTTPS://X.PREVIEW.GAMECON.CZ/'];
        yield 'host bez štítku' => ['https://preview.gamecon.cz/'];
        yield 'ostrá' => ['https://gamecon.cz/'];
        yield 'admin' => ['https://admin.gamecon.cz/'];
        yield 'beta' => ['https://beta.gamecon.cz/'];
        yield 'archiv' => ['https://2025.gamecon.cz/'];
        yield 'cizí host' => ['https://evil.example/'];
        yield 'preview jen v dotazu' => ['https://evil.example/?x=.preview.gamecon.cz'];
        yield 'preview jen v cestě' => ['https://evil.example/.preview.gamecon.cz'];
        yield 'preview jako začátek cizího hostu' => ['https://x.preview.gamecon.cz.evil.example/'];
        yield 'host končící preview' => ['https://xpreview.gamecon.cz/'];
        yield 'první tečka domény jiným znakem' => ['https://xapreview.gamecon.cz/'];
        yield 'druhá tečka domény jiným znakem' => ['https://x.previewagamecon.cz/'];
        yield 'třetí tečka domény jiným znakem' => ['https://x.preview.gameconacz/'];
        yield 'tečka na konci hostu' => ['https://x.preview.gamecon.cz./'];
        yield 'vnořený štítek' => ['https://a.b.preview.gamecon.cz/'];
        yield 'uživatel před hostem' => ['https://evil.example@x.preview.gamecon.cz/'];
        yield 'uživatel za hostem' => ['https://x.preview.gamecon.cz@evil.example/'];
        yield 'uživatel s heslem' => ['https://user:pass@x.preview.gamecon.cz/'];
        yield 'port' => ['https://x.preview.gamecon.cz:8443/'];
        yield 'zpětné lomítko za hostem' => ['https://x.preview.gamecon.cz\\@evil.example/'];
        yield 'zpětné lomítko v cestě' => ['https://x.preview.gamecon.cz/\\evil'];
        yield 'mezera' => ['https://x.preview.gamecon.cz/a b'];
        yield 'nový řádek v cestě' => ["https://x.preview.gamecon.cz/a\nSet-Cookie: a=b"];
        yield 'návrat vozíku' => ["https://x.preview.gamecon.cz/a\rb"];
        yield 'nový řádek na konci' => ["https://x.preview.gamecon.cz/\n"];
        yield 'nulový bajt' => ["https://x.preview.gamecon.cz/a\0b"];
        yield 'tabulátor' => ["https://x.preview.gamecon.cz/a\tb"];
        yield 'znak mimo ASCII' => ['https://x.preview.gamecon.cz/á'];
        yield 'host se znakem mimo ASCII' => ['https://á.preview.gamecon.cz/'];
        yield 'podtržítko v hostu' => ['https://a_b.preview.gamecon.cz/'];
        yield 'štítek začínající pomlčkou' => ['https://-a.preview.gamecon.cz/'];
        yield 'štítek končící pomlčkou' => ['https://a-.preview.gamecon.cz/'];
        yield 'tečka v hostu zakódovaná procentem' => ['https://x%2epreview.gamecon.cz/'];
        yield 'DEL v cestě' => ["https://x.preview.gamecon.cz/a\x7Fb"];
        yield 'štítek delší než 63 znaků' => ['https://' . str_repeat('a', 64) . '.preview.gamecon.cz/'];
        yield 'delší než 2000 znaků' => ['https://x.preview.gamecon.cz/' . str_repeat('a', 2000 - 29 + 1)];
        yield 'dvě URL za sebou' => ['https://x.preview.gamecon.cz/ https://evil.example/'];
    }
}
