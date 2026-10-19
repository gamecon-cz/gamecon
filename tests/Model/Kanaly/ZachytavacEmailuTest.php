<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly;

use Gamecon\Kanaly\ZachytavacEmailu;
use Gamecon\Prostredi\Prostredi;
use PHPUnit\Framework\TestCase;

class ZachytavacEmailuTest extends TestCase
{
    /**
     * @test
     */
    public function lokalneVedeNaAdresuZPromenneProstredi(): void
    {
        $zachytavac = new ZachytavacEmailu(Prostredi::Locale, 'http://localhost:11112', '');

        self::assertSame('http://localhost:11112', $zachytavac->getUrl());
    }

    /**
     * @test
     */
    public function lokalneBezAdresyVNastaveniNeniKamOdkazat(): void
    {
        $zachytavac = new ZachytavacEmailu(Prostredi::Locale, '', '');

        self::assertNull($zachytavac->getUrl(), 'Bez nastavené adresy by tlačítko vedlo na prázdný odkaz');
    }

    /**
     * @test
     */
    public function naPreviewVedeNaSdilenyWebmailVsechPreview(): void
    {
        $zachytavac = new ZachytavacEmailu(Prostredi::Preview, '', '');

        self::assertSame('https://webmail.preview.gamecon.cz/', $zachytavac->getUrl());
    }

    /**
     * @test
     */
    public function naPreviewSePodepisePrukazProBranuKdyzJeTajemstviNastavene(): void
    {
        $zachytavac = new ZachytavacEmailu(Prostredi::Preview, '', 'tajemstvi-brany');

        self::assertMatchesRegularExpression(
            '~^https://webmail\.preview\.gamecon\.cz/\?gate=[\w-]+\.[\w-]+$~',
            (string) $zachytavac->getUrl(),
            'Stejně jako odkaz na webmail na stránce Previews, aby se nezobrazil dialog s přihlášením k bráně',
        );
    }

    /**
     * @test
     *
     * @dataProvider provideProstrediBezZachytavace
     */
    public function naBeteAOstreSeEmailyDoruci(Prostredi $prostredi): void
    {
        // Even when the variable is set by mistake: on these environments the mail goes to real people
        $zachytavac = new ZachytavacEmailu($prostredi, 'http://localhost:11112', 'tajemstvi-brany');

        self::assertNull($zachytavac->getUrl());
    }

    public static function provideProstrediBezZachytavace(): iterable
    {
        yield 'beta' => [Prostredi::Beta];
        yield 'ostrá' => [Prostredi::Production];
    }
}
