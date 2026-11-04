<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\ZapisSouboruVTextu;
use PHPUnit\Framework\TestCase;

class ZapisSouboruVTextuTest extends TestCase
{
    /**
     * @test
     */
    public function markdownJeOdkazSNazvemSouboruBezPripony(): void
    {
        self::assertSame(
            '[dopis](soubory/obsah/download/dopis.pdf)',
            ZapisSouboruVTextu::markdown('soubory/obsah/download/dopis.pdf'),
        );
    }

    /**
     * @test
     */
    public function htmlJeOdkazSNazvemSouboruBezPripony(): void
    {
        self::assertSame(
            '<a href="soubory/obsah/download/dopis.pdf">dopis</a>',
            ZapisSouboruVTextu::html('soubory/obsah/download/dopis.pdf'),
        );
    }

    /**
     * @test
     */
    public function cestaSeZakodujeVMarkdownuIVHtmlStejne(): void
    {
        $cesta = 'soubory/obsah/drd/Příloha č. 1.pdf';
        $zakodovana = 'soubory/obsah/drd/P%C5%99%C3%ADloha%20%C4%8D.%201.pdf';

        self::assertSame("[Příloha č. 1]({$zakodovana})", ZapisSouboruVTextu::markdown($cesta));
        self::assertSame("<a href=\"{$zakodovana}\">Příloha č. 1</a>", ZapisSouboruVTextu::html($cesta));
    }

    /**
     * Soubor z doby FTP může mít v názvu cokoli, HTML zápis z něj nesmí udělat značku ani utrhnout atribut.
     *
     * @test
     */
    public function htmlZnakyZNazvuZabezpeci(): void
    {
        self::assertSame(
            '<a href="soubory/obsah/drd/a%3Cb%3E%26%22c.pdf">a&lt;b&gt;&amp;&quot;c</a>',
            ZapisSouboruVTextu::html('soubory/obsah/drd/a<b>&"c.pdf'),
        );
    }
}
