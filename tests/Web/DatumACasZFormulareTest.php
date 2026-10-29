<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web;

use Gamecon\Web\DatumACasZFormulare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatumACasZFormulareTest extends TestCase
{
    public function testPrazdnePoleJeNull(): void
    {
        self::assertNull(DatumACasZFormulare::zVstupu(''));
        self::assertNull(DatumACasZFormulare::zVstupu(null));
        self::assertSame('', DatumACasZFormulare::doVstupu(null));
    }

    public function testTamAZpet(): void
    {
        $datum = DatumACasZFormulare::zVstupu('2027-07-17T14:30');

        self::assertSame('2027-07-17 14:30:00', $datum?->format('Y-m-d H:i:s'));
        self::assertSame('2027-07-17T14:30', DatumACasZFormulare::doVstupu($datum));
    }

    public function testSeSekundami(): void
    {
        self::assertSame(
            '2027-07-17 14:30:15',
            DatumACasZFormulare::zVstupu('2027-07-17T14:30:15')?->format('Y-m-d H:i:s'),
        );
    }

    #[DataProvider('provideNeplatne')]
    public function testNeplatneDatumOdmitne(string $hodnota): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DatumACasZFormulare::zVstupu($hodnota);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideNeplatne(): array
    {
        return [
            'nesmysl'          => ['zítra'],
            'neexistující den' => ['2027-02-30T10:00'],
            'české datum'      => ['17. 7. 2027 14:30'],
            'jen datum'        => ['2027-07-17'],
        ];
    }
}
