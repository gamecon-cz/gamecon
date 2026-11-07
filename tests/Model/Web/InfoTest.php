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
            ->kanonickyParametr('start', 20);

        self::assertSame('https://gamecon.cz/novinky?start=20', $info->kanonickaUrl());
        self::assertStringContainsString('<link rel="canonical" href="https://gamecon.cz/novinky?start=20">', $info->html());
    }

    /**
     * @test
     */
    public function vicParametruSeVKanonickeAdreseOddeliEscapovanymAmpersandem(): void
    {
        $info = $this->vytvorInfo()
            ->url('https://gamecon.cz/aktivity')
            ->kanonickyParametr('rocnik', 2024)
            ->kanonickyParametr('start', 20);

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
        self::assertSame($ocekavanaUrl, Info::kanonickaUrlZCesty($zakladniUrl, $cesta));
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

    private function vytvorInfo(): Info
    {
        $systemoveNastaveni = $this->createStub(SystemoveNastaveni::class);
        $systemoveNastaveni->method('prostredi')->willReturn(Prostredi::Production);

        return new Info($systemoveNastaveni);
    }
}
