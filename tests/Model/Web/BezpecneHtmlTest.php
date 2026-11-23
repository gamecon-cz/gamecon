<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\BezpecneHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Text medailonku si píše vypravěč sám, z HTML v něm tedy smí projít jen to, co nic nespustí.
 */
class BezpecneHtmlTest extends TestCase
{
    #[Test]
    public function beznyMarkdownProjdeBezeZmeny(): void
    {
        $markdown = <<<'MD'
Hraju **RPG** a *larpy*, vedu [Dračí doupě](https://gamecon.cz/drd).

- jedna
- dvě

> citát

![mapa](soubory/obsah/mapa.png "Mapa")
MD;

        self::assertSame(
            <<<'HTML'
<p>Hraju <strong>RPG</strong> a <em>larpy</em>, vedu <a href="https://gamecon.cz/drd">Dračí doupě</a>.</p>

<ul>
<li>jedna</li>
<li>dvě</li>
</ul>

<blockquote>
  <p>citát</p>
</blockquote>

<p><img src="soubory/obsah/mapa.png" alt="mapa" title="Mapa"></p>

HTML,
            BezpecneHtml::zMarkdownu($markdown),
        );
    }

    #[Test]
    public function neskodneHtmlZAdminuZustane(): void
    {
        self::assertSame(
            '<p>řádek<br>další <b>tučně</b> <a href="mailto:pj@example.com" title="Napiš">e-mail</a></p>',
            BezpecneHtml::vycisti('<p>řádek<br />další <b>tučně</b> <a href="mailto:pj@example.com" title="Napiš">e-mail</a></p>'),
        );
    }

    #[Test]
    public function typografickeUvozovkyAEntityZustanouCitelne(): void
    {
        self::assertSame(
            "<p>„Ahoj“ &amp; 1 &lt; 2 – 5 € a 'apostrof'</p>\n",
            str_replace('&apos;', "'", BezpecneHtml::zMarkdownu('„Ahoj“ & 1 < 2 – 5 € a \'apostrof\'')),
        );
    }

    #[Test]
    #[DataProvider('provideSkodliveHtml')]
    public function skodliveHtmlNeprojde(string $markdown, string $ocekavane): void
    {
        $html = BezpecneHtml::zMarkdownu($markdown);

        self::assertSame($ocekavane, trim($html));
        // jako text (escapované) smí zůstat cokoli, ve značkách nic z toho
        self::assertDoesNotMatchRegularExpression('~<(script|iframe|style|svg|form|input|meta|base)\b~i', $html);
        self::assertDoesNotMatchRegularExpression('~<[^>]*\son\w+\s*=~i', $html);
        self::assertDoesNotMatchRegularExpression('~(href|src)="[^"]*(javascript|vbscript|data):~i', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSkodliveHtml(): iterable
    {
        yield 'skript' => ['Ahoj<script>alert(1)</script>', '<p>Ahoj</p>'];
        yield 'skript jako blok' => ["<script>\nalert(1)\n</script>", ''];
        yield 'obsluha události' => ['<img src="soubory/a.png" onerror="alert(1)">', '<p><img src="soubory/a.png"></p>'];
        yield 'obsluha události velkými' => ['<b OnMouseOver="alert(1)">x</b>', '<p><b>x</b></p>'];
        yield 'javascript v odkazu markdownu' => ['[klik](javascript:alert(1))', '<p>klik</p>'];
        yield 'javascript v odkazu html' => ['<a href="JaVaScRiPt:alert(1)">klik</a>', '<p>klik</p>'];
        yield 'javascript zakódovaný entitou' => ['<a href="&#106;avascript:alert(1)">klik</a>', '<p>klik</p>'];
        yield 'javascript s tabulátorem' => ["<a href=\"java\tscript:alert(1)\">klik</a>", '<p>klik</p>'];
        yield 'javascript s entitou tabulátoru' => ['<a href="java&#9;script:alert(1)">klik</a>', '<p>klik</p>'];
        yield 'javascript s mezerou na začátku' => ['<a href=" &#14; javascript:alert(1)">klik</a>', '<p>klik</p>'];
        yield 'data v obrázku' => ['![x](data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)', '<p></p>'];
        yield 'vbscript' => ['<a href="vbscript:msgbox(1)">klik</a>', '<p>klik</p>'];
        yield 'iframe' => ['<iframe src="https://example.com"></iframe>text', '<p>text</p>'];
        yield 'styl' => ['<style>body{display:none}</style>text', '<p>text</p>'];
        yield 'atribut style' => ['<p style="position:fixed;top:0">x</p>', '<p>x</p>'];
        yield 'svg s obsluhou' => ['<svg onload="alert(1)"><circle/></svg>text', '<p>text</p>'];
        yield 'formulář' => ['<form action="https://example.com"><input name="heslo"></form>text', '<p>text</p>'];
        yield 'neuzavřená značka' => ['<img src=x onerror=alert(1)//', '<p>&lt;img src=x onerror=alert(1)//</p>'];
        yield 'komentář' => ['a<!-- <script>alert(1)</script> -->b', '<p>ab</p>'];
        yield 'uvozovka v atributu' => ['<a href="https://example.com/&quot;onmouseover=&quot;alert(1)">x</a>', '<p><a href="https://example.com/&quot;onmouseover=&quot;alert(1)" rel="nofollow ugc">x</a></p>'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://example.com">text', '<p>text</p>'];
        yield 'base' => ['<base href="https://example.com/">text', '<p>text</p>'];
    }

    #[Test]
    public function relativniAProtokolemNezacinajiciOdkazyProjdou(): void
    {
        self::assertSame(
            '<a href="/vypraveci">a</a><a href="#kotva">b</a><a href="//example.com" rel="nofollow ugc">c</a><a href="tel:+420123456789">d</a>',
            BezpecneHtml::vycisti('<a href="/vypraveci">a</a><a href="#kotva">b</a><a href="//example.com">c</a><a href="tel:+420123456789">d</a>'),
        );
    }

    /**
     * Vyhledávače nemají odkazům, které si vypravěč napíše sám, věřit jako odkazům GameConu; na GameCon odkazovat smí.
     */
    #[Test]
    public function odkazVenDostaneNofollow(): void
    {
        self::assertSame(
            '<p><a href="https://example.com/hra" rel="nofollow ugc">hra</a> <a href="https://gamecon.cz/drd">DrD</a> '
            . '<a href="https://2025.gamecon.cz/">loni</a> <a href="mailto:pj@example.com">e-mail</a></p>',
            trim(BezpecneHtml::zMarkdownu(
                '[hra](https://example.com/hra) [DrD](https://gamecon.cz/drd) [loni](https://2025.gamecon.cz/) [e-mail](mailto:pj@example.com)',
            )),
        );
    }

    /**
     * Cizí server by z obrázku v medailonku viděl IP adresy všech, kdo stránku otevřou, a adresa s parametry by
     * v adminu jménem organizátora něco udělala.
     */
    #[Test]
    #[DataProvider('provideAdresyObrazku')]
    public function obrazekJenZWebuGameconu(string $adresa, bool $projde): void
    {
        $html = BezpecneHtml::vycisti('<img src="' . htmlspecialchars($adresa) . '" alt="x">');

        self::assertSame(
            $projde
                ? '<img src="' . htmlspecialchars($adresa, ENT_QUOTES | ENT_HTML5) . '" alt="x">'
                : '',
            $html,
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAdresyObrazku(): iterable
    {
        yield 'relativní do souborů' => ['soubory/obsah/mapa.png', true];
        yield 'adresa webu' => [URL_WEBU . '/soubory/obsah/mapa.png', true];
        yield 'zakódovaná diakritika' => ['soubory/obsah/m%C3%A1j.png', true];
        yield 'mimo soubory' => ['obrazek.png', false];
        yield 'od kořene, v adminu by vedla do adminu' => ['/soubory/obsah/mapa.png', false];
        yield 'parametry, v adminu by něco udělaly' => ['?pracovni_uzivatel=4242', false];
        yield 'soubor s parametry' => ['soubory/obsah/mapa.png?pracovni_uzivatel=4242', false];
        yield 'admin gameconu' => ['https://admin.gamecon.cz/?pracovni_uzivatel=4242', false];
        yield 'subdoména gameconu' => ['https://2025.gamecon.cz/soubory/obsah/mapa.png', false];
        yield 'web mimo soubory' => [URL_WEBU . '/odhlasit', false];
        yield 'web na jiném portu' => [
            parse_url(URL_WEBU, PHP_URL_SCHEME) . '://' . parse_url(URL_WEBU, PHP_URL_HOST) . ':8081/soubory/p.png',
            false,
        ];
        yield 'ven ze souborů přes ..' => ['soubory/../admin/', false];
        yield 'ven ze souborů přes zakódované ..' => ['soubory/%2E%2e/admin/', false];
        yield 'cizí server' => ['https://tracker.example/p.gif', false];
        yield 'bez protokolu' => ['//tracker.example/p.gif', false];
        yield 'zpětné lomítko jako lomítko' => ['/\\tracker.example/p.gif', false];
        yield 'jméno webu v cizí doméně' => ['https://' . parse_url(URL_WEBU, PHP_URL_HOST) . '.tracker.example/soubory/p.gif', false];
        yield 'web jako uživatel' => ['https://' . parse_url(URL_WEBU, PHP_URL_HOST) . '@tracker.example/soubory/p.gif', false];
    }

    #[Test]
    public function rozmeryObrazkuJenCisla(): void
    {
        self::assertSame(
            '<img src="soubory/a.png" width="100">',
            BezpecneHtml::vycisti('<img src="soubory/a.png" width="100" height="100%;x" class="velky" id="logo">'),
        );
    }

    #[Test]
    public function prazdnyText(): void
    {
        self::assertSame('', BezpecneHtml::zMarkdownu(null));
        self::assertSame('', BezpecneHtml::zMarkdownu("  \n "));
        self::assertSame('', BezpecneHtml::vycisti(''));
    }
}
