<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeImmutableStrict;
use PHPUnit\Framework\TestCase;

class DateTimeCzTest extends TestCase
{
    /**
     * @dataProvider provideDenNaPrelom
     */
    public function testDenNaPrelomDnuVeZkratkach(string $den, ?string $oddelovac, string $ocekavame)
    {
        self::assertSame($ocekavame, DateTimeCz::denNaPrelomDnuVeZkratkach($den, $oddelovac ?? ' - '));
    }

    public static function provideDenNaPrelom(): array
    {
        return [
            'neposlední den v týdnu bez diakritiky'                  => ['pondeli', null, 'po - út'],
            'neposlední den v týdnu s diakritikou a velkým písmenem' => ['Středa', null, 'St - Čt'],
            'poslední den v týdnu'                                   => ['neděle', null, 'ne - po'],
            'poslední den v týdnu s vlastním oddělovačem'            => ['Neděle', '!@#$%^&*', 'Ne!@#$%^&*Po'],
            'den s bílými znaky okolo'                               => ['     Neděle ', null, 'Ne - Po'],
        ];
    }

    /**
     * @dataProvider provideTridyDatumu
     */
    public function testZTimestampuJeVeVychoziCasoveZone(string $trida)
    {
        $puvodniZona = date_default_timezone_get();
        date_default_timezone_set('Europe/Prague');
        try {
            $zaloha = $trida::zTimestampu(1790132473); // 2026-09-23 03:01:13 UTC

            self::assertSame('2026-09-23 05:01:13', $zaloha->format(DateTimeCz::FORMAT_DB));
            self::assertSame('Europe/Prague', $zaloha->getTimezone()->getName());
            self::assertSame(1790132473, $zaloha->getTimestamp());
        } finally {
            date_default_timezone_set($puvodniZona);
        }
    }

    public static function provideTridyDatumu(): array
    {
        return [
            'měnitelný'   => [DateTimeCz::class],
            'neměnitelný' => [DateTimeImmutableStrict::class],
        ];
    }
}
