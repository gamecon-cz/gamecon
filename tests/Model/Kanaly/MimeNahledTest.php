<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly;

use Gamecon\Kanaly\MimeNahled;
use PHPUnit\Framework\TestCase;

class MimeNahledTest extends TestCase
{
    /**
     * @test
     */
    public function vytahneHtmlITextSDekodovanymiDiakritikamiZMultipartZpravy(): void
    {
        $mime = "From: GameCon <gamecon.fallback@seznam.cz>\r\n"
            . "Subject: =?utf-8?Q?Voln=C3=A9_m=C3=ADsto?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/alternative; boundary=IzEog0sX\r\n"
            . "\r\n"
            . "--IzEog0sX\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . "Na aktivit=C4=9B se uvolnilo m=C3=ADsto.\r\n"
            . "--IzEog0sX\r\n"
            . "Content-Type: text/html; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . "<html><body>Na aktivit=C4=9B se uvolnilo m=C3=ADsto. P=C5=99ihla=C5=A1 se p=C5=99es <a=\r\n"
            . " href=3D\"https://gamecon.cz/program\">program</a>.</body></html>\r\n"
            . "--IzEog0sX--\r\n";

        $tela = MimeNahled::vytahniTela($mime);

        self::assertSame('Na aktivitě se uvolnilo místo.', $tela['text']);
        self::assertNotNull($tela['html']);
        self::assertStringContainsString('Na aktivitě se uvolnilo místo.', $tela['html']);
        self::assertStringContainsString('Přihlaš se přes', $tela['html']);
        self::assertStringContainsString('href="https://gamecon.cz/program"', $tela['html']);
        // quoted-printable soft line break (=\n) i =3D musí zmizet
        self::assertStringNotContainsString('=3D', $tela['html']);
        self::assertStringNotContainsString('=C5', $tela['html']);
    }

    /**
     * @test
     */
    public function zvladneJednoduchouNeMultipartHtmlZpravu(): void
    {
        $mime = "Content-Type: text/html; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . '<p>M=C3=ADsto</p>';

        $tela = MimeNahled::vytahniTela($mime);

        self::assertSame('<p>Místo</p>', $tela['html']);
        self::assertNull($tela['text']);
    }

    /**
     * @test
     */
    public function dekodujeBase64Telo(): void
    {
        $obsah = '<p>Příliš žluťoučký kůň</p>';
        $mime = "Content-Type: text/html; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "\r\n"
            . chunk_split(base64_encode($obsah));

        $tela = MimeNahled::vytahniTela($mime);

        self::assertSame($obsah, $tela['html']);
    }
}
