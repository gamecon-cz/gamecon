<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DateTimeCzTrait;
use PHPUnit\Framework\TestCase;

class DateTimeCzTraitTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider dataRelativni
     */
    public function relativniVraciSpravnyRetezec(string $cas, string $ted, string $ocekavany): void
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
     *
     * @dataProvider dataStari
     */
    public function stariVraciSpravnyRetezec(string $cas, string $ted, string $ocekavany): void
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
            'dnes hodiny minuty' => ['2025-06-01 09:05:00', '2025-06-01 12:00:00', '2 hodiny 55 minut'],
            'dnes 1 hodina'      => ['2025-06-01 11:00:00', '2025-06-01 12:00:00', '1 hodina'],
            'dnes 2 hodiny'      => ['2025-06-01 10:00:00', '2025-06-01 12:00:00', '2 hodiny'],
            'včera'              => ['2025-05-31 10:00:00', '2025-06-01 12:00:00', 'včera'],
            'předevčírem'        => ['2025-05-30 10:00:00', '2025-06-01 12:00:00', 'předevčírem'],
            '3 dny'              => ['2025-05-29 10:00:00', '2025-06-01 12:00:00', '3 dny'],
            '4 dny'              => ['2025-05-28 10:00:00', '2025-06-01 12:00:00', '4 dny'],
            '5 dní'              => ['2025-05-27 10:00:00', '2025-06-01 12:00:00', '5 dní'],
        ];
    }

    /**
     * @test
     */
    public function muzuZaokrouhlitNaHodinyNahoru()
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
