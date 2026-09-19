<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\RazeniTypuUbytovani;
use PHPUnit\Framework\TestCase;

class RazeniTypuUbytovaniTest extends TestCase
{
    /**
     * @param string[] $ocekavanePoradi
     */
    private function overPoradi(array $ocekavanePoradi): void
    {
        $nahodnePoradi = $ocekavanePoradi;
        shuffle($nahodnePoradi);

        $serazene = (new RazeniTypuUbytovani())->serad(array_fill_keys($nahodnePoradi, true));

        self::assertSame($ocekavanePoradi, array_keys($serazene));
    }

    public function testCollegeBedsComeBeforeHotelRooms(): void
    {
        $this->overPoradi([
            'Jednolůžák',
            'Dvoulůžák',
            'Dvojlůžák',
            'Trojlůžák',
            'Spacák',
            'Hotelový jednolůžák standard',
            'Hotelový dvojlůžák standard',
            'Hotelový jednolůžák deluxe (buňka)',
            'Hotelový jednolůžák deluxe',
            'Hotelový dvojlůžák deluxe',
        ]);
    }

    /**
     * Názvy se mezi ročníky mění, takže popisná řada musí dopadnout stejně jako krátká.
     */
    public function testDescriptiveNamesSortTheSameWay(): void
    {
        $this->overPoradi([
            'Postel na "1L" koleji',
            'Postel na 2L koleji',
            'Postel na 3L koleji',
            'Postel na 1L hotelu se snídaní',
            'Postel na 2L hotelu se snídaní',
            'Postel na 1L hotelu deluxe se snídaní - dvojbuňka',
            'Postel na 1L hotelu deluxe se snídaní',
            'Postel na 2L hotelu deluxe se snídaní',
        ]);
    }

    /**
     * Varianta („(A)", „(C)") se řadí ke svému základnímu typu, ne zvlášť.
     */
    public function testVariantsSortWithTheirBaseType(): void
    {
        $this->overPoradi([
            'Jednolůžák (C)',
            'Dvojlůžák (A)',
            'Spacák',
            'Hotelový dvoulůžák standard (A)',
            'Hotelový jednolůžák deluxe (buňka) (A)',
            'Hotelový jednolůžák deluxe (A)',
            'Hotelový dvoulůžák deluxe (A)',
        ]);
    }

    /**
     * Delší typ se rozpoznává dřív než jeho vlastní předpona, takže „deluxe (buňka)"
     * neskončí u „deluxe".
     */
    public function testALongerTypeIsRecognisedBeforeItsOwnPrefix(): void
    {
        $razeni = new RazeniTypuUbytovani();

        self::assertLessThan(
            $razeni->poradi('Hotelový jednolůžák deluxe'),
            $razeni->poradi('Hotelový jednolůžák deluxe (buňka)'),
        );
        self::assertLessThan(
            $razeni->poradi('Postel na 1L hotelu deluxe se snídaní'),
            $razeni->poradi('Postel na 1L hotelu deluxe se snídaní - dvojbuňka'),
        );
    }

    /**
     * Předpona musí končit celým slovem — „Dvojlůžákovna" není dvojlůžák.
     */
    public function testAPrefixOnlyMatchesAtAWordBoundary(): void
    {
        $razeni = new RazeniTypuUbytovani();

        self::assertSame(PHP_INT_MAX, $razeni->poradi('Dvojlůžákovna'));
        self::assertNotSame(PHP_INT_MAX, $razeni->poradi('Dvojlůžák (A)'));
        // Závorka hranici uzavírá i bez mezery před ní; admin takové názvy běžně zadává.
        self::assertNotSame(PHP_INT_MAX, $razeni->poradi('Spacák(A)'));
    }

    public function testAnUnknownTypeGoesLast(): void
    {
        $this->overPoradi([
            'Spacák',
            'Hotelový dvojlůžák deluxe',
            'Stan na parkovišti',
        ]);
    }
}
