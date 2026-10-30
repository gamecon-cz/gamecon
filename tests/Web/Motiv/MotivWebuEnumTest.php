<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Web\Motiv\MotivWebuEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MotivWebuEnumTest extends TestCase
{
    #[DataProvider('provideMotivy')]
    public function testMotivMaSvujStyl(MotivWebuEnum $motiv): void
    {
        self::assertFileExists(__DIR__ . '/../../../web/' . $motiv->getCssSoubor());
    }

    /**
     * @return array<string, array{0: MotivWebuEnum}>
     */
    public static function provideMotivy(): array
    {
        $motivy = [];
        foreach (MotivWebuEnum::cases() as $motiv) {
            $motivy[$motiv->value] = [$motiv];
        }

        return $motivy;
    }
}
