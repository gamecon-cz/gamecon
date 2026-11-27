<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\OdkazNotifikace;
use Gamecon\PushNotifikace\PopisZarizeni;
use Gamecon\PushNotifikace\TestovaciNotifikace;
use Gamecon\PushNotifikace\TypNotifikace;
use Gamecon\PushNotifikace\Urgence;
use Gamecon\PushNotifikace\VapidKlice;
use Gamecon\PushNotifikace\ZpravaNotifikace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Co smí být v notifikaci: délky, odkaz jen na web GameConu, doručení podle typu.
 */
class ZpravaNotifikaceTest extends TestCase
{
    use PushNotifikaceTestTrait;

    #[DataProvider('platneOdkazy')]
    public function testOdkazNaWebGameconuProjde(?string $odkaz, string $ocekavany): void
    {
        self::assertSame($ocekavany, OdkazNotifikace::normalizuj($odkaz));
    }

    /**
     * @return \Generator<string, array{string|null, string}>
     */
    public static function platneOdkazy(): \Generator
    {
        yield 'bez odkazu' => [null, ''];
        yield 'prázdný' => ['  ', ''];
        yield 'Můj program' => ['program/muj', 'program/muj'];
        yield 'úvodní lomítko se zahodí' => ['/program/muj', 'program/muj'];
        yield 'jen lomítko' => ['/', ''];
        yield 'dotaz a kotva' => ['program?den=3#patek', 'program?den=3#patek'];
        yield 'tečky v názvu' => ['soubory/obr.v2.png', 'soubory/obr.v2.png'];
    }

