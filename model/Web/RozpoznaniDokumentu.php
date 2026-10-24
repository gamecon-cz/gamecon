<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Pozná PDF, MP3, ZIP a XLSX podle obsahu souboru, ne podle přípony od prohlížeče. Kontroluje hlavičku
 * a stavbu souboru, ne to, jestli jde otevřít.
 */
final class RozpoznaniDokumentu
{
    private const ZIP_OBYCEJNY = 'zip';
    private const ZIP_XLSX = 'xlsx';
    private const ZIP_OFFICE = 'office';

    private const MAX_BAJTU_CONTENT_TYPES = 1_048_576;
    /** Skutečný sešit má desítky položek, archiv s víc se za sešit nepovažuje a neprochází se po jedné */
    private const MAX_POLOZEK_SESITU = 10_000;
    private const MP3_PRUZKUM_BAJTU = 4096;
    private const XLSX_HLAVNI_CAST = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';

    /** Bitrate vrstvy III v kb/s podle indexu z hlavičky snímku */
    private const MP3_BITRATE_MPEG1 = [1 => 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private const MP3_BITRATE_MPEG2 = [1 => 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    /** Vzorkovací frekvence podle verze MPEG (0 = 2.5, 2 = 2, 3 = 1) a indexu z hlavičky */
    private const MP3_VZORKOVANI = [
        0 => [11025, 12000, 8000],
        2 => [22050, 24000, 16000],
        3 => [44100, 48000, 32000],
    ];

    /**
     * @return string|null jedna z konstant @see NahravaniObrazku::DOKUMENTY, null pro cokoli jiného
     */
    public static function mime(string $cesta): ?string
    {
        $zacatek = (string) @file_get_contents($cesta, false, null, 0, 8);
        if (str_starts_with($zacatek, '%PDF-')) {
            return NahravaniObrazku::PDF;
        }
        if (str_starts_with($zacatek, "PK\x03\x04")) {
            return match (self::druhZipu($cesta)) {
                self::ZIP_OBYCEJNY => NahravaniObrazku::ZIP,
                self::ZIP_XLSX     => NahravaniObrazku::XLSX,
                default            => null,
            };
        }

        return self::jeMp3($cesta)
            ? NahravaniObrazku::MP3
            : null;
    }

    /**
     * Dokument z Wordu, PowerPointu, LibreOffice a podobných je ZIP, ale ne ten, který organizátor myslel.
     */
    public static function jeBalikOffice(string $cesta): bool
    {
        return self::druhZipu($cesta) === self::ZIP_OFFICE;
    }

    /**
     * @return string|null null pro poškozený archiv
     */
    private static function druhZipu(string $cesta): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($cesta, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS) !== true) {
            return null;
        }
        try {
            $contentTypes = $zip->getFromName('[Content_Types].xml', self::MAX_BAJTU_CONTENT_TYPES);
            if (is_string($contentTypes)) {
                return self::jeSesitBezMaker($zip, $contentTypes)
                    ? self::ZIP_XLSX
                    : self::ZIP_OFFICE;
            }
            $mimetype = $zip->getFromName('mimetype', 64);

            return is_string($mimetype) && str_starts_with($mimetype, 'application/vnd.oasis.opendocument.')
                ? self::ZIP_OFFICE
                : self::ZIP_OBYCEJNY;
        } finally {
            $zip->close();
        }
    }

