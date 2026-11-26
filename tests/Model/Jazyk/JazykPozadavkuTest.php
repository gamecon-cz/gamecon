<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\JazykPozadavku;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JazykPozadavkuTest extends TestCase
{
    public static function cesty(): array
    {
        return [
            'česká stránka'               => ['program/patek', Jazyk::CESTINA, 'program/patek'],
            'česká titulka'               => ['', Jazyk::CESTINA, ''],
            'anglická stránka'            => ['en/program/patek', Jazyk::ANGLICTINA, 'program/patek'],
            'anglická titulka'            => ['en', Jazyk::ANGLICTINA, ''],
            'anglická titulka s lomítkem' => ['en/', Jazyk::ANGLICTINA, ''],
            'úvodní lomítko'              => ['/en/blog', Jazyk::ANGLICTINA, 'blog'],
            'jen začíná na en'            => ['english-page', Jazyk::CESTINA, 'english-page'],
            'en uprostřed cesty'          => ['blog/en', Jazyk::CESTINA, 'blog/en'],
        ];
    }

    #[DataProvider('cesty')]
    public function testPoznaJazykZPrefixuCesty(string $cesta, Jazyk $jazyk, string $cestaBezJazyka): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty($cesta);

        self::assertSame($jazyk, $jazykPozadavku->jazyk());
        self::assertSame($cestaBezJazyka, $jazykPozadavku->cestaBezJazyka());
    }

    public function testUrlVJazyceZachovaCestuIDotaz(): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty('program/patek', [
            'linie' => '["larpy"]',
        ]);

        self::assertSame(
            URL_WEBU . '/en/program/patek?linie=%5B%22larpy%22%5D',
            $jazykPozadavku->urlVJazyce(Jazyk::ANGLICTINA),
        );
        self::assertSame(URL_WEBU . '/program/patek', $jazykPozadavku->urlVJazyce(Jazyk::CESTINA, false));
    }

    public function testPrepinacVedePresParametrAZahodiStaryParametr(): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty('en/blog', [
            'jazyk'  => 'en',
            'strana' => '2',
        ]);

        self::assertSame(URL_WEBU . '/blog?strana=2&jazyk=cs', $jazykPozadavku->urlPrepnuti(Jazyk::CESTINA));
    }

    public function testCestuVOdkazuZnovuZakoduje(): void
    {
        // $_GET['req'] je už dekódovaná cesta, např. z /x%22%3E%3Cb%3E nebo /blog/a%3Fb
        self::assertSame(
            URL_WEBU . '/en/x%22%3E%3Cb%3E?jazyk=en',
            JazykPozadavku::zCesty('x"><b>')->urlPrepnuti(Jazyk::ANGLICTINA),
        );
        self::assertSame(
            URL_WEBU . '/en/blog/a%3Fb',
            JazykPozadavku::zCesty('blog/a?b')->presmerovani(Jazyk::ANGLICTINA, 'GET', false),
        );
        self::assertSame(
            URL_WEBU . '/en/a%0D%0ALocation%3A%20x',
            JazykPozadavku::zCesty("a\r\nLocation: x")->urlVJazyce(Jazyk::ANGLICTINA),
        );
    }

    public function testPoleVParametruPrepnutiNeniVolbaJazyka(): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty('program', [
            'jazyk' => ['en'],
        ]);

        self::assertNull($jazykPozadavku->jazykPrepnuti());
        self::assertNull($jazykPozadavku->getJazykDoCookie('GET', false));
        self::assertNull($jazykPozadavku->getJazykDoUctu());
    }

    public function testCeskaAdresaSeZvolenouAnglictinouPresmerujeNaAnglickou(): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty('program');

        self::assertSame(URL_WEBU . '/en/program', $jazykPozadavku->presmerovani(Jazyk::ANGLICTINA, 'GET', false));
    }

    public function testNepresmerovavaBezVolbyNeboKdyzVolbaSedi(): void
    {
        self::assertNull(JazykPozadavku::zCesty('program')->presmerovani(null, 'GET', false));
        self::assertNull(JazykPozadavku::zCesty('program')->presmerovani(Jazyk::CESTINA, 'GET', false));
        self::assertNull(
            JazykPozadavku::zCesty('en/program')->presmerovani(Jazyk::CESTINA, 'GET', false),
            'Adresa s prefixem jazyka je výslovná volba, nepřebíjí ji cookie.',
        );
    }

    public function testNepresmerovavaFormulareAjaxAniApi(): void
    {
        self::assertNull(JazykPozadavku::zCesty('prihlaska')->presmerovani(Jazyk::ANGLICTINA, 'POST', false));
        self::assertNull(JazykPozadavku::zCesty('program')->presmerovani(Jazyk::ANGLICTINA, 'GET', true));
        self::assertNull(JazykPozadavku::zCesty('api/aktivityUzivatel')->presmerovani(Jazyk::ANGLICTINA, 'GET', false));
        self::assertNull(
            JazykPozadavku::zCesty('altcha-challenge')->presmerovani(Jazyk::ANGLICTINA, 'GET', false, 'cors'),
            'fetch() (výzva Altchy) není navigace',
        );
        self::assertNull(
            JazykPozadavku::zCesty('program', [
                'jazyk' => 'cs',
            ])->presmerovani(Jazyk::ANGLICTINA, 'GET', false, 'cors'),
        );
    }

    public function testPresmerujeNavigaciSHlavickouIBezNi(): void
    {
        $jazykPozadavku = JazykPozadavku::zCesty('program');

        self::assertSame(URL_WEBU . '/en/program', $jazykPozadavku->presmerovani(Jazyk::ANGLICTINA, 'GET', false, 'navigate'));
        self::assertSame(
            URL_WEBU . '/en/program',
            $jazykPozadavku->presmerovani(Jazyk::ANGLICTINA, 'GET', false, ''),
            'Bez hlavičky Sec-Fetch-Mode (starší prohlížeč, test) rozhodne metoda a AJAX.',
        );
    }

    /**
     * @return array<string, array{string, bool, string, bool}>
     */
    public static function pozadavky(): array
    {
        return [
            'odkaz'                    => ['GET', false, 'navigate', true],
            'bez hlavičky'             => ['get', false, '', true],
            'formulář'                 => ['POST', false, 'navigate', false],
            'AJAX'                     => ['GET', true, '', false],
            'fetch()'                  => ['GET', false, 'cors', false],
            'fetch() ze stejného webu' => ['GET', false, 'same-origin', false],
            'obrázek, skript'          => ['GET', false, 'no-cors', false],
        ];
    }

    #[DataProvider('pozadavky')]
    public function testPoznaNavigaci(string $metoda, bool $jeAjax, string $secFetchMode, bool $jeNavigace): void
    {
        self::assertSame($jeNavigace, JazykPozadavku::zCesty('en/program')->isNavigace($metoda, $jeAjax, $secFetchMode));
    }

    public function testApiNeniNavigace(): void
    {
        self::assertFalse(JazykPozadavku::zCesty('en/api/aktivityUzivatel')->isNavigace('GET', false, 'navigate'));
        self::assertFalse(JazykPozadavku::zCesty('en/api')->isNavigace('GET', false));
    }

    public function testPrepnutiPresmerujeNaAdresuBezParametru(): void
    {
        self::assertSame(
            URL_WEBU . '/program',
            JazykPozadavku::zCesty('en/program', [
                'jazyk' => 'cs',
            ])->presmerovani(Jazyk::ANGLICTINA, 'GET', false),
        );
        self::assertSame(
            URL_WEBU . '/en/program',
            JazykPozadavku::zCesty('program', [
                'jazyk' => 'en',
            ])->presmerovani(null, 'GET', false),
        );
    }

    public function testCookieSiZapamatujePrefixPriNavigaciAPrepinacVzdy(): void
    {
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::zCesty('en/program')->getJazykDoCookie('GET', false, 'navigate'));
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::zCesty('en/')->getJazykDoCookie('GET', false));
        self::assertSame(Jazyk::CESTINA, JazykPozadavku::zCesty('program', [
            'jazyk' => 'cs',
        ])->getJazykDoCookie('GET', false));
        self::assertSame(
            Jazyk::CESTINA,
            JazykPozadavku::zCesty('en/program', [
                'jazyk' => 'cs',
            ])->getJazykDoCookie('POST', true, 'cors'),
            'Přepínač je výslovná volba, platí vždy.',
        );
        self::assertNull(
            JazykPozadavku::zCesty('program')->getJazykDoCookie('GET', false),
            'Česká adresa mohla přijít z natvrdo psaného odkazu, volbu nemění.',
        );
    }

    public function testPrefixMimoNavigaciCookieNemeni(): void
    {
        $anglicky = JazykPozadavku::zCesty('en/prihlaska');

        self::assertNull($anglicky->getJazykDoCookie('POST', false), 'formulář');
        self::assertNull($anglicky->getJazykDoCookie('GET', true), 'AJAX');
        self::assertNull($anglicky->getJazykDoCookie('GET', false, 'cors'), 'fetch()');
        self::assertNull(
            JazykPozadavku::zCesty('en/api/aktivityUzivatel')->getJazykDoCookie('GET', false),
            'Stará anglická záložka volající API nesmí přepnout volbu zpět.',
        );
    }

    public function testPrihlasenemuSiCookieJazykZPrefixuNebere(): void
    {
        self::assertNull(
            JazykPozadavku::zCesty('en/program')->getJazykDoCookie('GET', false, 'navigate', true),
            'Přihlášeného přesměruje na /en/ jeho účet; cookie by tu angličtinu jinak podržela pro každého dalšího v prohlížeči.',
        );
        self::assertSame(
            Jazyk::CESTINA,
            JazykPozadavku::zCesty('en/program', [
                'jazyk' => 'cs',
            ])->getJazykDoCookie('GET', false, 'navigate', true),
            'Přepínač platí i pro přihlášeného.',
        );
    }

    public function testPrepnutyNaJinehoUzivateleJehoUcetNemeni(): void
    {
        $prepinac = JazykPozadavku::zCesty('program', [
            'jazyk' => 'cs',
        ]);

        self::assertNull(
            $prepinac->getJazykDoUctu(true, true),
            'Infopult přepnutý na účastníka volí jazyk svého prohlížeče, ne jazyk e-mailů účastníka.',
        );
        self::assertSame(Jazyk::CESTINA, $prepinac->getJazykDoCookie('GET', false, 'navigate', true));
    }

    public function testDoUctuSeUkladaJenPrepinac(): void
    {
        self::assertNull(
            JazykPozadavku::zCesty('en/program')->getJazykDoUctu(),
            'Sdílený odkaz na /en/ ani infopult přihlášený za účastníka v anglickém prohlížeči nesmí přepnout účet.',
        );
        self::assertNull(JazykPozadavku::zCesty('program')->getJazykDoUctu());
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::zCesty('program', [
            'jazyk' => 'en',
        ])->getJazykDoUctu(zeStrankyWebu: true));
        self::assertSame(Jazyk::CESTINA, JazykPozadavku::zCesty('en/program', [
            'jazyk' => 'cs',
        ])->getJazykDoUctu(zeStrankyWebu: true));
    }

    public function testOdkazSPrepinacemZCizihoWebuUcetNemeni(): void
    {
        $prepinac = JazykPozadavku::zCesty('program', [
            'jazyk' => 'en',
        ]);

        self::assertNull(
            $prepinac->getJazykDoUctu(zeStrankyWebu: false),
            'Odkaz ?jazyk=en z chatu nebo cizího webu přepne jen prohlížeč, ne e-maily a přesměrování účtu.',
        );
        self::assertSame(Jazyk::ANGLICTINA, $prepinac->getJazykDoCookie('GET', false, 'navigate', true));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function providePuvodPozadavku(): array
    {
        return [
            'přepínač na našem webu'        => ['same-origin', '', true],
            'odkaz z cizího webu'           => ['cross-site', 'https://gamecon.cz/', false],
            'subdoména (admin, preview)'    => ['same-site', 'https://gamecon.cz/', false],
            'napsaná adresa nebo záložka'   => ['none', '', false],
            'starý prohlížeč, náš Referer'  => ['', 'https://GAMECON.cz/program', true],
            'starý prohlížeč, cizí Referer' => ['', 'https://evil.example/gamecon.cz', false],
            'starý prohlížeč, bez Refereru' => ['', '', false],
        ];
    }

    #[DataProvider('providePuvodPozadavku')]
    public function testPoznaJestliPozadavekPriselZeStrankyWebu(string $secFetchSite, string $referer, bool $ocekavany): void
    {
        self::assertSame($ocekavany, JazykPozadavku::isZeStrankyWebu($secFetchSite, $referer, 'gamecon.cz'));
    }

    public function testZvolenyJazykJeZCookieJinakZUctu(): void
    {
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::getZvolenyJazyk('en', Jazyk::CESTINA));
        self::assertSame(Jazyk::CESTINA, JazykPozadavku::getZvolenyJazyk('cs', Jazyk::ANGLICTINA));
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::getZvolenyJazyk(null, Jazyk::ANGLICTINA));
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::getZvolenyJazyk('xx', Jazyk::ANGLICTINA), 'neznámá hodnota cookie');
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::getZvolenyJazyk(['cs'], Jazyk::ANGLICTINA), 'gc_jazyk[]=cs');
        self::assertNull(JazykPozadavku::getZvolenyJazyk(null, null));
    }
}
