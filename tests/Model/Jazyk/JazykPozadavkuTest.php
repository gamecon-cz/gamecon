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
        self::assertNull($jazykPozadavku->jazykKZapamatovani());
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

    public function testZapamatujeSiJenVyslovnouVolbu(): void
    {
        self::assertSame(Jazyk::ANGLICTINA, JazykPozadavku::zCesty('en/program')->jazykKZapamatovani());
        self::assertSame(Jazyk::CESTINA, JazykPozadavku::zCesty('program', [
            'jazyk' => 'cs',
        ])->jazykKZapamatovani());
        self::assertNull(
            JazykPozadavku::zCesty('program')->jazykKZapamatovani(),
            'Česká adresa mohla přijít z natvrdo psaného odkazu, volbu nemění.',
        );
    }
}
