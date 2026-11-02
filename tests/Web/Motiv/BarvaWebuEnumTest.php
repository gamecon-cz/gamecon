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
     * The classic look has its own defaults, but a colour of the admin it uses has to be its variable, otherwise
     * the designer would change a colour nothing reads.
     */
    #[DataProvider('provideBarvy')]
    public function testKlasickyVzhledMaPromennou(BarvaWebuEnum $barva): void
    {
        $promenna = $barva->getCssPromennaKlasickehoVzhledu();
        self::assertSame(
            $promenna === null,
            $barva->getPouzitiVKlasickemVzhledu() === null,
            'Barva, kterou klasický vzhled používá, potřebuje proměnnou i popis pro grafika',
        );
        if ($promenna === null) {
            self::assertNotContains('--gc-' . $barva->value, array_keys(self::promenneKlasickehoVzhledu()));

            return;
        }
        self::assertArrayHasKey($promenna, self::promenneKlasickehoVzhledu(), "V :root _zaklad.less chybí {$promenna}");
    }

    /**
     * Each colour of the classic look comes from the admin, a variable nobody can change would not follow the year.
     */
    public function testKazdaPromennaKlasickehoVzhleduJdeZmenit(): void
    {
        $zAdminu = array_filter(array_map(
            static fn (BarvaWebuEnum $barva): ?string => $barva->getCssPromennaKlasickehoVzhledu(),
            BarvaWebuEnum::cases(),
        ));

        self::assertEqualsCanonicalizing(array_values($zAdminu), array_keys(self::promenneKlasickehoVzhledu()));
    }

    /**
     * @return array<string, string> '--gc-…' => value, from :root of the classic look
     */
    private static function promenneKlasickehoVzhledu(): array
    {
        $less = file_get_contents(__DIR__ . '/../../../web/soubory/blackarrow/_spolecne/_zaklad.less');
        self::assertIsString($less);
        self::assertMatchesRegularExpression('~:root\s*\{(?<promenne>[^}]*)}~', $less);
        preg_match('~:root\s*\{(?<promenne>[^}]*)}~', $less, $root);
        preg_match_all('~(?<nazev>--gc-[a-z-]+):\s*(?<hodnota>[^;]+);~', $root['promenne'], $promenne);

        return array_combine($promenne['nazev'], $promenne['hodnota']);
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