    #[DataProvider('neplatneOdkazy')]
    public function testOdkazMimoWebNeprojde(string $odkaz): void
    {
        $this->expectException(ChybaPushNotifikace::class);

        OdkazNotifikace::normalizuj($odkaz);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function neplatneOdkazy(): \Generator
    {
        yield 'celá adresa' => ['https://example.com/program'];
        yield 'celá adresa GameConu' => ['https://gamecon.cz/program'];
        yield 'adresa bez schématu' => ['//example.com/program'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript s velkými písmeny' => ['JaVaScRiPt:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'zpětné lomítko' => ['\\\\example.com'];
        yield 'lomítko a zpětné lomítko' => ['/\\example.com'];
        yield 'o složku výš' => ['../admin'];
        yield 'o složku výš uprostřed' => ['program/../../admin'];
        yield 'o složku výš před dotazem' => ['program/..?x=1'];
        yield 'zakódované tečky' => ['%2e%2e/admin'];
        yield 'zakódované tečky velkými písmeny' => ['program/%2E%2E/%2E%2E/admin'];
        yield 'zakódovaná jedna tečka z dvojice' => ['.%2e/admin'];
        yield 'zakódované lomítko' => ['..%2fadmin'];
        yield 'zakódované zpětné lomítko' => ['%5c%5cexample.com'];
        yield 'mezera' => ['program muj'];
        yield 'konec řádku' => ["program\nmuj"];
        yield 'tabulátor před schématem' => ["java\tscript:alert(1)"];
        yield 'moc dlouhý' => [str_repeat('a', OdkazNotifikace::MAX_DELKA + 1)];
    }

    public function testAdresaOdkazuJeVzdyNaWebuProstredi(): void
    {
        self::assertSame('https://gamecon.cz/program/muj', OdkazNotifikace::getUrl('https://gamecon.cz', 'program/muj'));
        self::assertSame('http://localhost/web/program/muj', OdkazNotifikace::getUrl('http://localhost/web/', 'program/muj'));
        self::assertSame('https://gamecon.cz/', OdkazNotifikace::getUrl('https://gamecon.cz', ''));
    }

    public function testZpravaBereDoruceniZTypu(): void
    {
        $zprava = ZpravaNotifikace::vytvor(TypNotifikace::PRIPOMINKA_AKTIVITY, ' Nadpis ', ' Text ', '/program/muj');

        self::assertSame('Nadpis', $zprava->nadpis);
        self::assertSame('Text', $zprava->text);
        self::assertSame('program/muj', $zprava->odkaz);
        self::assertSame(15 * 60, $zprava->ttl, 'Připomínka „za 15 minut“ po čtvrthodině nemá smysl.');
        self::assertSame(Urgence::VYSOKA, $zprava->urgence);
        self::assertSame('aktivita', $zprava->tag);
    }

    public function testDoruceniJdePrepsat(): void
    {
        $zprava = ZpravaNotifikace::vytvor(
            TypNotifikace::OBECNA,
            'Nadpis',
            '',
            ttl: 60,
            urgence: Urgence::NIZKA,
            tag: 'vlastni_tag-1',
        );

        self::assertSame(60, $zprava->ttl);
        self::assertSame(Urgence::NIZKA, $zprava->urgence);
        self::assertSame('vlastni_tag-1', $zprava->tag);
    }

    public function testPayloadNeseCelouAdresuNaWebu(): void
    {
        $zprava = ZpravaNotifikace::vytvor(TypNotifikace::ZMENA_AKTIVITY, 'Změna „času“', "Řádek\ndruhý", 'program/muj');

        self::assertSame(
            [
                'title' => 'Změna „času“',
                'body'  => "Řádek\ndruhý",
                'url'   => 'https://beta.gamecon.cz/program/muj',
                'tag'   => 'aktivita',
            ],
            json_decode($zprava->getPayload('https://beta.gamecon.cz'), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    #[DataProvider('neplatneZpravy')]
    public function testNeplatnaZpravaNevznikne(callable $vytvor): void
    {
        $this->expectException(ChybaPushNotifikace::class);

        $vytvor();
    }

    /**
     * @return \Generator<string, array{callable(): ZpravaNotifikace}>
     */
    public static function neplatneZpravy(): \Generator
    {
        $typ = TypNotifikace::OBECNA;
        yield 'bez nadpisu' => [
            static fn () => ZpravaNotifikace::vytvor($typ, '   ', 'Text'),
        ];
        yield 'dlouhý nadpis' => [
            static fn () => ZpravaNotifikace::vytvor($typ, str_repeat('ž', ZpravaNotifikace::MAX_DELKA_NADPISU + 1), ''),
        ];
        yield 'dlouhý text' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', str_repeat('ž', ZpravaNotifikace::MAX_DELKA_TEXTU + 1)),
        ];
        yield 'odkaz jinam' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', '', 'https://example.com'),
        ];
        yield 'záporné TTL' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', '', ttl: -1),
        ];
        yield 'TTL přes čtyři týdny' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', '', ttl: ZpravaNotifikace::MAX_TTL + 1),
        ];
        yield 'tag s mezerou' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', '', tag: 'dva tagy'),
        ];
        yield 'nadpis s neplatným UTF-8' => [
            static fn () => ZpravaNotifikace::vytvor($typ, "Nadpis \xFF", 'Text'),
        ];
        yield 'text s neplatným UTF-8' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', "Text \xC3"),
        ];
        yield 'dlouhý tag' => [
            static fn () => ZpravaNotifikace::vytvor($typ, 'Nadpis', '', tag: str_repeat('a', 33)),
        ];
    }

    public function testNadpisATextNaHraneDelkyProjdou(): void
    {
        $zprava = ZpravaNotifikace::vytvor(
            TypNotifikace::OBECNA,
            str_repeat('🎲', ZpravaNotifikace::MAX_DELKA_NADPISU),
            str_repeat('🎲', ZpravaNotifikace::MAX_DELKA_TEXTU),
            str_repeat('a', OdkazNotifikace::MAX_DELKA),
        );

        self::assertSame(ZpravaNotifikace::MAX_DELKA_TEXTU, mb_strlen($zprava->text));
        // i samé znaky po čtyřech bajtech se vejdou pod mez, kterou berou všechny push služby
        self::assertLessThan(3052, strlen($zprava->getPayload('https://dlouhy-nazev-vetve.preview.gamecon.cz')));
    }

    public function testKazdaTestovaciNotifikaceJeOznacenaAPlatna(): void
    {
        foreach (TestovaciNotifikace::cases() as $testovaci) {
            $zprava = $testovaci->getZprava();

            self::assertStringEndsWith(
                TestovaciNotifikace::OZNACENI,
                $zprava->nadpis,
                "Testovací notifikace {$testovaci->value} musí mít v nadpisu označení, aby ji příjemce nevzal vážně.",
            );
            self::assertNotSame('', $testovaci->getPopisek());
            self::assertNotSame('', $testovaci->getCoOveruje());
        }
    }

    public function testZmenaAktivityNahradiPripominku(): void
    {
        self::assertSame(
            TestovaciNotifikace::PRIPOMINKA_AKTIVITY->getZprava()->tag,
            TestovaciNotifikace::ZMENA_AKTIVITY->getZprava()->tag,
        );
        self::assertNotNull(TestovaciNotifikace::ZMENA_AKTIVITY->getZprava()->tag);
    }

    public function testPripominkaVedeNaMujProgram(): void
    {
        self::assertSame('program/muj', TestovaciNotifikace::PRIPOMINKA_AKTIVITY->getZprava()->odkaz);
    }

    #[DataProvider('userAgenti')]
    public function testPopisZarizeniZUserAgenta(string $userAgent, string $ocekavany): void
    {
        self::assertSame($ocekavany, PopisZarizeni::zUserAgenta($userAgent));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function userAgenti(): \Generator
    {
        yield 'Chrome na Androidu' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Chrome, Android'];
        yield 'Safari na iPhonu' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'Safari, iOS'];
        yield 'Edge na Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'Edge, Windows'];
        yield 'Firefox na Linuxu' => ['Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox, Linux'];
        yield 'Safari na Macu' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', 'Safari, macOS'];
        yield 'neznámý' => ['curl/8.5.0', PopisZarizeni::NEZNAME];
        yield 'prázdný' => ['', PopisZarizeni::NEZNAME];
    }

    public function testVapidKliceBezPlatnehoParuNejsou(): void
    {
        $platne = self::vapidKlice();

        self::assertNull(VapidKlice::zHodnot('', '', self::URL_WEBU_TESTU));
        self::assertNull(VapidKlice::zHodnot($platne->verejny, 'kratky', self::URL_WEBU_TESTU));
        self::assertNull(VapidKlice::zHodnot('neni base64url!', $platne->getKonfiguraceKnihovny()['privateKey'], self::URL_WEBU_TESTU));
    }

    public function testVapidSubjectJeAdresaWebuJenNaHttps(): void
    {
        $klice = self::vapidKlice()->getKonfiguraceKnihovny();

        self::assertSame('https://gamecon.cz', VapidKlice::zHodnot($klice['publicKey'], $klice['privateKey'], 'https://gamecon.cz')?->subject);
        self::assertStringStartsWith('mailto:', (string) VapidKlice::zHodnot($klice['publicKey'], $klice['privateKey'], 'http://localhost/web')?->subject);
    }
}
