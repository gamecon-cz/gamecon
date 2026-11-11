<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\Info;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    /**
     * @test
     */
    public function kanonickaAdresaJeVHlavicceStejnaJakoOgUrl(): void
    {
        $info = $this->vytvorInfo()->url('https://gamecon.cz/program');

        $html = $info->html();

        self::assertStringContainsString('<link rel="canonical" href="https://gamecon.cz/program">', $html);
        self::assertStringContainsString('<meta property="og:url" content="https://gamecon.cz/program">', $html);
    }

    /**
     * @test
     */
    public function bezAdresyHlavickaNeobsahujeKanonickouAdresu(): void
    {
        $html = $this->vytvorInfo()->nazev('Program')->html();

        self::assertStringNotContainsString('rel="canonical"', $html);
        self::assertStringNotContainsString('og:url', $html);
    }

    /**
     * @test
     */
    public function parametrKterySeRozhodujeOObsahuSeDoKanonickeAdresyPrida(): void
    {
        $info = $this->vytvorInfo()
            ->url('https://gamecon.cz/novinky')
            ->setKanonickyParametr('start', 20);

        self::assertSame('https://gamecon.cz/novinky?start=20', $info->getKanonickaUrl());
        self::assertStringContainsString('<link rel="canonical" href="https://gamecon.cz/novinky?start=20">', $info->html());
    }

    /**
     * @test
     */
    public function vicParametruSeVKanonickeAdreseOddeliEscapovanymAmpersandem(): void
    {
        $info = $this->vytvorInfo()
            ->url('https://gamecon.cz/aktivity')
            ->setKanonickyParametr('rocnik', 2024)
            ->setKanonickyParametr('start', 20);

        self::assertStringContainsString(
            '<link rel="canonical" href="https://gamecon.cz/aktivity?rocnik=2024&amp;start=20">',
            $info->html(),
        );
    }

    /**
     * @test
     *
     * @dataProvider provideKanonickeAdresyZCesty
     */
    public function kanonickaAdresaZCestyJeBezKoncovehoLomitka(string $zakladniUrl, string $cesta, string $ocekavanaUrl): void
    {
        self::assertSame($ocekavanaUrl, Info::getKanonickaUrlZCesty($zakladniUrl, $cesta));
    }

    public static function provideKanonickeAdresyZCesty(): array
    {
        return [
            'podstranka'                      => ['https://gamecon.cz', 'program', 'https://gamecon.cz/program'],
            'koncove lomitko se zahodi'       => ['https://gamecon.cz', 'program/', 'https://gamecon.cz/program'],
            'vnorena cesta zustane'           => ['https://gamecon.cz', 'blog/nejaky-clanek', 'https://gamecon.cz/blog/nejaky-clanek'],
            'titulka'                         => ['https://gamecon.cz', '', 'https://gamecon.cz/'],
            'zakladni url s lomitkem'         => ['https://gamecon.cz/', 'program', 'https://gamecon.cz/program'],
            'zakladni url s cestou (lokalne)' => ['http://localhost:11190/web', 'program', 'http://localhost:11190/web/program'],
        ];
    }

    /**
     * @test
     */
    public function nazevAPopisOdUzivateleNeukonciTitulekAniAtribut(): void
    {
        // jméno vypravěče na /<id> si píše vypravěč sám
        $html = $this->vytvorInfo()
            ->nazev('x"><script>alert(1)</script>')
            ->popis('Hry & "spol"')
            ->html();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString(
            '<title>x&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</title>',
            $html,
        );
        self::assertStringContainsString(
            '<meta property="og:title" content="x&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">',
            $html,
        );
        self::assertStringContainsString('<meta property="og:description" content="Hry &amp; &quot;spol&quot;">', $html);
    }

    /**
     * @test
     */
    public function entitaVNadpisuStrankyZCmsSeNezdvoji(): void
    {
        // Stranka::nadpis() vrací obsah <h1> tak, jak je v HTML stránky
        $html = $this->vytvorInfo()->nazev('Hry &amp; spol')->html();

        self::assertStringContainsString('<title>Hry &amp; spol</title>', $html);
    }

    private function vytvorInfo(): Info
    {
        $systemoveNastaveni = $this->createStub(SystemoveNastaveni::class);
        $systemoveNastaveni->method('prostredi')->willReturn(Prostredi::Production);

        return new Info($systemoveNastaveni);
    }
}
