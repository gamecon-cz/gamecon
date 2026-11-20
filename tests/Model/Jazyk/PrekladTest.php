<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\Preklad;
use PHPUnit\Framework\TestCase;

/**
 * Chování překladů nad fixture katalogy (`resources/preklady`), nezávisle na textech webu.
 */
class PrekladTest extends TestCase
{
    public const string ADRESAR_FIXTURE = __DIR__ . '/resources/preklady';

    protected function setUp(): void
    {
        Preklad::setAdresarKatalogu(self::ADRESAR_FIXTURE);
    }

    protected function tearDown(): void
    {
        Preklad::setAdresarKatalogu(null);
        AktualniJazyk::reset();
    }

    public function testPrekladVJazyce(): void
    {
        self::assertSame('Ahoj', Preklad::t('test.pozdrav', [], Jazyk::CESTINA));
        self::assertSame('Hello', Preklad::t('test.pozdrav', [], Jazyk::ANGLICTINA));

        AktualniJazyk::nastav(Jazyk::ANGLICTINA);
        self::assertSame('Hello', Preklad::t('test.pozdrav'), 'Bez jazyka platí jazyk požadavku.');
    }

    public function testChybejiciAnglickyTextSpadneNaCestinuSCeskymMnoznymCislem(): void
    {
        // anglická pravidla by pro 3 vybrala „other“ (3 míst), české „few“
        self::assertSame('1 místo', Preklad::t('test.jen_cesky', [
            'pocet' => 1,
        ], Jazyk::ANGLICTINA));
        self::assertSame('3 místa', Preklad::t('test.jen_cesky', [
            'pocet' => 3,
        ], Jazyk::ANGLICTINA));
        self::assertSame('5 míst', Preklad::t('test.jen_cesky', [
            'pocet' => 5,
        ], Jazyk::ANGLICTINA));
    }

    public function testChybejiciKlicVratiSamSebe(): void
    {
        self::assertSame('test.neexistuje', Preklad::t('test.neexistuje', [], Jazyk::ANGLICTINA));
    }

    public function testVraciProstyTextVcetneParametru(): void
    {
        self::assertSame('Řekl "ahoj" & odešel', Preklad::t('test.uvozovky', [], Jazyk::CESTINA));
        self::assertSame('He said "hi" & left', \t('test.uvozovky', [], Jazyk::ANGLICTINA));
        self::assertSame(
            'Welcome, <b>Tom & Jerry</b>',
            Preklad::t('test.parametr', [
                'jmeno' => '<b>Tom & Jerry</b>',
            ], Jazyk::ANGLICTINA),
            'Parametr se vkládá bez úprav, escapuje až ten, kdo výsledek vkládá do HTML.',
        );
    }

    /**
     * Git checkout dá fixture i katalogům webu stejný čas změny. Klíč cache Translatoru z něj
     * se ale pro fixture nesmí shodovat s klíčem katalogů webu, jinak by si přečetly cizí cache.
     */
    public function testFixtureNesdiliCacheSKatalogyWebu(): void
    {
        Preklad::setAdresarKatalogu(null);
        $casyZmenWebu = [];
        foreach (Jazyk::cases() as $jazyk) {
            $casyZmenWebu[$jazyk->value] = filemtime(Preklad::souborKatalogu($jazyk));
        }
        Preklad::setAdresarKatalogu(self::ADRESAR_FIXTURE);
        foreach (Jazyk::cases() as $jazyk) {
            touch(Preklad::souborKatalogu($jazyk), $casyZmenWebu[$jazyk->value]);
        }
        clearstatcache();

        self::assertSame('Ahoj', Preklad::t('test.pozdrav', [], Jazyk::CESTINA));

        Preklad::setAdresarKatalogu(null);
        self::assertSame('program', Preklad::t('menu.program', [], Jazyk::CESTINA));
        self::assertSame('programme', Preklad::t('menu.program', [], Jazyk::ANGLICTINA));

        Preklad::setAdresarKatalogu(self::ADRESAR_FIXTURE);
        self::assertSame('Hello', Preklad::t('test.pozdrav', [], Jazyk::ANGLICTINA));
    }
}
