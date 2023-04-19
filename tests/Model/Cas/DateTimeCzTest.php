<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeCz;
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
}
