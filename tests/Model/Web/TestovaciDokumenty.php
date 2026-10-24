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

    private static function odtBajty(): string
    {
        return self::zipSObsahem([
            'mimetype'    => 'application/vnd.oasis.opendocument.text',
            'content.xml' => '<office:document-content/>',
        ]);
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
