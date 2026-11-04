<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

/**
 * Bajty souborů, které nahrávání rozpozná: minimální PDF, ZIP, XLSX, MP3 a balíky Office, které rozpoznané nejsou.
 */
trait TestovaciDokumenty
{
    private const string HLAVICKA_MP3 = "\xFF\xFB\x90\x00";

    private static function pdfBajty(): string
    {
        return "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    private static function zipBajty(): string
    {
        return self::zipSObsahem([
            'poznamky.txt' => 'Obyčejný archiv',
        ]);
    }

    private static function xlsxBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'),
            'xl/workbook.xml'     => '<workbook/>',
        ]);
    }

    private static function xlsmBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/xl/workbook.xml', 'application/vnd.ms-excel.sheet.macroEnabled.main+xml'),
            'xl/workbook.xml'     => '<workbook/>',
            'xl/vbaProject.bin'   => 'makra',
        ]);
    }

    /**
     * Sešit, který se hlásí jako XLSX, ale nese makra.
     */
    private static function xlsxSMakryBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'),
            'xl/workbook.xml'     => '<workbook/>',
            'xl/vbaProject.bin'   => 'makra',
        ]);
    }

    /**
     * Sešit s makrolistem, který není pod `xl/macrosheets/`: o jeho typu rozhoduje `[Content_Types].xml`.
     */
    private static function xlsxSMakrolistemJinde(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/jinde/list.xml" ContentType="application/vnd.ms-excel.macrosheet+xml"/>'
                . '</Types>',
            'xl/workbook.xml' => '<workbook/>',
            'jinde/list.xml'  => '<macrosheet/>',
        ]);
    }

    /**
     * Názvy částí balíku OPC se nerozlišují podle velikosti písmen.
     */
    private static function xlsxSMakryVeVelkychPismenech(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'),
            'xl/workbook.xml'     => '<workbook/>',
            'XL/VBAProject.bin'   => 'makra',
        ]);
    }

    /**
     * ZIP s prázdným `[Content_Types].xml`.
     */
    private static function zipSPrazdnymContentTypes(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => '',
            'xl/workbook.xml'     => '<workbook/>',
        ]);
    }

    /**
     * Dokument z Wordu, který text o sešitu jen zmiňuje, třeba v komentáři.
     */
    private static function docxSeZminkouOXlsxBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<!-- application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml -->'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '</Types>',
            'word/document.xml' => '<document/>',
        ]);
    }

    private static function docxBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'),
            'word/document.xml'   => '<document/>',
        ]);
    }

    /**
     * Dokument z Wordu, který nese makra.
     */
    private static function docxSMakryBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => self::contentTypes('/word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'),
            'word/document.xml'   => '<document/>',
            'word/vbaProject.bin' => 'makra',
        ]);
    }

    /**
     * Dokument z Wordu bez maker, ale s vloženým objektem, který se po otevření může spustit.
     */
    private static function docxSVlozenymObjektemBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml'            => self::contentTypes('/word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'),
            'word/document.xml'              => '<document/>',
            'word/embeddings/oleObject1.bin' => 'objekt',
        ]);
    }

    private const string TYP_HLAVNI_CASTI_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
    private const string VAZBY_ODKAZ = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';

    /**
     * Dokument z Wordu se soubory vazeb (`.rels`) s daným obsahem; `$dalsiPolozky` jsou části, na které vazby míří.
     *
     * @param array<string, string> $dalsiPolozky
     */
    private static function docxSVazbami(string $vazby, array $dalsiPolozky = []): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml'          => self::contentTypes('/word/document.xml', self::TYP_HLAVNI_CASTI_DOCX),
            'word/document.xml'            => '<document/>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $vazby . '</Relationships>',
            ...$dalsiPolozky,
        ]);
    }

    /**
     * Skutečný dokument: obrázek, styly, motiv a odkaz na web. Nic z toho nic nespouští.
     */
    private static function docxSObrazkyAOdkazyBajty(): string
    {
        return self::docxSVazbami(
            '<Relationship Id="rId1" Type="' . self::VAZBY_ODKAZ . 'styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="' . self::VAZBY_ODKAZ . 'image" Target="media/mapa.png"/>'
            . '<Relationship Id="rId3" Type="' . self::VAZBY_ODKAZ . 'hyperlink" Target="https://gamecon.cz" TargetMode="External"/>'
            . '<Relationship Id="rId4" Type="' . self::VAZBY_ODKAZ . 'theme" Target="theme/theme1.xml"/>',
            [
                'word/media/mapa.png' => 'obrazek',
                'word/styles.xml'     => '<styles/>',
            ],
        );
    }

    /**
     * Vložený objekt mimo složku `word/embeddings`, na který míří jen vazba.
     */
    private static function docxSObjektemJindeBajty(): string
    {
        return self::docxSVazbami(
            '<Relationship Id="rId1" Type="' . self::VAZBY_ODKAZ . 'oleObject" Target="blob/objekt1.bin"/>',
            [
                'word/blob/objekt1.bin' => 'objekt',
            ],
        );
    }

    /**
     * Vzdálená šablona: Word ji při otevření stáhne a může s ní načíst makra.
     */
    private static function docxSeVzdalenouSablonouBajty(): string
    {
        return self::docxSVazbami(
            '<Relationship Id="rId1" Type="' . self::VAZBY_ODKAZ . 'attachedTemplate" Target="https://example.invalid/sablona.dotm" TargetMode="External"/>',
        );
    }

    /**
     * Typ vazby s lomítkem zapsaným znakovou entitou, který hledání řetězce `/oleObject` mine.
     */
    private static function docxSVazbouVEntitachBajty(): string
    {
        return self::docxSVazbami('<Relationship Id="rId1" Type="' . self::VAZBY_ODKAZ . '&#111;leObject" Target="blob/objekt1.bin"/>', [
            'word/blob/objekt1.bin' => 'objekt',
        ]);
    }

    private static function docxSNeplatnymiVazbamiBajty(): string
    {
        return self::docxSVazbami('<Relationship Id="rId1" Type=');
    }

    /**
     * Typ obsahu vloženého objektu u části mimo `word/embeddings`.
     */
    private static function docxSTypemObsahuObjektuBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Override PartName="/word/document.xml" ContentType="' . self::TYP_HLAVNI_CASTI_DOCX . '"/>'
                . '<Default Extension="bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/></Types>',
            'word/document.xml'     => '<document/>',
            'word/blob/objekt1.bin' => 'objekt',
        ]);
    }

    /**
     * Vložený objekt v adresáři zapsaném se zpětnými lomítky a velkými písmeny, jak to dělají některé programy.
     */
    private static function docxSObjektemSeZpetnymiLomitkyBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml'           => self::contentTypes('/word/document.xml', self::TYP_HLAVNI_CASTI_DOCX),
            'word/document.xml'             => '<document/>',
            'Word\\Embeddings\\objekt1.bin' => 'objekt',
        ]);
    }

    private const string NAMESPACE_WORDU = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Dokument z Wordu s `word/document.xml` daného obsahu.
     */
    private static function docxSTextem(string $telo): string
    {
        return self::docxSVazbami('', [
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . self::NAMESPACE_WORDU . '"><w:body>' . $telo . '</w:body></w:document>',
        ]);
    }

    private static function poleWordu(string ...$kodyVPrvcich): string
    {
        return '<w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r>'
            . implode('', array_map(static fn (string $kod): string => '<w:r><w:instrText xml:space="preserve">' . $kod . '</w:instrText></w:r>', $kodyVPrvcich))
            . '<w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
    }

    /**
     * Skutečný dokument má pole: číslo strany, obsah, odkaz. Žádné nic nespouští.
     */
    private static function docxSBeznymiPolemiBajty(): string
    {
        return self::docxSTextem(
            self::poleWordu(' PAGE ') . self::poleWordu(' TOC \\o "1-3" \\h \\z \\u ') . self::poleWordu(' HYPERLINK "https://gamecon.cz" '),
        );
    }

    private static function docxSPolemDdeBajty(): string
    {
        return self::docxSTextem(self::poleWordu(' DDEAUTO c:\\windows\\system32\\cmd.exe "/k calc.exe" '));
    }

    /**
     * Kód pole rozdělený do víc prvků, aby se v textu nedal najít: Word je při čtení spojí.
     */
    private static function docxSPolemDdeRozdelenymBajty(): string
    {
        return self::docxSTextem(self::poleWordu('DD', 'EAUTO c:\\windows\\system32\\cmd.exe'));
    }

    private static function docxSJednoduchymPolemDdeBajty(): string
    {
        return self::docxSTextem('<w:fldSimple w:instr="DDE &quot;cmd&quot; &quot;/c calc&quot;"><w:r><w:t>x</w:t></w:r></w:fldSimple>');
    }

    /**
     * Obrázek, který si Word při otevření stáhne z cizí adresy (nebo ze sdílené složky, a prozradí tak přihlášení).
     */
    private static function docxSVzdalenymObrazkemBajty(): string
    {
        return self::docxSVazbami(
            '<Relationship Id="rId1" Type="' . self::VAZBY_ODKAZ . 'image" Target="https://example.invalid/logo.png" TargetMode="External"/>',
        );
    }

    /**
     * `[Content_Types].xml` se dvěma příponami: vložený objekt je první a poslední `Default` by ho v mapě podle `PartName` přepsal.
     */
    private static function docxSTypemObsahuObjektuPredJinouPriponouBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml' => '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Override PartName="/word/document.xml" ContentType="' . self::TYP_HLAVNI_CASTI_DOCX . '"/>'
                . '<Default Extension="bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>'
                . '<Default Extension="xml" ContentType="application/xml"/></Types>',
            'word/document.xml'     => '<document/>',
            'word/blob/objekt1.bin' => 'objekt',
        ]);
    }

    /**
     * ZIP, ve kterém se názvy opakují: čtecí program může vzít jinou položku než kontrola. Skládá se ručně,
     * `ZipArchive` druhou položku stejného názvu nepřidá.
     *
     * @param list<array{string, string}> $polozky dvojice název a obsah, bez komprese
     */
    private static function zipSDuplicitami(array $polozky): string
    {
        $mistni = '';
        $ustredni = '';
        foreach ($polozky as [$nazev, $obsah]) {
            $velikost = strlen($obsah);
            // verze, příznaky, metoda (bez komprese), čas, datum, CRC, velikosti a délka názvu a doplňku
            $zahlavi = pack('vvvvvVVV', 20, 0, 0, 0, 0x21, crc32($obsah), $velikost, $velikost) . pack('vv', strlen($nazev), 0);
            $ustredni .= "PK\x01\x02" . pack('v', 20) . $zahlavi . pack('vvvVV', 0, 0, 0, 0, strlen($mistni)) . $nazev;
            $mistni .= "PK\x03\x04" . $zahlavi . $nazev . $obsah;
        }

        return $mistni . $ustredni . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($polozky), count($polozky), strlen($ustredni), strlen($mistni), 0);
    }

    /**
     * Dokument LibreOffice s čistým obsahem, za kterým stejnojmenná položka nese skript.
     */
    private static function odtSDuplicitnimObsahemBajty(): string
    {
        return self::zipSDuplicitami([
            ['mimetype', 'application/vnd.oasis.opendocument.text'],
            ['content.xml', self::odfObsah()],
            ['content.xml', self::odfObsah('<text:a xlink:href="vnd.sun.star.script:Standard.Module1.Spust">text</text:a>')],
        ]);
    }

    private static function docxSDuplicitnimiTypyObsahuBajty(): string
    {
        return self::zipSDuplicitami([
            ['[Content_Types].xml', self::contentTypes('/word/document.xml', self::TYP_HLAVNI_CASTI_DOCX)],
            ['word/document.xml', '<document/>'],
            ['[Content_Types].xml', self::contentTypes('/word/document.xml', 'application/vnd.ms-word.document.macroEnabled.main+xml')],
        ]);
    }

    /**
     * Dokument LibreOffice, jehož makro je navázané na klávesovou zkratku ve zvláštní části.
     */
    private static function odtSZkratkouNaMakroBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'                                => 'application/vnd.oasis.opendocument.text',
            'content.xml'                             => self::odfObsah(),
            'Configurations2/accelerator/current.xml' => '<accel:accelerator xmlns:accel="http://openoffice.org/2001/accel" xmlns:xlink="http://www.w3.org/1999/xlink">'
                . '<accel:item accel:code="F5" xlink:href="vnd.sun.star.script:Standard.Module1.Spust?language=Basic"/></accel:accelerator>',
        ]);
    }

    private static function odtSPrazdnouCastiBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'    => 'application/vnd.oasis.opendocument.text',
            'content.xml' => self::odfObsah(),
            'styles.xml'  => '',
        ]);
    }

    private static function odtSDialogemBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'               => 'application/vnd.oasis.opendocument.text',
            'content.xml'            => self::odfObsah(),
            'Dialogs/Standard/d.xml' => '<dlg:window/>',
        ]);
    }

    /**
     * Stopa, která se hlásí jako zvuk (`soun`), ale má v popisu médií hlavičku obrazu.
     */
    private static function mp4ZvukSHlavickouObrazuBajty(): string
    {
        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun') . self::mp4Krabice('minf', self::mp4Krabice('vmhd', str_repeat("\0", 12))))));
    }

    /**
     * Stopa `soun`, jejíž popis vzorků je videokodek.
     */
    private static function mp4ZvukSVideokodekemBajty(): string
    {
        $stsd = self::mp4Krabice('stsd', "\0\0\0\0" . pack('N', 1) . self::mp4Krabice('avc1', str_repeat("\0", 20)));

        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun') . self::mp4Krabice('minf', self::mp4Krabice('stbl', $stsd)))));
    }

    /**
     * Skutečná zvuková stopa: hlavička zvuku a popis vzorků `mp4a`.
     */
    private static function mp4ZvukSPopisemVzorkuBajty(): string
    {
        $stsd = self::mp4Krabice('stsd', "\0\0\0\0" . pack('N', 1) . self::mp4Krabice('mp4a', str_repeat("\0", 20)));

        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun') . self::mp4Krabice('minf', self::mp4Krabice('smhd', str_repeat("\0", 8)) . self::mp4Krabice('stbl', $stsd)))));
    }

    private static function pptxBajty(): string
    {
        return self::zipSObsahem([
            '[Content_Types].xml'  => self::contentTypes('/ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'),
            'ppt/presentation.xml' => '<presentation/>',
        ]);
    }

    private static function odtBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'    => 'application/vnd.oasis.opendocument.text',
            'content.xml' => self::odfObsah(),
        ]);
    }

    private static function odsBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'    => 'application/vnd.oasis.opendocument.spreadsheet',
            'content.xml' => self::odfObsah(),
        ]);
    }

    /**
     * Dokument LibreOffice s makrem v knihovně Basic.
     */
    private static function odtSMakryBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'                   => 'application/vnd.oasis.opendocument.text',
            'content.xml'                => self::odfObsah(),
            'Basic/Standard/Module1.xml' => '<script:module/>',
        ]);
    }

    /**
     * Dokument LibreOffice s vloženým objektem (třeba grafem).
     */
    private static function odtSVlozenymObjektemBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'             => 'application/vnd.oasis.opendocument.text',
            'content.xml'          => self::odfObsah(),
            'Object 1/content.xml' => self::odfObsah(),
        ]);
    }

    /**
     * Dokument LibreOffice bez maker, jehož odkaz spouští makro z knihovny na počítači čtenáře.
     */
    private static function odtSOdkazemNaMakroBajty(): string
    {
        return self::odtSObsahem(self::odfObsah('<text:a xlink:href="vnd.sun.star.script:Standard.Module1.Spust?language=Basic&amp;location=application">text</text:a>'));
    }

    /**
     * Totéž s odkazem zapsaným znakovými entitami, který hledání řetězce `vnd.sun.star.script` mine.
     */
    private static function odtSOdkazemNaMakroVEntitachBajty(): string
    {
        return self::odtSObsahem(self::odfObsah('<text:a xlink:href="&#118;nd.sun.star.script:Standard.Module1.Spust">text</text:a>'));
    }

    private static function odtSUdalostiBajty(): string
    {
        return self::odtSObsahem(self::odfObsah(
            '<office:event-listeners><script:event-listener script:event-name="dom:click" xlink:href="macro:///Standard.Module1.Spust"/></office:event-listeners>',
        ));
    }

    /**
     * Událost se skriptem ve jmenném prostoru skriptů pod jiným prefixem, než je obvyklé `script`.
     */
    private static function odtSUdalostiSJinymPrefixemBajty(): string
    {
        return self::odtSObsahem(
            '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:s="urn:oasis:names:tc:opendocument:xmlns:script:1.0">'
            . '<office:event-listeners><s:event-listener/></office:event-listeners></office:document-content>',
        );
    }

    /**
     * Obsah, který není XML, tedy takový, jehož kontrola na makra by nic neřekla.
     */
    private static function odtSRozbitymXmlBajty(): string
    {
        return self::odtSObsahem('<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"><office:body>');
    }

    private static function odtSObsahem(string $obsah): string
    {
        return self::zipSObsahem([
            'mimetype'    => 'application/vnd.oasis.opendocument.text',
            'content.xml' => $obsah,
        ]);
    }

    /**
     * `content.xml` jako ho píše LibreOffice: s jmennými prostory včetně skriptů, ale bez jediného skriptu.
     */
    private static function odfObsah(string $telo = ''): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"'
            . ' xmlns:xlink="http://www.w3.org/1999/xlink" office:version="1.3">'
            . '<office:scripts/><office:body><office:text><text:p>Ahoj</text:p>' . $telo . '</office:text></office:body>'
            . '</office:document-content>';
    }

    /**
     * Stránka Ogg: hlavička, jeden segment s paketem. `$zacatekProudu` je příznak první stránky proudu.
     */
    private static function oggStranka(string $paket, bool $zacatekProudu = true, int $proud = 1, int $poradi = 0): string
    {
        return 'OggS' . "\0" . chr($zacatekProudu ? 0x02 : 0x00) . str_repeat("\0", 8) . pack('VV', $proud, $poradi)
            . str_repeat("\0", 4) . chr(1) . chr(strlen($paket)) . $paket;
    }

    private static function oggVorbisBajty(): string
    {
        return self::oggStranka("\x01vorbis" . str_repeat("\0", 23))
            . self::oggStranka(str_repeat("\0", 40), false, 1, 1);
    }

    private static function oggOpusBajty(): string
    {
        return self::oggStranka('OpusHead' . str_repeat("\0", 11));
    }

    /**
     * Ogg s obrazem: proud Theora vedle zvuku.
     */
    private static function oggSVideemBajty(): string
    {
        return self::oggStranka("\x01vorbis" . str_repeat("\0", 23), true, 1)
            . self::oggStranka("\x80theora" . str_repeat("\0", 30), true, 2);
    }

    /**
     * Osm zvukových proudů a za nimi devátý, Theora: soubor, který by prošel, kdyby se kontrolovalo jen prvních osm.
     */
    private static function oggSDevatymProudemVideoBajty(): string
    {
        $stranky = '';
        for ($proud = 1; $proud <= 8; ++$proud) {
            $stranky .= self::oggStranka("\x01vorbis" . str_repeat("\0", 23), true, $proud);
        }

        return $stranky . self::oggStranka("\x80theora" . str_repeat("\0", 30), true, 9);
    }

    /**
     * Zřetězený Ogg: zvuk a za ním, po stránkách s daty, další řetěz s obrazem.
     */
    private static function oggZretezenySVideemBajty(): string
    {
        return self::oggVorbisBajty()
            . self::oggStranka("\x80theora" . str_repeat("\0", 30), true, 2);
    }

    /**
     * Nahrávka s useknutým koncem, třeba přerušené stahování: začátek je v pořádku.
     */
    private static function oggSUseknutymKoncemBajty(): string
    {
        return self::oggVorbisBajty() . substr(self::oggStranka(str_repeat("\0", 100), false, 1, 2), 0, 40);
    }

    private static function wavBajty(int $bajtuZvuku = 400): string
    {
        $format = pack('vvVVvv', 1, 1, 8000, 8000, 1, 8);

        return 'RIFF' . pack('V', 4 + 8 + strlen($format) + 8 + $bajtuZvuku) . 'WAVE'
            . 'fmt ' . pack('V', strlen($format)) . $format
            . 'data' . pack('V', $bajtuZvuku) . str_repeat("\x80", $bajtuZvuku);
    }

    /**
     * WAV, kde za hlavičkou chybí popis formátu.
     */
    private static function wavBezFormatuBajty(): string
    {
        return 'RIFF' . pack('V', 4 + 8 + 100) . 'WAVEdata' . pack('V', 100) . str_repeat("\x80", 100);
    }

    private static function mp4Krabice(string $typ, string $obsah): string
    {
        return pack('N', 8 + strlen($obsah)) . $typ . $obsah;
    }

    /**
     * Stopa MP4 s popisem druhu (`soun` je zvuk, `vide` obraz).
     */
    private static function mp4Stopa(string $druh): string
    {
        $hdlr = self::mp4Krabice('hdlr', "\0\0\0\0\0\0\0\0" . $druh . str_repeat("\0", 12) . "stopa\0");

        return self::mp4Krabice('trak', self::mp4Krabice('mdia', $hdlr));
    }

    /**
     * @param list<string> $druhyStop druhy stop v pořadí
     */
    private static function mp4Bajty(string $znacka, array $druhyStop): string
    {
        return self::mp4Krabice('ftyp', $znacka . "\0\0\0\0" . $znacka . 'mp42')
            . self::mp4Krabice('mdat', str_repeat("\0", 200))
            . self::mp4Krabice('moov', implode('', array_map(self::mp4Stopa(...), $druhyStop)));
    }

    /**
     * MP4 s popisem stop z hotových `trak`.
     */
    private static function mp4SeStopami(string $znacka, string ...$stopy): string
    {
        return self::mp4Krabice('ftyp', $znacka . "\0\0\0\0" . $znacka . 'mp42')
            . self::mp4Krabice('mdat', str_repeat("\0", 200))
            . self::mp4Krabice('moov', implode('', $stopy));
    }

    private static function mp4Popis(string $druh): string
    {
        return self::mp4Krabice('hdlr', "\0\0\0\0\0\0\0\0" . $druh . str_repeat("\0", 12) . "stopa\0");
    }

    /**
     * Stopa s dvěma popisy, zvukovým a obrazovým, která by prošla, kdyby se četl jen první.
     */
    private static function mp4StopaSDvemaPopisyBajty(): string
    {
        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun')) . self::mp4Krabice('mdia', self::mp4Popis('vide'))));
    }

    private static function mp4StopaBezPopisuBajty(): string
    {
        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', '')), self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun'))));
    }

    private static function mp4StopaNeznamehoDruhuBajty(): string
    {
        return self::mp4SeStopami('M4A ', self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('soun'))), self::mp4Krabice('trak', self::mp4Krabice('mdia', self::mp4Popis('xxxx'))));
    }

    private static function m4aBajty(): string
    {
        return self::mp4Bajty('M4A ', ['soun']);
    }

    /**
     * AVIF 16 × 12 px, 328 bajtů, vytvořený `imageavif()`.
     */
    private static function avifBajty(): string
    {
        return (string) base64_decode(
            'AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADybWV0YQAAAAAAAAAoaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAGxpYmF2aWYAAAAADnBpdG0AAAAAAAEAAAAeaWxvYwAAAABEAAABAAEAAAABAAABGgAAAC4AAAAoaWluZgAAAAAAAQAAABppbmZlAgAAAAABAABhdjAxQ29sb3IAAAAAamlwcnAAAABLaXBjbwAAABRpc3BlAAAAAAAAABAAAAAMAAAAEHBpeGkAAAAAAwgICAAAAAxhdjFDgQAMAAAAABNjb2xybmNseAABAA0AAYAAAAAXaXBtYQAAAAAAAAABAAEEAQKDBAAAADZtZGF0EgAKCRgM/tggIaAwgDIfGwAaaaaEAL/XpRSCds7BKNcSeteYdPWZqBWVX5/B+g==',
            true,
        );
    }

    /**
     * Snímky MPEG vrstvy III vyplněné nulami. Se stejnou hlavičkou má každý snímek stejnou délku.
     *
     * @param int    $snimku   kolik snímků za sebou
     * @param int    $id3Bajtu velikost ID3v2 značky na začátku, 0 pro soubor bez ní
     * @param string $hlavicka čtyři bajty hlavičky snímku
     */
    private static function mp3Bajty(int $snimku = 3, int $id3Bajtu = 0, string $hlavicka = self::HLAVICKA_MP3): string
    {
        $delkaSnimku = [
            self::HLAVICKA_MP3 => 417,
            "\xFF\xFB\x92\x00" => 418,
            "\xFF\xF3\x48\x00" => 144,
        ][$hlavicka] ?? 417;
        $id3 = '';
        if ($id3Bajtu > 0) {
            // velikost značky se zapisuje po sedmi bitech
            $id3 = "ID3\x03\x00\x00" . pack('C4', ($id3Bajtu >> 21) & 0x7F, ($id3Bajtu >> 14) & 0x7F, ($id3Bajtu >> 7) & 0x7F, $id3Bajtu & 0x7F)
                . str_repeat("\0", $id3Bajtu);
        }

        return $id3 . str_repeat($hlavicka . str_repeat("\0", $delkaSnimku - 4), $snimku);
    }

    /**
     * @param array<string, string> $polozky název položky => obsah
     */
    private static function zipSObsahem(array $polozky): string
    {
        $cesta = (string) tempnam(sys_get_temp_dir(), 'gc-zip');
        $zip = new \ZipArchive();
        $zip->open($cesta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($polozky as $nazev => $obsah) {
            $zip->addFromString($nazev, $obsah);
        }
        $zip->close();
        $bajty = (string) file_get_contents($cesta);
        unlink($cesta);

        return $bajty;
    }

    private static function contentTypes(string $cast, string $druh): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Override PartName="' . $cast . '" ContentType="' . $druh . '"/></Types>';
    }
}
