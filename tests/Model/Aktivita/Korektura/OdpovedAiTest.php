<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\OdpovedAi;
use PHPUnit\Framework\TestCase;

class OdpovedAiTest extends TestCase
{
    /**
     * @test
     */
    public function nacteOpravenaPoleAVynechanaNecha(): void
    {
        $odpoved = OdpovedAi::rozparsuj(<<<'TEXT'
<gc-aktivita id="12">
<gc-popis>
Opravený **popis**, který má čárku.
</gc-popis>
<gc-zmeny>čárka před „který“</gc-zmeny>
</gc-aktivita>
TEXT,
        );

        self::assertSame([], $odpoved->varovani);
        $navrh = $odpoved->navrhy[12];
        self::assertNull($navrh->nazev);
        self::assertNull($navrh->popisKratky);
        self::assertSame("\nOpravený **popis**, který má čárku.\n", $navrh->popis);
        self::assertSame('čárka před „který“', $navrh->zmeny);
    }

    /**
     * @test
     */
    public function kratkyPopisSeNespleteSPopisem(): void
    {
        $odpoved = OdpovedAi::rozparsuj(
            '<gc-aktivita id="1"><gc-popis-kratky>Krátký</gc-popis-kratky></gc-aktivita>',
        );

        self::assertSame('Krátký', $odpoved->navrhy[1]->popisKratky);
        self::assertNull($odpoved->navrhy[1]->popis);
        self::assertSame([], $odpoved->varovani);
    }

    /**
     * @test
     */
    public function prazdnyBlokZnamenaBezZmen(): void
    {
        $odpoved = OdpovedAi::rozparsuj(<<<'TEXT'
Tady jsou opravy:
```
<gc-aktivita id="1"></gc-aktivita>
<gc-aktivita id='2'/>
```
TEXT,
        );

        self::assertSame([1, 2], array_keys($odpoved->navrhy));
        foreach ($odpoved->navrhy as $navrh) {
            self::assertNull($navrh->nazev);
            self::assertNull($navrh->popisKratky);
            self::assertNull($navrh->popis);
        }
        self::assertSame([], $odpoved->varovani);
    }

    /**
     * @test
     */
    public function useknutyBlokPreskociAOstatniNacte(): void
    {
        $odpoved = OdpovedAi::rozparsuj(<<<'TEXT'
<gc-aktivita id="1">
<gc-nazev>První</gc-nazev>
</gc-aktivita>
<gc-aktivita id="2">
<gc-popis>Useknu
TEXT,
        );

        self::assertSame([1], array_keys($odpoved->navrhy));
        self::assertSame('První', $odpoved->navrhy[1]->nazev);
        self::assertCount(1, $odpoved->varovani);
        self::assertStringContainsString('Aktivita 2', $odpoved->varovani[0]);
    }

    /**
     * @test
     */
    public function blokBezKonceNespolkneNasledujiciBlok(): void
    {
        $odpoved = OdpovedAi::rozparsuj(<<<'TEXT'
<gc-aktivita id="1">
<gc-nazev>Bez konce</gc-nazev>
<gc-aktivita id="2">
<gc-nazev>Druhá</gc-nazev>
</gc-aktivita>
TEXT,
        );

        self::assertSame([2], array_keys($odpoved->navrhy));
        self::assertSame('Druhá', $odpoved->navrhy[2]->nazev);
        self::assertCount(1, $odpoved->varovani);
    }

    /**
     * @test
     */
    public function poleBezKonceZustanePuvodni(): void
    {
        $odpoved = OdpovedAi::rozparsuj('<gc-aktivita id="1"><gc-popis>Useknuté</gc-aktivita>');

        self::assertNull($odpoved->navrhy[1]->popis);
        self::assertCount(1, $odpoved->varovani);
        self::assertStringContainsString('<gc-popis>', $odpoved->varovani[0]);
    }

    /**
     * @test
     */
    public function vracenaDrivejsiVerzeNeniNavrh(): void
    {
        $odpoved = OdpovedAi::rozparsuj(<<<'TEXT'
<gc-aktivita id="1">
<gc-drivejsi rok="2025">
<gc-popis>Loňský popis</gc-popis>
</gc-drivejsi>
</gc-aktivita>
TEXT,
        );

        self::assertNull($odpoved->navrhy[1]->popis);
        self::assertSame([], $odpoved->varovani);
    }

    /**
     * @test
     */
    public function opakovanaAktivitaVezmePosledniVyskyt(): void
    {
        $odpoved = OdpovedAi::rozparsuj(
            '<gc-aktivita id="1"><gc-nazev>Poprvé</gc-nazev></gc-aktivita>'
            . '<gc-aktivita id="1"><gc-nazev>Podruhé</gc-nazev></gc-aktivita>',
        );

        self::assertSame('Podruhé', $odpoved->navrhy[1]->nazev);
        self::assertCount(1, $odpoved->varovani);
    }

    /**
     * @test
     */
    public function odpovedBezBlokuVaruje(): void
    {
        $odpoved = OdpovedAi::rozparsuj('Omlouvám se, nemohu pomoci.');

        self::assertSame([], $odpoved->navrhy);
        self::assertCount(1, $odpoved->varovani);
    }
}
