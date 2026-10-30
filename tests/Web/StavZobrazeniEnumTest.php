<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web;

use Gamecon\Web\StavZobrazeniEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StavZobrazeniEnumTest extends TestCase
{
    #[DataProvider('provideStavy')]
    public function testStavPodleObdobi(?string $od, ?string $do, StavZobrazeniEnum $ocekavany): void
    {
        $kdy = new \DateTimeImmutable('2027-07-17 12:00');

        self::assertSame(
            $ocekavany,
            StavZobrazeniEnum::zObdobi(
                $od !== null ? new \DateTimeImmutable($od) : null,
                $do !== null ? new \DateTimeImmutable($do) : null,
                $kdy,
            ),
        );
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: StavZobrazeniEnum}>
     */
    public static function provideStavy(): array
    {
        return [
            'bez omezení'      => [null, null, StavZobrazeniEnum::ZOBRAZUJE_SE],
            'už začalo'        => ['2027-07-17 11:00', null, StavZobrazeniEnum::ZOBRAZUJE_SE],
            'začíná právě teď' => ['2027-07-17 12:00', null, StavZobrazeniEnum::ZOBRAZUJE_SE],
            'ještě nezačalo'   => ['2027-07-17 13:00', null, StavZobrazeniEnum::NAPLANOVANO],
            'končí později'    => [null, '2027-07-17 13:00', StavZobrazeniEnum::ZOBRAZUJE_SE],
            'končí právě teď'  => [null, '2027-07-17 12:00', StavZobrazeniEnum::SKONCILO],
            'skončilo'         => ['2027-07-16 10:00', '2027-07-16 12:00', StavZobrazeniEnum::SKONCILO],
        ];
    }
}
