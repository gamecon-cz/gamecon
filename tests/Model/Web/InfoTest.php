<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\Info;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    protected function tearDown(): void
    {
        AktualniJazyk::reset();
        parent::tearDown();
    }

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

    /**
     * @test
     */
    public function prelozenaAnglickaStrankaMaKanonickouAdresuPodPrefixemJazyka(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        $info = $this->vytvorInfo()->setKanonickaCesta('program')->setObsahPrelozeny(true);

        self::assertSame(URL_WEBU . '/en/program', $info->getKanonickaUrl());
        self::assertSame(URL_WEBU . '/en/program', $info->url());
        $html = $info->html();
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/en/program">', $html);
        self::assertStringContainsString('<meta property="og:locale" content="en_GB">', $html);
    }

    /**
     * @test
     */
    public function jazykoveVerzeUkazujiNaKanonickeAdresyIsDotazem(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        $html = $this->vytvorInfo()
            ->setKanonickaCesta('rpg')
            ->setKanonickyParametr('rocnik', 2024)
            ->setObsahPrelozeny(true)
            ->html();

        self::assertStringContainsString('<link rel="alternate" hreflang="cs" href="' . URL_WEBU . '/rpg?rocnik=2024">', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="en" href="' . URL_WEBU . '/en/rpg?rocnik=2024">', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="x-default" href="' . URL_WEBU . '/rpg?rocnik=2024">', $html);
    }

    /**
     * @test
     */
    public function titulkaMaVKazdemJazyceSvouAdresu(): void
    {
        $html = $this->vytvorInfo()->setKanonickaCesta('')->setObsahPrelozeny(true)->html();

        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/">', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="en" href="' . URL_WEBU . '/en/">', $html);
    }

    /**
     * @test
     */
    public function bezKanonickeCestyHlavickaNeobsahujeJazykoveVerze(): void
    {
        $html = $this->vytvorInfo()->url('https://gamecon.cz/program')->setObsahPrelozeny(true)->html();

        self::assertStringNotContainsString('hreflang', $html);
    }

    /**
     * @test
     */
    public function neprelozenaStrankaNemaJazykoveVerzeAKanonickaJeSama(): void
    {
        $info = $this->vytvorInfo()->setKanonickaCesta('rpg')->setKanonickyParametr('rocnik', 2024);

        $html = $info->html();

        self::assertFalse($info->isObsahPrelozeny(), 'Výchozí je nepřeložená stránka.');
        self::assertStringNotContainsString('hreflang', $html);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/rpg?rocnik=2024">', $html);
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $html);
    }

    /**
     * @test
     */
    public function neprelozenaStrankaPodAnglictinouOdkazujeKanonickyNaCeskou(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        $info = $this->vytvorInfo()->setKanonickaCesta('rpg')->setKanonickyParametr('rocnik', 2024);

        $html = $info->html();

        self::assertSame(Jazyk::CESTINA, $info->getJazykObsahu());
        self::assertSame(URL_WEBU . '/rpg?rocnik=2024', $info->getKanonickaUrl());
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/rpg?rocnik=2024">', $html);
        self::assertStringContainsString('<meta property="og:url" content="' . URL_WEBU . '/rpg?rocnik=2024">', $html);
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $html, 'Obsah je česky, anglické je jen menu.');
        self::assertStringNotContainsString('hreflang', $html, 'Český obsah pod /en/ se nesmí hlásit jako anglická verze.');
    }

    /**
     * @test
     */
    public function prelozenaStrankaMaJazykObsahuPodleJazykaPozadavku(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        self::assertSame(Jazyk::ANGLICTINA, $this->vytvorInfo()->setObsahPrelozeny(true)->getJazykObsahu());
        self::assertSame(Jazyk::CESTINA, $this->vytvorInfo()->getJazykObsahu());
    }

    /**
     * @test
     */
    public function pozdejiNastavenaAdresaPrebijeKanonickouCestu(): void
    {
        $info = $this->vytvorInfo()
            ->setKanonickaCesta('program')
            ->setObsahPrelozeny(true)
            ->url('https://gamecon.cz/jinde');

        self::assertSame('https://gamecon.cz/jinde', $info->getKanonickaUrl());
        self::assertStringNotContainsString('hreflang', $info->html(), 'Z hotové adresy jazykové verze složit nejde.');
    }

    private function vytvorInfo(): Info
    {
        $systemoveNastaveni = $this->createStub(SystemoveNastaveni::class);
        $systemoveNastaveni->method('prostredi')->willReturn(Prostredi::Production);

        return new Info($systemoveNastaveni);
    }
}
