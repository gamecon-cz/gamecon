<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeCzTrait;
use PHPUnit\Framework\TestCase;

class DateTimeCzTraitTest extends TestCase
{

    /**
     * @test
     */
    public function Muzu_zaokrouhlit_na_hodiny_nahoru()
    {
        $dateTime = new class('2023-07-19 18:17') extends \DateTime {
            use DateTimeCzTrait;
        };
        self::assertSame(
            '2023-07-19 19:00:00',
            $dateTime->zaokrouhlitNaHodinyNahoru()->format('Y-m-d H:i:s'),
        );

        $dateTimeImmutable = new class('2023-07-20 12:01') extends \DateTimeImmutable {
            use DateTimeCzTrait;
        };
        self::assertSame(
            '2023-07-20 13:00:00',
            $dateTimeImmutable->zaokrouhlitNaHodinyNahoru()->format('Y-m-d H:i:s'),
        );
    }
}