    /**
     * Sešit je hlavní část `/xl/workbook.xml` s typem XLSX, a ne s makry. Samotný řetězec s typem by stačil
     * i dokumentu z Wordu, který ho má v komentáři.
     */
    private static function jeSesitBezMaker(\ZipArchive $zip, string $contentTypes): bool
    {
        $xml = new \DOMDocument();
        // loadXML() s prázdným řetězcem nevrací false, ale vyhodí ValueError
        if ($contentTypes === '' || !@$xml->loadXML($contentTypes, LIBXML_NONET)) {
            return false;
        }
        $jeSesit = false;
        foreach ([...$xml->getElementsByTagName('Override'), ...$xml->getElementsByTagName('Default')] as $cast) {
            $typ = $cast->getAttribute('ContentType');
            // makra mají vlastní typ obsahu bez ohledu na to, kde v archivu leží
            if (preg_match('~macrosheet|vbaProject|macroEnabled~i', $typ) === 1) {
                return false;
            }
            if ($cast->getAttribute('PartName') === '/xl/workbook.xml' && $typ === self::XLSX_HLAVNI_CAST) {
                $jeSesit = true;
            }
        }
        if (!$jeSesit || $zip->numFiles > self::MAX_POLOZEK_SESITU) {
            return false;
        }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            // názvy částí balíku se nerozlišují podle velikosti písmen
            $nazev = strtolower((string) $zip->getNameIndex($index));
            if ($nazev === 'xl/vbaproject.bin' || str_starts_with($nazev, 'xl/macrosheets/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Za případnou ID3 značkou musí následovat dva MPEG snímky vrstvy III za sebou, jedna hlavička by mohla být náhoda.
     */
    private static function jeMp3(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return false;
        }
        try {
            $zacatekZvuku = self::zacatekZvukuPoId3($soubor);
            if ($zacatekZvuku === null) {
                return false;
            }
            fseek($soubor, $zacatekZvuku);
            $okno = (string) fread($soubor, self::MP3_PRUZKUM_BAJTU);
            // bez ID3 značky musí snímek začínat hned na začátku souboru, jinak by dvě náhodné hlavičky v metadatech
            // obrázku stačily; za značkou se hledá v okně, protože některé programy nechávají za ní výplň
            for ($posun = 0, $konec = $zacatekZvuku === 0 ? 1 : strlen($okno) - 3; $posun < $konec; $posun++) {
                $delkaSnimku = self::delkaSnimku(substr($okno, $posun, 4));
                if ($delkaSnimku === null) {
                    continue;
                }
                fseek($soubor, $zacatekZvuku + $posun + $delkaSnimku);
                if (self::delkaSnimku((string) fread($soubor, 4)) !== null) {
                    return true;
                }
            }

            return false;
        } finally {
            fclose($soubor);
        }
    }

    /**
     * @param resource $soubor
     * @return int|null pozice za ID3v2 značkou (0, když žádná není), null pro značku s neplatnou velikostí
     */
    private static function zacatekZvukuPoId3($soubor): ?int
    {
        $hlavicka = (string) fread($soubor, 10);
        if (!str_starts_with($hlavicka, 'ID3') || strlen($hlavicka) < 10) {
            return 0;
        }
        $velikost = 0;
        foreach (str_split(substr($hlavicka, 6, 4)) as $bajt) {
            // velikost má 4 × 7 bitů, nejvyšší bit každého bajtu musí být nulový
            if (ord($bajt) & 0x80) {
                return null;
            }
            $velikost = ($velikost << 7) | ord($bajt);
        }
        $paticka = (ord($hlavicka[5]) & 0x10) !== 0 ? 10 : 0;

        return 10 + $velikost + $paticka;
    }

    /**
     * @return int|null délka snímku v bajtech, null když čtyři bajty nejsou hlavička snímku vrstvy III
     */
    private static function delkaSnimku(string $hlavicka): ?int
    {
        if (strlen($hlavicka) < 4 || $hlavicka[0] !== "\xFF" || (ord($hlavicka[1]) & 0xE0) !== 0xE0) {
            return null;
        }
        $verze = (ord($hlavicka[1]) >> 3) & 0x03;
        $vrstva = (ord($hlavicka[1]) >> 1) & 0x03;
        $indexBitrate = ord($hlavicka[2]) >> 4;
        $indexVzorkovani = (ord($hlavicka[2]) >> 2) & 0x03;
        // verze 1 je rezervovaná, vrstva 1 je III, bitrate 0 je volný formát a 15 neplatná hodnota
        if ($verze === 1 || $vrstva !== 1 || $indexBitrate === 0 || $indexBitrate === 15 || $indexVzorkovani === 3) {
            return null;
        }
        $bitrate = ($verze === 3 ? self::MP3_BITRATE_MPEG1 : self::MP3_BITRATE_MPEG2)[$indexBitrate] * 1000;
        $vypln = (ord($hlavicka[2]) >> 1) & 0x01;

        return intdiv(($verze === 3 ? 144 : 72) * $bitrate, self::MP3_VZORKOVANI[$verze][$indexVzorkovani]) + $vypln;
    }
}
