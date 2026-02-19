<?php

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeCzTrait;
use PHPUnit\Framework\TestCase;

class DateTimeCzTraitTest extends TestCase
{

    /**
     * @test
     * @dataProvider dataRelativni
     */
    public function Relativni_vraci_spravny_retezec(string $cas, string $ted, string $ocekavany): void
    {
        $dateTime = new class($cas) extends \DateTime {
            use DateTimeCzTrait;
        };
        $tedDt = new \DateTime($ted);
        self::assertSame($ocekavany, $dateTime->relativni($tedDt));
    }

    public static function dataRelativni(): array
    {
        return [
            'v budoucnosti'    => ['2025-06-01 12:00:01', '2025-06-01 12:00:00', 'v budoucnosti'],
            'před okamžikem 0' => ['2025-06-01 12:00:00', '2025-06-01 12:00:00', 'před okamžikem'],
            'před okamžikem 1' => ['2025-06-01 11:59:59', '2025-06-01 12:00:00', 'před okamžikem'],
            'před sekundami'   => ['2025-06-01 11:59:30', '2025-06-01 12:00:00', 'před 30 sekundami'],
            'před minutou'     => ['2025-06-01 11:59:00', '2025-06-01 12:00:00', 'před minutou'],
            'před minutami'    => ['2025-06-01 11:55:00', '2025-06-01 12:00:00', 'před 5 minutami'],
            'dnes G:i'         => ['2025-06-01 09:05:00', '2025-06-01 12:00:00', '9:05'],
            'včera'            => ['2025-05-31 10:00:00', '2025-06-01 12:00:00', 'včera'],
            'předevčírem'      => ['2025-05-30 10:00:00', '2025-06-01 12:00:00', 'předevčírem'],
            'před 5 dny'       => ['2025-05-27 10:00:00', '2025-06-01 12:00:00', 'před 5 dny'],
        ];
    }

    /**
     * @test
     * @dataProvider dataStari
     */
    public function Stari_vraci_spravny_retezec(string $cas, string $ted, string $ocekavany): void
    {
        $dateTime = new class($cas) extends \DateTime {
            use DateTimeCzTrait;
        };
        $tedDt = new \DateTime($ted);
        self::assertSame($ocekavany, $dateTime->stari($tedDt));
    }

    public static function dataStari(): array
    {
        return [
            'okamžik budoucnost' => ['2025-06-01 12:00:01', '2025-06-01 12:00:00', 'okamžik'],
            'okamžik 0'          => ['2025-06-01 12:00:00', '2025-06-01 12:00:00', 'okamžik'],
            'okamžik 1'          => ['2025-06-01 11:59:59', '2025-06-01 12:00:00', 'okamžik'],
            '30 sekund'          => ['2025-06-01 11:59:30', '2025-06-01 12:00:00', '30 sekund'],
            'minuta'             => ['2025-06-01 11:59:00', '2025-06-01 12:00:00', 'minuta'],
            '5 minut'            => ['2025-06-01 11:55:00', '2025-06-01 12:00:00', '5 minut'],
            'dnes G:i'           => ['2025-06-01 09:05:00', '2025-06-01 12:00:00', '9:05'],
            'včera'              => ['2025-05-31 10:00:00', '2025-06-01 12:00:00', 'včera'],
            'předevčírem'        => ['2025-05-30 10:00:00', '2025-06-01 12:00:00', 'předevčírem'],
            '5 dní'              => ['2025-05-27 10:00:00', '2025-06-01 12:00:00', '5 dní'],
        ];
    }

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
