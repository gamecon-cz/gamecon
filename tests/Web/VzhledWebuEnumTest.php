<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web;

use Gamecon\Web\VzhledWebuEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VzhledWebuEnumTest extends TestCase
{
    /**
     * Bloky, které parsuje web/moduly/titulka.php: bez kteréhokoli z nich titulka v daném vzhledu spadne.
     */
    private const array BLOKY_TITULKY = ['linie', 'odpocetPrihlasit', 'karty', 'cisla', 'fotky', 'sponzor', 'partner'];

    public function testModerniVariantyJsouAzD(): void
    {
        self::assertSame(
            ['A', 'B', 'C', 'D'],
            array_map(
                static fn (VzhledWebuEnum $vzhled) => $vzhled->getPismenoVarianty(),
                VzhledWebuEnum::getModerni(),
            ),
        );
    }

    public function testKazdyVzhledKromeKlasickehoJeModerni(): void
    {
        foreach (VzhledWebuEnum::cases() as $vzhled) {
            self::assertSame($vzhled !== VzhledWebuEnum::KLASICKY, $vzhled->isModerni(), $vzhled->value);
        }
        self::assertSame(VzhledWebuEnum::cases(), [VzhledWebuEnum::KLASICKY, ...VzhledWebuEnum::getModerni()]);
    }

    public function testKlasickyNemaVariantu(): void
    {
        $klasicky = VzhledWebuEnum::KLASICKY;

        self::assertNull($klasicky->getPismenoVarianty());
        self::assertNull($klasicky->getNazevVarianty());
        self::assertNull($klasicky->getAdresarSablonVarianty());
        self::assertNull($klasicky->getSouborVarianty('css'));
        self::assertSame('blackarrow', $klasicky->getAdresarSablon());
    }

    #[DataProvider('provideModerniVarianty')]
    public function testVariantaMaNazevPopisASdilenouModerniKostru(VzhledWebuEnum $vzhled): void
    {
        self::assertNotEmpty($vzhled->getNazevVarianty());
        self::assertNotEmpty($vzhled->getPopisVarianty());
        self::assertSame('moderni', $vzhled->getAdresarSablon());
    }

    /**
     * Varianta s vlastní titulkou musí mít i vlastní styly, jinak by se titulka ukázala nenastylovaná.
     */
    #[DataProvider('provideModerniVarianty')]
    public function testVlastniTitulkaVariantyMaVsechnyBlokyAStyly(VzhledWebuEnum $vzhled): void
    {
        $titulka = __DIR__ . '/../../web/sablony/' . $vzhled->getAdresarSablonVarianty() . '/titulka.xtpl';
        if (! is_file($titulka)) {
            self::assertSame(VzhledWebuEnum::MODERNI, $vzhled, 'vlastní titulku nemá jen varianta D');

            return;
        }
        self::assertFileExists(__DIR__ . '/../../web/' . $vzhled->getSouborVarianty('css'));
        $obsah = (string) file_get_contents($titulka);
        foreach (self::BLOKY_TITULKY as $blok) {
            self::assertMatchesRegularExpression('~<!--\s*begin:\s*' . $blok . '\s*-->~', $obsah, "chybí blok {$blok}");
        }
    }

    /**
     * @return array<string, array{VzhledWebuEnum}>
     */
    public static function provideModerniVarianty(): array
    {
        $varianty = [];
        foreach (VzhledWebuEnum::getModerni() as $vzhled) {
            $varianty[$vzhled->value] = [$vzhled];
        }

        return $varianty;
    }
}
