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
            'Excel s makry'                       => [self::xlsmBajty()],
            'sešit se jménem XLSX, ale s makry'   => [self::xlsxSMakryBajty()],
            'makrolist s typem v obsahu balíku'   => [self::xlsxSMakrolistemJinde()],
            'makra ve velkých písmenech'          => [self::xlsxSMakryVeVelkychPismenech()],
            'prázdný seznam typů'                 => [self::zipSPrazdnymContentTypes()],
            'Word s makry'                        => [self::docxSMakryBajty()],
            'Word s vloženým objektem'            => [self::docxSVlozenymObjektemBajty()],
            'PowerPoint'                          => [self::pptxBajty()],
            'LibreOffice s makrem v Basicu'       => [self::odtSMakryBajty()],
            'LibreOffice s odkazem na makro'      => [self::odtSOdkazemNaMakroBajty()],
            'LibreOffice s událostí'              => [self::odtSUdalostiBajty()],
            'LibreOffice s objektem'              => [self::odtSVlozenymObjektemBajty()],
            'odkaz na makro v entitách'           => [self::odtSOdkazemNaMakroVEntitachBajty()],
            'událost s jiným prefixem'            => [self::odtSUdalostiSJinymPrefixemBajty()],
            'rozbité XML dokumentu'               => [self::odtSRozbitymXmlBajty()],
            'Word s objektem jinde než ve složce' => [self::docxSObjektemJindeBajty()],
            'Word se vzdálenou šablonou'          => [self::docxSeVzdalenouSablonouBajty()],
            'typ vazby v entitách'                => [self::docxSVazbouVEntitachBajty()],
            'nečitelné vazby'                     => [self::docxSNeplatnymiVazbamiBajty()],
            'typ obsahu objektu'                  => [self::docxSTypemObsahuObjektuBajty()],
            'zpětná lomítka a velká písmena'      => [self::docxSObjektemSeZpetnymiLomitkyBajty()],
            'typ objektu před jinou příponou'     => [self::docxSTypemObsahuObjektuPredJinouPriponouBajty()],
            'pole DDE'                            => [self::docxSPolemDdeBajty()],
            'pole DDE rozdělené do prvků'         => [self::docxSPolemDdeRozdelenymBajty()],
            'jednoduché pole DDE'                 => [self::docxSJednoduchymPolemDdeBajty()],
            'vzdálený obrázek'                    => [self::docxSVzdalenymObrazkemBajty()],
            'zkratka na makro v nastavení'        => [self::odtSZkratkouNaMakroBajty()],
            'prázdná XML část'                    => [self::odtSPrazdnouCastiBajty()],
            'dialog'                              => [self::odtSDialogemBajty()],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideBalikyOffice
     */
    public function balikOfficeKteryNeniPovolenyNeniZipAniDokument(string $obsah): void
    {
        $soubor = $this->soubor('dokument.zip', $obsah);

        self::assertNull(RozpoznaniDokumentu::mime($soubor));
        self::assertTrue(RozpoznaniDokumentu::jeBalikOffice($soubor));
    }

    /**
     * Dvě položky stejného názvu: kontrola čte jednu, program, který dokument otevře, může druhou. ZipArchive
     * s kontrolou souladu takový archiv odmítne, takže se nepozná ani jako obyčejný ZIP.
     *
     * @test
     */
    public function archivSOpakovanymiNazvyPolozekSeNepozna(): void
    {
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('a.odt', self::odtSDuplicitnimObsahemBajty())));
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('b.docx', self::docxSDuplicitnimiTypyObsahuBajty())));
    }

    public static function provideDokumentyKtereSePrijmou(): array
    {
        return [
            'Word'                           => [self::docxBajty(), NahravaniObrazku::DOCX],
            'Word s obrázky a odkazy'        => [self::docxSObrazkyAOdkazyBajty(), NahravaniObrazku::DOCX],
            'Word s běžnými poli'            => [self::docxSBeznymiPolemiBajty(), NahravaniObrazku::DOCX],
            'Word, který zmiňuje typ sešitu' => [self::docxSeZminkouOXlsxBajty(), NahravaniObrazku::DOCX],
            'sešit Excelu'                   => [self::xlsxBajty(), NahravaniObrazku::XLSX],
            'LibreOffice Writer'             => [self::odtBajty(), NahravaniObrazku::ODT],
            'LibreOffice Calc'               => [self::odsBajty(), NahravaniObrazku::ODS],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideDokumentyKtereSePrijmou
     */
    public function poznaDokumentyBezMakerPodleHlavniCasti(string $obsah, string $ocekavanyTyp): void
    {
        $soubor = $this->soubor('dokument.bin', $obsah);

        self::assertSame($ocekavanyTyp, RozpoznaniDokumentu::mime($soubor));
        self::assertFalse(RozpoznaniDokumentu::jeBalikOffice($soubor));
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

    public static function provideZvuk(): array
    {
        return [
            'Ogg Vorbis'             => [self::oggVorbisBajty(), NahravaniObrazku::OGG],
            'Ogg Opus'               => [self::oggOpusBajty(), NahravaniObrazku::OGG],
            'Ogg s useknutým koncem' => [self::oggSUseknutymKoncemBajty(), NahravaniObrazku::OGG],
            'WAV'                    => [self::wavBajty(), NahravaniObrazku::WAV],
            'M4A'                    => [self::m4aBajty(), NahravaniObrazku::M4A],
            'audio v mp42'           => [self::mp4Bajty('mp42', ['soun']), NahravaniObrazku::M4A],
            'audio a titulky'        => [self::mp4Bajty('isom', ['soun', 'text']), NahravaniObrazku::M4A],
            'zvuk s popisem vzorků'  => [self::mp4ZvukSPopisemVzorkuBajty(), NahravaniObrazku::M4A],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideZvuk
     */
    public function poznaZvukPodleKontejneru(string $obsah, string $ocekavanyTyp): void
    {
        self::assertSame($ocekavanyTyp, RozpoznaniDokumentu::mime($this->soubor('zvuk.bin', $obsah)));
    }

    public static function provideZvukKterySePoznaneNeniAniVideo(): array
    {
        return [
            'Ogg s obrazem'                  => [self::oggSVideemBajty()],
            'Ogg s neznámým kodekem'         => [self::oggStranka('NeznamyKodek1234')],
            'osm zvuků a devátý proud video' => [self::oggSDevatymProudemVideoBajty()],
            'zřetězený Ogg s videem'         => [self::oggZretezenySVideemBajty()],
            'MP4 se stopou o dvou popisech'  => [self::mp4StopaSDvemaPopisyBajty()],
            'MP4 se stopou bez popisu'       => [self::mp4StopaBezPopisuBajty()],
            'MP4 se stopou neznámého druhu'  => [self::mp4StopaNeznamehoDruhuBajty()],
            'zvuk s hlavičkou obrazu'        => [self::mp4ZvukSHlavickouObrazuBajty()],
            'zvuk s videokodekem'            => [self::mp4ZvukSVideokodekemBajty()],
            'Ogg, který nezačíná proudem'    => [self::oggStranka("\x01vorbis" . str_repeat("\0", 23), false)],
            'useknutá hlavička Ogg'          => ['OggS' . "\0\x02" . str_repeat("\0", 10)],
            'WAV bez popisu formátu'         => [self::wavBezFormatuBajty()],
            'WAV useknutý před daty'         => [substr(self::wavBajty(), 0, 30)],
            'RIFF, který není WAV'           => ['RIFF' . pack('V', 100) . 'AVI ' . str_repeat("\0", 100)],
            'MP4 s obrazem'                  => [self::mp4Bajty('isom', ['vide'])],
            'MP4 se zvukem i obrazem'        => [self::mp4Bajty('mp42', ['soun', 'vide'])],
            'MP4 bez zvukové stopy'          => [self::mp4Bajty('M4A ', ['text'])],
            'QuickTime'                      => [self::mp4Bajty('qt  ', ['soun'])],
            'MP4 useknuté před popisem stop' => [substr(self::m4aBajty(), 0, 60)],
            'AVIF'                           => [self::avifBajty()],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideZvukKterySePoznaneNeniAniVideo
     */
    public function videoAPoskozenyZvukNepozna(string $obsah): void
    {
        self::assertNull(RozpoznaniDokumentu::mime($this->soubor('soubor.bin', $obsah)));
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
