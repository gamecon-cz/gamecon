<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\VolnoProEnum;
use Gamecon\Uzivatel\Pohlavi;
use PHPUnit\Framework\TestCase;

class VolnoProEnumTest extends TestCase
{
    public function testPohlaviSVolnymMistem(): void
    {
        self::assertSame(
            [Pohlavi::MUZ_KOD, Pohlavi::ZENA_KOD],
            VolnoProEnum::PRO_VSECHNY->pohlaviSVolnymMistem(),
        );
        self::assertSame([Pohlavi::MUZ_KOD], VolnoProEnum::JEN_MUZI->pohlaviSVolnymMistem());
        self::assertSame([Pohlavi::ZENA_KOD], VolnoProEnum::JEN_ZENY->pohlaviSVolnymMistem());
        self::assertSame([], VolnoProEnum::PLNO->pohlaviSVolnymMistem());
    }

    public function testProVsechnyJeVolnoProObePohlavi(): void
    {
        self::assertTrue(VolnoProEnum::PRO_VSECHNY->proPohlaviJeVolno(Pohlavi::MUZ_KOD));
        self::assertTrue(VolnoProEnum::PRO_VSECHNY->proPohlaviJeVolno(Pohlavi::ZENA_KOD));
    }

    public function testPlnoNeniVolnoProNikoho(): void
    {
        self::assertFalse(VolnoProEnum::PLNO->proPohlaviJeVolno(Pohlavi::MUZ_KOD));
        self::assertFalse(VolnoProEnum::PLNO->proPohlaviJeVolno(Pohlavi::ZENA_KOD));
    }

    public function testGenderoveOmezenyStavJeVolnoJenProSvePohlavi(): void
    {
        self::assertTrue(VolnoProEnum::JEN_ZENY->proPohlaviJeVolno(Pohlavi::ZENA_KOD));
        self::assertFalse(VolnoProEnum::JEN_ZENY->proPohlaviJeVolno(Pohlavi::MUZ_KOD));

        self::assertTrue(VolnoProEnum::JEN_MUZI->proPohlaviJeVolno(Pohlavi::MUZ_KOD));
        self::assertFalse(VolnoProEnum::JEN_MUZI->proPohlaviJeVolno(Pohlavi::ZENA_KOD));
    }

    public function testBackingHodnotyOdpovidajiPohlaviKodum(): void
    {
        // odhlas() posílá volnoPro->value do SQL dotazu proti sloupci pohlaví – hodnoty musí sedět
        self::assertSame(Pohlavi::ZENA_KOD, VolnoProEnum::JEN_ZENY->value);
        self::assertSame(Pohlavi::MUZ_KOD, VolnoProEnum::JEN_MUZI->value);
    }
}
