<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\RozpoznaniDokumentu;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class RozpoznaniDokumentuTest extends TestCase
{
    use TestovaciDokumenty;

    private string $zdroje;

    protected function setUp(): void
    {
        $this->zdroje = sys_get_temp_dir() . '/gamecon-rozpoznani-dokumentu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->zdroje);
    }

    /**
     * @test
     */
    public function poznaPdfPodleHlavicky(): void
    {
        self::assertSame(NahravaniObrazku::PDF, RozpoznaniDokumentu::mime($this->soubor('a.bin', self::pdfBajty())));
    }

    /**
     * @test
     */
    public function poznaZipXlsxAMp3(): void
    {
        self::assertSame(NahravaniObrazku::ZIP, RozpoznaniDokumentu::mime($this->soubor('a.bin', self::zipBajty())));
        self::assertSame(NahravaniObrazku::XLSX, RozpoznaniDokumentu::mime($this->soubor('b.bin', self::xlsxBajty())));
        self::assertSame(NahravaniObrazku::MP3, RozpoznaniDokumentu::mime($this->soubor('c.bin', self::mp3Bajty())));
    }

    /**
     * @test
     */
    public function typUrciObsahNePripona(): void
    {
        // přípona od prohlížeče nic neříká, rozhoduje obsah
        self::assertSame(NahravaniObrazku::PDF, RozpoznaniDokumentu::mime($this->soubor('smlouva.mp3', self::pdfBajty())));
        self::assertSame(NahravaniObrazku::ZIP, RozpoznaniDokumentu::mime($this->soubor('tabulka.xlsx', self::zipBajty())));
        self::assertSame(NahravaniObrazku::XLSX, RozpoznaniDokumentu::mime($this->soubor('archiv.zip', self::xlsxBajty())));
    }

    /**
     * @test
     */
    public function poznaMp3SId3ZnackouIBezNi(): void
    {
        self::assertSame(
            NahravaniObrazku::MP3,
            RozpoznaniDokumentu::mime($this->soubor('s-id3.mp3', self::mp3Bajty(snimku: 3, id3Bajtu: 300))),
        );
        self::assertSame(
            NahravaniObrazku::MP3,
            RozpoznaniDokumentu::mime($this->soubor('mpeg2.mp3', self::mp3Bajty(hlavicka: "\xFF\xF3\x48\x00"))),
            'MPEG 2 vrstva III, třeba záznam z diktafonu',
        );
        self::assertSame(
            NahravaniObrazku::MP3,
            RozpoznaniDokumentu::mime($this->soubor('s-vyplni.mp3', self::mp3Bajty(hlavicka: "\xFF\xFB\x92\x00"))),
            'Snímek s výplní je o bajt delší',
        );
    }

    public static function provideSouboryKterePoznaneNejsou(): array
    {
        return [
            'prázdný soubor'            => [''],
            'PHP skript'                => ['<?php echo "ahoj";'],
            'HTML'                      => ['<html><body>Ahoj</body></html>'],
            'jen text ID3'              => ['ID3'],
            'ID3 značka a nic za ní'    => ["ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\0", 100)],
            'ID3 s neplatnou velikostí' => ["ID3\x03\x00\x00\xFF\xFF\xFF\xFF" . self::mp3Bajty()],
            'jediný snímek'             => [self::mp3Bajty(snimku: 1)],
            'náhodné bajty'             => [str_repeat("\xFF\x13\x00\xA7", 500)],
            'PDF s mezerou na začátku'  => [' ' . "%PDF-1.7\n"],
            'useknutý ZIP'              => [substr(self::zipBajty(), 0, 30)],
            'GIF'                       => ["GIF89a\x01\x00\x01\x00"],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSouboryKterePoznaneNejsou
     */
    public function cokoliJineNezPodporovanyDokumentNepozna(string $obsah): void
    {
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('a.pdf', $obsah)));
    }

    /**
     * Bez ID3 značky musí MP3 začínat snímkem. Soubor, který začíná jinak a snímky má až dál, je třeba obrázek,
     * jehož metadata se na dvě hlavičky snímků shodou okolností podobají.
     *
     * @test
     */
    public function mp3BezId3MusiZacinatSnimkem(): void
    {
        $snimky = self::mp3Bajty();

        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('s-odsazenim.mp3', str_repeat("\0", 100) . $snimky)));
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('jpeg.jpg', "\xFF\xD8\xFF\xE0" . str_repeat("\0", 200) . $snimky)));
        self::assertSame(NahravaniObrazku::MP3, RozpoznaniDokumentu::mime($this->soubor('od-zacatku.mp3', $snimky)));
    }

    /**
     * Za ID3 značkou některé programy nechávají výplň, tam se snímek hledá v okně.
     *
     * @test
     */
    public function mp3ZaId3ZnackouMuzeMitVyplnPredPrvnimSnimkem(): void
    {
        $znackaSVyplni = self::mp3Bajty(snimku: 0, id3Bajtu: 40) . str_repeat("\0", 30);

        self::assertSame(
            NahravaniObrazku::MP3,
            RozpoznaniDokumentu::mime($this->soubor('s-vyplni.mp3', $znackaSVyplni . self::mp3Bajty())),
        );
    }

    /**
     * @test
     */
    public function mp3SeSnimkyVrstvyIIJeJinyFormat(): void
    {
        // vrstva II (bity 10) má jinou délku snímku, čtení by se rozešlo; pro web chceme jen MP3
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('a.mp2', self::mp3Bajty(hlavicka: "\xFF\xFD\x90\x00"))));
    }

    public static function provideBalikyOffice(): array
    {
        return [
            'Word'                              => [self::docxBajty()],
            'Excel s makry'                     => [self::xlsmBajty()],
            'sešit se jménem XLSX, ale s makry' => [self::xlsxSMakryBajty()],
            'makrolist s typem v obsahu balíku' => [self::xlsxSMakrolistemJinde()],
            'makra ve velkých písmenech'        => [self::xlsxSMakryVeVelkychPismenech()],
            'prázdný seznam typů'               => [self::zipSPrazdnymContentTypes()],
            'Word, který zmiňuje typ sešitu'    => [self::docxSeZminkouOXlsxBajty()],
            'LibreOffice Writer'                => [self::odtBajty()],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideBalikyOffice
     */
    public function dokumentOfficeNeniZipAleTakAniXlsx(string $obsah): void
    {
        $soubor = $this->soubor('dokument.zip', $obsah);

        self::assertNull(RozpoznaniDokumentu::mime($soubor));
        self::assertTrue(RozpoznaniDokumentu::jeBalikOffice($soubor));
    }

    /**
     * @test
     */
    public function jeBalikOfficeJenKdyzJdeOBalikOffice(): void
    {
        self::assertFalse(RozpoznaniDokumentu::jeBalikOffice($this->soubor('a.zip', self::zipBajty())));
        self::assertFalse(RozpoznaniDokumentu::jeBalikOffice($this->soubor('b.xlsx', self::xlsxBajty())));
        self::assertFalse(RozpoznaniDokumentu::jeBalikOffice($this->soubor('c.pdf', self::pdfBajty())));
        self::assertFalse(RozpoznaniDokumentu::jeBalikOffice($this->soubor('d.zip', substr(self::docxBajty(), 0, 40))));
    }

    /**
     * @test
     */
    public function neexistujiciSouborNeniDokument(): void
    {
        self::assertNull(RozpoznaniDokumentu::mime($this->zdroje . '/neexistuje.pdf'));
    }

    private function soubor(string $nazev, string $obsah): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '-' . $nazev;
        file_put_contents($cesta, $obsah);

        return $cesta;
    }
}
