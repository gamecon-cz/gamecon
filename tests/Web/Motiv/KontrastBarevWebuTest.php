<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Web\Motiv\BarvaWebuEnum;
use Gamecon\Web\Motiv\BarvyWebu;
use Gamecon\Web\Motiv\KontrastBarevWebu;
use PHPUnit\Framework\TestCase;

class KontrastBarevWebuTest extends TestCase
{
    public function testKontrastPodleWcag(): void
    {
        self::assertEqualsWithDelta(21.0, KontrastBarevWebu::kontrast('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(21.0, KontrastBarevWebu::kontrast('#ffffff', '#000000'), 0.001);
        self::assertEqualsWithDelta(1.0, KontrastBarevWebu::kontrast('#e22630', '#e22630'), 0.001);
        // the red of GameCon under white text, just above the minimum
        self::assertEqualsWithDelta(4.61, KontrastBarevWebu::kontrast('#ffffff', '#e22630'), 0.01);
    }

    public function testVychoziBarvyJsouCitelne(): void
    {
        self::assertSame([], KontrastBarevWebu::varovani(BarvyWebu::vychozi()));
    }

    public function testVarujePredSvetlouHlavniBarvou(): void
    {
        $varovani = KontrastBarevWebu::varovani(BarvyWebu::zPole([
            BarvaWebuEnum::CERVENA->value => '#ffd500',
        ]));

        self::assertNotEmpty($varovani);
        self::assertStringContainsString('Bílý text na hlavní barvě', $varovani[0]);
        self::assertStringContainsString('4,5:1', $varovani[0]);
    }
}
