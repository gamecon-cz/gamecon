<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
use Gamecon\Uzivatel\Enum\UcastNaGc;
use Gamecon\Uzivatel\UpominaniDluzniku;
use PHPUnit\Framework\TestCase;

/**
 * The code of an e-mail text used to be joined from strings. Choosing it by type must give exactly the code
 * that join gave, and every code chosen must be a text the catalog knows.
 */
class KodSablonyPodleTypuTest extends TestCase
{
    /**
     * @test
     */
    public function kodStandardniUpominkyOdpovidaPuvodnimuSkladaniRetezce(): void
    {
        $upominaniDluzniku = (new \ReflectionClass(UpominaniDluzniku::class))->newInstanceWithoutConstructor();
        $vyberKodu = new \ReflectionMethod(UpominaniDluzniku::class, 'kodSablonyUpominky');

        foreach ([TypUpominky::TYDEN, TypUpominky::MESIC, TypUpominky::RUCNI] as $typ) {
            foreach (UcastNaGc::cases() as $ucast) {
                foreach ([null, 2019] as $rokPosledniUcasti) {
                    $varianta = match ($ucast) {
                        UcastNaGc::PRITOMEN      => 'pritomen',
                        UcastNaGc::JEN_PRIHLASEN => 'jen_prihlasen',
                        UcastNaGc::NEDORAZIL     => $rokPosledniUcasti === null
                            ? 'nedorazil_nikdy'
                            : 'nedorazil_drive',
                    };

                    $kod = $vyberKodu->invoke($upominaniDluzniku, $typ, $ucast, $rokPosledniUcasti);

                    self::assertSame(
                        'upominka_' . $typ->textovaVarianta()->value . '_' . $varianta,
                        $kod->value,
                    );
                    self::assertTrue(EmailoveSablonyKatalog::existuje($kod), $kod->value);
                }
            }
        }
    }

    /**
     * @test
     */
    public function kodInfoProCfoOUpominkachOdpovidaPuvodnimuSkladaniRetezce(): void
    {
        foreach (TypUpominky::cases() as $typ) {
            $kod = $typ->getKodSablonyInfoCfo();

            self::assertSame('cfo_upominky_' . $typ->value . '_odeslano', $kod->value);
            self::assertTrue(EmailoveSablonyKatalog::existuje($kod), $kod->value);
        }
    }

    /**
     * @test
     */
    public function kodyVarovaniOPromlceniOdpovidajiPuvodnimuSkladaniRetezce(): void
    {
        foreach (TypVarovaniPromlceni::cases() as $typ) {
            $varovani = $typ->getKodSablonyVarovani();
            $infoCfo = $typ->getKodSablonyInfoCfo();

            self::assertSame('promlceni_varovani_' . $typ->value, $varovani->value);
            self::assertSame('cfo_promlceni_varovani_' . $typ->value . '_odeslano', $infoCfo->value);
            self::assertTrue(EmailoveSablonyKatalog::existuje($varovani), $varovani->value);
            self::assertTrue(EmailoveSablonyKatalog::existuje($infoCfo), $infoCfo->value);
        }
    }
}
