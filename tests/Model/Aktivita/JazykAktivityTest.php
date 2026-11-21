<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\JazykAktivity;
use Gamecon\Jazyk\Jazyk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JazykAktivityTest extends TestCase
{
    public static function textyJazyku(): array
    {
        return [
            'kód'                       => ['en', JazykAktivity::ANGLICKY],
            'český název'               => ['anglicky', JazykAktivity::ANGLICKY],
            'velká písmena a mezery'    => ['  Česky I  Anglicky ', JazykAktivity::CESKY_I_ANGLICKY],
            'bez diakritiky'            => ['jazykove nezavisla', JazykAktivity::NEZAVISLA],
            'kód obou jazyků'           => ['cs-en', JazykAktivity::CESKY_I_ANGLICKY],
            'obvyklý zápis obou jazyků' => ['CZ/EN', JazykAktivity::CESKY_I_ANGLICKY],
            'anglický název angličtiny' => ['English', JazykAktivity::ANGLICKY],
            'čeština'                   => ['čeština', JazykAktivity::CESKY],
            'zkrácená nezávislá'        => ['nezávislá', JazykAktivity::NEZAVISLA],
        ];
    }

    #[DataProvider('textyJazyku')]
    public function testPoznaJazykZTextu(string $text, JazykAktivity $ocekavany): void
    {
        self::assertSame($ocekavany, JazykAktivity::zTextu($text));
    }

    public function testNeznamyNeboPrazdnyTextNeniJazyk(): void
    {
        self::assertNull(JazykAktivity::zTextu('klingonsky'));
        self::assertNull(JazykAktivity::zTextu('   '));
    }

    public function testKazdyJazykPoznaPodleSvehoNazvuIKodu(): void
    {
        foreach (JazykAktivity::cases() as $jazyk) {
            self::assertSame($jazyk, JazykAktivity::zTextu($jazyk->nazev()));
            self::assertSame($jazyk, JazykAktivity::zTextu($jazyk->value));
        }
    }

    public function testPopisekJeVJazyceStrankyANazevProImportZustavaCesky(): void
    {
        self::assertSame('in English', JazykAktivity::ANGLICKY->popisek(Jazyk::ANGLICTINA));
        self::assertSame('anglicky', JazykAktivity::ANGLICKY->popisek(Jazyk::CESTINA));
        self::assertSame('anglicky', JazykAktivity::ANGLICKY->nazev());
    }

    public function testNeznamyJazykZFormulareJeVychozi(): void
    {
        self::assertSame(JazykAktivity::ANGLICKY, JazykAktivity::zFormulare('en'));
        self::assertSame(JazykAktivity::vychozi(), JazykAktivity::zFormulare(''));
        self::assertSame(JazykAktivity::vychozi(), JazykAktivity::zFormulare('klingonsky'));
    }
}
