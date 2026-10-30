<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Web\Motiv\BarvaWebuEnum;
use Gamecon\Web\Motiv\BarvyWebu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BarvaWebuEnumTest extends TestCase
{
    /**
     * The defaults are a copy of :root in moderni.css, a colour changed there has to change here as well,
     * otherwise the admin would offer a "default" the web does not have.
     */
    #[DataProvider('provideBarvy')]
    public function testVychoziHodnotaOdpovidaModerniCss(BarvaWebuEnum $barva): void
    {
        $css = file_get_contents(__DIR__ . '/../../../web/soubory/moderni/moderni.css');
        self::assertIsString($css);
        self::assertMatchesRegularExpression('~:root\s*\{(?<promenne>[^}]*)}~', $css);
        preg_match('~:root\s*\{(?<promenne>[^}]*)}~', $css, $root);
        preg_match('~' . preg_quote($barva->getCssPromenna(), '~') . ':\s*(?<hodnota>#[0-9a-fA-F]+)\s*;~', $root['promenne'], $promenna);

        self::assertArrayHasKey('hodnota', $promenna, "V :root moderni.css chybí {$barva->getCssPromenna()}");
        self::assertSame(BarvyWebu::normalizujHodnotu($promenna['hodnota']), $barva->getVychoziHodnota());
    }

    /**
     * @return array<string, array{0: BarvaWebuEnum}>
     */
    public static function provideBarvy(): array
    {
        $barvy = [];
        foreach (BarvaWebuEnum::cases() as $barva) {
            $barvy[$barva->value] = [$barva];
        }

        return $barvy;
    }
}
