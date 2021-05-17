<?php declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;

class FunkceTest extends TestCase
{
    /**
     * @test
     */
    public function Muzu_nahradit_placeholder_za_konstantu() {
        $bezPlaceholderu = 'Jsem bez placeholderu';
        self::assertSame($bezPlaceholderu, nahradPlaceholderZaKonstantu($bezPlaceholderu));

        $sNeznamouKnstantou = 'Jsem s neznámou %konstantou z jiného světa%';
        self::assertSame($sNeznamouKnstantou, nahradPlaceholderZaKonstantu($sNeznamouKnstantou));

        $nahodnaKonstanta = uniqid(__FUNCTION__, true);
        $sKonstantou = "Jsem s konstantou %$nahodnaKonstanta%";

        self::assertFalse(defined($nahodnaKonstanta));
        self::assertSame($sKonstantou, nahradPlaceholderZaKonstantu($sKonstantou));

        define($nahodnaKonstanta, 'To je ale náhodička!');
        self::assertSame('Jsem s konstantou To je ale náhodička!', nahradPlaceholderZaKonstantu($sKonstantou));
    }
}
