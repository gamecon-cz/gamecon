<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\JazykAktivity;
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
}
