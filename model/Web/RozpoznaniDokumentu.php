<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Pozná PDF, dokumenty (DOCX, ODT, ODS, XLSX), archivy ZIP a zvuk (MP3, OGG, WAV, M4A) podle obsahu souboru,
 * ne podle přípony od prohlížeče. Kontroluje hlavičku a stavbu souboru, ne to, jestli jde otevřít. Dokument s makry
 * nebo vloženými objekty, prezentace a video se nepoznají.
 */
final class RozpoznaniDokumentu
{
    private const ZIP_OBYCEJNY = 'zip';
    private const ZIP_XLSX = 'xlsx';
    private const ZIP_DOCX = 'docx';
    private const ZIP_ODT = 'odt';
    private const ZIP_ODS = 'ods';
    private const ZIP_OFFICE = 'office';

    private const MAX_BAJTU_CONTENT_TYPES = 1_048_576;
    /** Skutečný dokument má desítky až stovky položek, archiv s víc se za dokument nepovažuje a neprochází se po jedné */
    private const MAX_POLOZEK_DOKUMENTU = 10_000;
    /** Víc XML dokumentu LibreOffice se na odkazy na makra neprohledává, takový dokument se nepřijme */
    private const MAX_BAJTU_XML_ODF = 16_777_216;
    /** Všechny XML části dokumentu dohromady; skutečný dokument má jednotky MB */
    private const MAX_BAJTU_XML_CELKEM = 67_108_864;
    private const MAX_XML_CASTI = 300;
    private const MP3_PRUZKUM_BAJTU = 4096;
    /** Víc stránek Ogg se neprochází; soubor o 200 MB má podle velikosti stránek nanejvýš desítky tisíc */
    private const OGG_MAX_STRAN = 300_000;
    private const OGG_PRUZKUM_BAJTU = 16;
    private const WAV_MAX_BLOKU = 32;
    private const MP4_MAX_KRABIC = 64;
    /** Popis stop skutečného souboru má desítky kB, i dlouhá nahrávka s kapitolami má do jednotek MB */
    private const MP4_MAX_BAJTU_MOOV = 8_388_608;
    /** Druhy stop MP4, které se přijímají vedle zvuku: kapitoly, popisky, časový kód. Cokoli jiného, tedy i video, ne */
    private const MP4_POVOLENE_STOPY = ['soun', 'text', 'meta', 'sbtl', 'subt', 'tmcd', 'hint', 'clcp'];
    /** Kodeky obrazu v popisu vzorků stopy; přehrávač volí dekodér podle nich, ne podle druhu stopy */
    private const MP4_KODEKY_OBRAZU = ['avc1', 'avc3', 'hvc1', 'hev1', 'mp4v', 'vp08', 'vp09', 'av01', 's263', 'encv', 'dvh1', 'dvhe', 'jpeg', 'mjp2'];
    /** Souborů `.rels` v dokumentu z Wordu; skutečný jich má desítky */
    private const MAX_SOUBORU_VAZEB = 500;

    /**
     * Balíky Open Packaging Conventions, které se přijímají: hlavní část a její typ v `[Content_Types].xml`
     * a co v balíku být nesmí (začátek názvu položky malými písmeny, lomítko na konci je celá složka). Makra mají vlastní
     * typ obsahu bez ohledu na to, kde v archivu leží.
     */
    private const BALIKY_OPC = [
        self::ZIP_XLSX => [
            'cast'     => '/xl/workbook.xml',
            'typ'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
            'zakazane' => ['xl/vbaproject.bin', 'xl/macrosheets/'],
        ],
        self::ZIP_DOCX => [
            'cast'     => '/word/document.xml',
            'typ'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'zakazane' => ['word/vbaproject.bin', 'word/activex/', 'word/embeddings/'],
        ],
    ];
    private const TYPY_ODF = [
        'application/vnd.oasis.opendocument.text'        => self::ZIP_ODT,
        'application/vnd.oasis.opendocument.spreadsheet' => self::ZIP_ODS,
    ];
    /** Makra (`Basic`, `Scripts`) a vložené objekty včetně grafů (`Object 1`, `ObjectReplacements`) */
    private const ZAKAZANE_SLOZKY_ODF = ['basic/', 'scripts/', 'dialogs/', 'object ', 'objectreplacements/'];
    private const ODF_JMENNY_PROSTOR_SKRIPTU = 'urn:oasis:names:tc:opendocument:xmlns:script:1.0';
    /** Vazby, které z dokumentu z Wordu dělají víc než text: vložený objekt nebo balík, ActiveX, makra, vzdálená šablona */
    private const NEBEZPECNA_VAZBA = '~/(?:oleObject|package|control|vbaProject\w*|vbaData|attachedTemplate|frame|subDocument|externalLink\w*)$~i';

    /** Hlavní značka souboru M4A; QuickTime (`qt  `) a 3GPP jsou video formáty, i když mají jen zvuk */
    private const M4A_ZNACKY = ['M4A ', 'M4B ', 'mp42', 'mp41', 'isom', 'iso2'];
    /** Zvukové kodeky na začátku proudu Ogg a Skeleton (popis proudů), který sám nic nepřehrává */
    private const OGG_KODEKY_ZVUKU = ["\x01vorbis", 'OpusHead', "\x7fFLAC", 'Speex   '];
    private const OGG_SKELETON = "fishead\0";

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
        $zacatek = (string) @file_get_contents($cesta, false, null, 0, 12);
        if (str_starts_with($zacatek, '%PDF-')) {
            return NahravaniObrazku::PDF;
        }
        if (str_starts_with($zacatek, "PK\x03\x04")) {
            return match (self::druhZipu($cesta)) {
                self::ZIP_OBYCEJNY => NahravaniObrazku::ZIP,
                self::ZIP_XLSX     => NahravaniObrazku::XLSX,
                self::ZIP_DOCX     => NahravaniObrazku::DOCX,
                self::ZIP_ODT      => NahravaniObrazku::ODT,
                self::ZIP_ODS      => NahravaniObrazku::ODS,
                default            => null,
            };
        }
        if (str_starts_with($zacatek, 'OggS')) {
            return self::jeOggSeZvukem($cesta)
                ? NahravaniObrazku::OGG
                : null;
        }
        if (str_starts_with($zacatek, 'RIFF') && substr($zacatek, 8, 4) === 'WAVE') {
            return self::jeWav($cesta)
                ? NahravaniObrazku::WAV
                : null;
        }
        if (substr($zacatek, 4, 4) === 'ftyp') {
            return self::jeM4a($cesta)
                ? NahravaniObrazku::M4A
                : null;
        }

        return self::jeMp3($cesta)
            ? NahravaniObrazku::MP3
            : null;
    }

    /**
     * Dokument z Wordu, PowerPointu, LibreOffice a podobných je ZIP, ale ne ten, který organizátor myslel
     * (prezentace, dokument s makry nebo vloženými objekty).
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
                return self::druhBalikuOpc($zip, $contentTypes);
            }
            $mimetype = $zip->getFromName('mimetype', 64);

            return is_string($mimetype) && str_starts_with($mimetype, 'application/vnd.oasis.opendocument.')
                ? self::druhBalikuOdf($zip, trim($mimetype))
                : self::ZIP_OBYCEJNY;
        } finally {
            $zip->close();
        }
    }

    /**
     * Dokument je ten, jehož hlavní část (třeba `/word/document.xml`) má v `[Content_Types].xml` správný typ,
     * a ne s makry. Samotný řetězec s typem by stačil i dokumentu z jiného programu, který ho má v komentáři.
     */
    private static function druhBalikuOpc(\ZipArchive $zip, string $contentTypes): string
    {
        $xml = new \DOMDocument();
        // loadXML() s prázdným řetězcem nevrací false, ale vyhodí ValueError
        if ($contentTypes === '' || !@$xml->loadXML($contentTypes, LIBXML_NONET)) {
            return self::ZIP_OFFICE;
        }
        $typyCasti = [];
        $vsechnyTypy = [];
        foreach ([...$xml->getElementsByTagName('Override'), ...$xml->getElementsByTagName('Default')] as $cast) {
            $typ = $cast->getAttribute('ContentType');
            if (preg_match('~macrosheet|vbaProject|macroEnabled~i', $typ) === 1) {
                return self::ZIP_OFFICE;
            }
            $vsechnyTypy[] = $typ;
            // `Default` nemá `PartName`, takže by se v mapě všechny sesypaly pod jeden klíč
            $typyCasti[$cast->getAttribute('PartName')] = $typ;
        }
        foreach (self::BALIKY_OPC as $druh => $balik) {
            if (($typyCasti[$balik['cast']] ?? null) !== $balik['typ']) {
                continue;
            }

            return self::obsahujeNejakouZ($zip, $balik['zakazane'])
                || ($druh === self::ZIP_DOCX && self::maDocxVazbyNaObjektyNeboMakra($zip, $vsechnyTypy))
                    ? self::ZIP_OFFICE
                    : $druh;
        }

        return self::ZIP_OFFICE;
    }

    /**
     * Vložený objekt, balík nebo ovládací prvek ActiveX, makra a vzdálená šablona jsou vždy vazba v `.rels` a typ
     * obsahu, ať jejich část leží v archivu kdekoli. Složky, které hledá @see obsahujeNejakouZ(), jsou jen obvyklé místo.
     *
     * @param list<string> $vsechnyTypy typy obsahu všech částí a přípon z `[Content_Types].xml`
     */
    private static function maDocxVazbyNaObjektyNeboMakra(\ZipArchive $zip, array $vsechnyTypy): bool
    {
        foreach ($vsechnyTypy as $typ) {
            if (preg_match('~oleObject|activeX~i', $typ) === 1) {
                return true;
            }
        }
        $souboruVazeb = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (!str_ends_with(self::normalizujNazevPolozky((string) $zip->getNameIndex($index)), '.rels')) {
                continue;
            }
            $obsah = $zip->getFromIndex($index, self::MAX_BAJTU_CONTENT_TYPES + 1);
            $xml = new \DOMDocument();
            // vazby, které se nedají přečíst, se nepřijmou; loadXML() s prázdným řetězcem vyhodí ValueError
            if (++$souboruVazeb > self::MAX_SOUBORU_VAZEB
                || !is_string($obsah)
                || $obsah === ''
                || strlen($obsah) > self::MAX_BAJTU_CONTENT_TYPES
                || !@$xml->loadXML($obsah, LIBXML_NONET)
            ) {
                return true;
            }
            foreach ($xml->getElementsByTagName('Relationship') as $vazba) {
                $typVazby = $vazba->getAttribute('Type');
                // vzdálený obrázek nebo rám otevře adresu při otevření dokumentu; odkaz jen čeká na kliknutí
                $jeVzdalenaNeodkaz = strcasecmp($vazba->getAttribute('TargetMode'), 'External') === 0
                    && !str_ends_with(strtolower($typVazby), '/hyperlink');
                if ($jeVzdalenaNeodkaz || preg_match(self::NEBEZPECNA_VAZBA, $typVazby) === 1) {
                    return true;
                }
            }
        }

        return self::maDocxPoleDde($zip);
    }

    /**
     * Pole `DDE` a `DDEAUTO` spustí příkaz, i když dokument nemá makra. Kód pole bývá rozdělený do víc prvků
     * `instrText`, proto se čtou všechny za sebou jako jeden text; čte se jako XML, ne jako řetězec, aby ho nezakryla entita.
     */
    private static function maDocxPoleDde(\ZipArchive $zip): bool
    {
        $casti = 0;
        $bajtu = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $nazev = self::normalizujNazevPolozky((string) $zip->getNameIndex($index));
            if (!str_starts_with($nazev, 'word/') || !str_ends_with($nazev, '.xml')) {
                continue;
            }
            $xml = $zip->getFromIndex($index, self::MAX_BAJTU_XML_ODF + 1);
            $bajtu += is_string($xml) ? strlen($xml) : 0;
            if (++$casti > self::MAX_XML_CASTI
                || !is_string($xml)
                || $xml === ''
                || strlen($xml) > self::MAX_BAJTU_XML_ODF
                || $bajtu > self::MAX_BAJTU_XML_CELKEM
                || self::xmlMaPoleDde($xml)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function xmlMaPoleDde(string $xml): bool
    {
        $puvodniChyby = libxml_use_internal_errors(true);
        $ctecka = new \XMLReader();
        try {
            if (!$ctecka->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return true;
            }
            $kodyPoli = '';
            while ($ctecka->read()) {
                if ($ctecka->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($ctecka->localName === 'instrText') {
                    $kodyPoli .= $ctecka->readString();
                } elseif ($ctecka->hasAttributes) {
                    // jednoduché pole: kód je atribut `instr`
                    while ($ctecka->moveToNextAttribute()) {
                        if ($ctecka->localName === 'instr') {
                            $kodyPoli .= ' ' . $ctecka->value;
                        }
                    }
                    $ctecka->moveToElement();
                }
            }

            return libxml_get_errors() !== [] || preg_match('~DDE(?:AUTO)?\b~i', $kodyPoli) === 1;
        } finally {
            $ctecka->close();
            libxml_clear_errors();
            libxml_use_internal_errors($puvodniChyby);
        }
    }

    /**
     * Názvy částí balíku se nerozlišují podle velikosti písmen a některé programy zapisují zpětná lomítka.
     */
    private static function normalizujNazevPolozky(string $nazev): string
    {
        return ltrim(strtolower(str_replace('\\', '/', $nazev)), './');
    }

    /**
     * @param list<string> $zakazane začátky názvů položek malými písmeny, lomítko na konci je celá složka
     */
    private static function obsahujeNejakouZ(\ZipArchive $zip, array $zakazane): bool
    {
        if ($zip->numFiles > self::MAX_POLOZEK_DOKUMENTU) {
            return true;
        }
        $videne = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $nazev = self::normalizujNazevPolozky((string) $zip->getNameIndex($index));
            // dvě položky stejného názvu: kontrola čte jednu, program, který dokument otevře, může druhou
            if (isset($videne[$nazev])) {
                return true;
            }
            $videne[$nazev] = true;
            foreach ($zakazane as $zakazana) {
                if (str_starts_with($nazev, $zakazana)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Dokument LibreOffice bez maker a vložených objektů: bez složek `Basic`, `Scripts`, `Dialogs` a `Object`, bez prvků
     * a atributů ve jmenném prostoru skriptů a bez odkazů na makra v žádné jeho XML části (ty by mohly spustit makro
     * z knihovny, kterou má čtenář nainstalovanou).
     */
    private static function druhBalikuOdf(\ZipArchive $zip, string $mimetype): string
    {
        $druh = self::TYPY_ODF[$mimetype] ?? null;
        if ($druh === null || $zip->numFiles > self::MAX_POLOZEK_DOKUMENTU) {
            return self::ZIP_OFFICE;
        }
        if (self::obsahujeNejakouZ($zip, self::ZAKAZANE_SLOZKY_ODF)) {
            return self::ZIP_OFFICE;
        }
        $casti = 0;
        $bajtu = 0;
        // i `Configurations2/accelerator` a `toolbar` umějí navázat příkaz na makro
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (!str_ends_with(self::normalizujNazevPolozky((string) $zip->getNameIndex($index)), '.xml')) {
                continue;
            }
            $xml = $zip->getFromIndex($index, self::MAX_BAJTU_XML_ODF + 1);
            $bajtu += is_string($xml) ? strlen($xml) : 0;
            if (++$casti > self::MAX_XML_CASTI
                || !is_string($xml)
                || $xml === ''
                || strlen($xml) > self::MAX_BAJTU_XML_ODF
                || $bajtu > self::MAX_BAJTU_XML_CELKEM
                || self::odfMaSkriptNeboOdkazNaMakro($xml)
            ) {
                return self::ZIP_OFFICE;
            }
        }

        return $druh;
    }

    /**
     * Čte XML jako XML, ne jako text: odkaz zapsaný znakovou entitou nebo prvek s jiným prefixem jmenného prostoru
     * by hledání řetězce minulo. XML, které se nedá přečíst, se nepřijme.
     */
    private static function odfMaSkriptNeboOdkazNaMakro(string $xml): bool
    {
        $puvodniChyby = libxml_use_internal_errors(true);
        $ctecka = new \XMLReader();
        try {
            if (!$ctecka->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return true;
            }
            while ($ctecka->read()) {
                if ($ctecka->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($ctecka->namespaceURI === self::ODF_JMENNY_PROSTOR_SKRIPTU) {
                    return true;
                }
                if (!$ctecka->hasAttributes) {
                    continue;
                }
                while ($ctecka->moveToNextAttribute()) {
                    if ($ctecka->namespaceURI === self::ODF_JMENNY_PROSTOR_SKRIPTU
                        || preg_match('~^\s*(?:vnd\.sun\.star\.script|macro):~i', $ctecka->value) === 1
                    ) {
                        return true;
                    }
                }
                $ctecka->moveToElement();
            }

            return libxml_get_errors() !== [];
        } finally {
            $ctecka->close();
            libxml_clear_errors();
            libxml_use_internal_errors($puvodniChyby);
        }
    }

    /**
     * Ogg je jen kontejner, zvuk to je podle kodeku v prvním paketu každého proudu. Prochází se celý soubor po
     * stránkách, protože další proud (nebo zřetězený Ogg s videem) může začít kdekoli. Soubor s proudem, který zvuk
     * není (Theora, VP8), bez zvukového proudu nebo s poškozenou stavbou se nepřijme; useknutý konec nevadí.
     */
    private static function jeOggSeZvukem(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return false;
        }
        try {
            $velikostSouboru = (int) @filesize($cesta);
            $pozice = 0;
            $maZvuk = false;
            for ($stranka = 0; $stranka < self::OGG_MAX_STRAN; $stranka++) {
                if ($pozice >= $velikostSouboru) {
                    return $maZvuk;
                }
                fseek($soubor, $pozice);
                $hlavicka = (string) fread($soubor, 27);
                if (strlen($hlavicka) < 27) {
                    // useknutá hlavička poslední stránky
                    return $maZvuk && $stranka > 0;
                }
                if (!str_starts_with($hlavicka, 'OggS') || $hlavicka[4] !== "\0") {
                    return false;
                }
                $zacinaProud = (ord($hlavicka[5]) & 0x02) !== 0;
                if ($stranka === 0 && !$zacinaProud) {
                    return false;
                }
                $pocetSegmentu = ord($hlavicka[26]);
                $tabulka = $pocetSegmentu > 0
                    ? (string) fread($soubor, $pocetSegmentu)
                    : '';
                if (strlen($tabulka) < $pocetSegmentu) {
                    return $maZvuk && $stranka > 0;
                }
                if ($zacinaProud) {
                    $zacatekPaketu = (string) fread($soubor, self::OGG_PRUZKUM_BAJTU);
                    if (!str_starts_with($zacatekPaketu, self::OGG_SKELETON)) {
                        if (!self::jeKodekZvuku($zacatekPaketu)) {
                            return false;
                        }
                        $maZvuk = true;
                    }
                }
                $pozice += 27 + $pocetSegmentu + ($pocetSegmentu > 0 ? array_sum(array_map(ord(...), str_split($tabulka))) : 0);
            }

            // tolik stránek soubor této velikosti mít nemá
            return false;
        } finally {
            fclose($soubor);
        }
    }

    private static function jeKodekZvuku(string $zacatekPaketu): bool
    {
        foreach (self::OGG_KODEKY_ZVUKU as $kodek) {
            if (str_starts_with($zacatekPaketu, $kodek)) {
                return true;
            }
        }

        return false;
    }

    /**
     * RIFF/WAVE s blokem `fmt ` a za ním blokem `data`.
     */
    private static function jeWav(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return false;
        }
        try {
            $velikostSouboru = (int) @filesize($cesta);
            fseek($soubor, 12);
            $mameFmt = false;
            for ($blok = 0; $blok < self::WAV_MAX_BLOKU; $blok++) {
                $hlavickaBloku = (string) fread($soubor, 8);
                if (strlen($hlavickaBloku) < 8) {
                    return false;
                }
                $nazev = substr($hlavickaBloku, 0, 4);
                $velikost = (int) unpack('V', substr($hlavickaBloku, 4, 4))[1];
                if ($nazev === 'data') {
                    return $mameFmt;
                }
                $dalsiBlok = (int) ftell($soubor) + $velikost + ($velikost & 1);
                if ($nazev === 'fmt ') {
                    $format = (string) fread($soubor, 2);
                    // 0 je neznámý formát; blok `fmt ` má aspoň 16 bajtů
                    if ($velikost < 16 || strlen($format) < 2 || (int) unpack('v', $format)[1] === 0) {
                        return false;
                    }
                    $mameFmt = true;
                }
                if ($dalsiBlok > $velikostSouboru) {
                    return false;
                }
                fseek($soubor, $dalsiBlok);
            }

            return false;
        } finally {
            fclose($soubor);
        }
    }

    /**
     * MP4 se značkou souboru se zvukem a s popisem stop, kde je aspoň jedna zvuková a žádná obrazová.
     * AVIF a HEIC mají stejný kontejner, ale jinou značku, a jsou to obrázky.
     */
    private static function jeM4a(string $cesta): bool
    {
        $soubor = @fopen($cesta, 'rb');
        if ($soubor === false) {
            return false;
        }
        try {
            $velikostSouboru = (int) @filesize($cesta);
            $pozice = 0;
            for ($krabice = 0; $krabice < self::MP4_MAX_KRABIC && $pozice + 8 <= $velikostSouboru; $krabice++) {
                fseek($soubor, $pozice);
                $hlavicka = (string) fread($soubor, 16);
                $velikost = (int) unpack('N', substr($hlavicka, 0, 4))[1];
                $typ = substr($hlavicka, 4, 4);
                $delkaHlavicky = 8;
                if ($velikost === 1) {
                    if (strlen($hlavicka) < 16) {
                        return false;
                    }
                    $velikost = (int) unpack('J', substr($hlavicka, 8, 8))[1];
                    $delkaHlavicky = 16;
                } elseif ($velikost === 0) {
                    $velikost = $velikostSouboru - $pozice;
                }
                if ($velikost < $delkaHlavicky || $pozice + $velikost > $velikostSouboru) {
                    return false;
                }
                if ($krabice === 0 && ($typ !== 'ftyp' || !in_array(substr($hlavicka, 8, 4), self::M4A_ZNACKY, true))) {
                    return false;
                }
                if ($typ === 'moov') {
                    if ($velikost > self::MP4_MAX_BAJTU_MOOV) {
                        return false;
                    }
                    fseek($soubor, $pozice + $delkaHlavicky);

                    return self::moovMaJenZvuk((string) fread($soubor, $velikost - $delkaHlavicky));
                }
                $pozice += $velikost;
            }

            return false;
        } finally {
            fclose($soubor);
        }
    }

    private static function moovMaJenZvuk(string $moov): bool
    {
        $mamZvuk = false;
        foreach (self::podkrabice($moov, 0, strlen($moov)) as $typ => [$od, $do]) {
            if ($typ !== 'trak') {
                continue;
            }
            $druhyStop = self::druhyStopMp4($moov, $od, $do);
            // stopa bez popisu nebo s neznámým druhem se nepřijme, video se jím dá schovat
            if ($druhyStop === [] || array_diff($druhyStop, self::MP4_POVOLENE_STOPY) !== []) {
                return false;
            }
            $mamZvuk = $mamZvuk || in_array('soun', $druhyStop, true);
        }

        return $mamZvuk;
    }

    /**
     * @return list<string> druhy z každé krabice `hdlr` ve stopě (`soun`, `vide`, …); víc krabic se zkontroluje všech
     */
    private static function druhyStopMp4(string $data, int $odTrak, int $doTrak): array
    {
        $druhy = [];
        foreach (self::podkrabice($data, $odTrak, $doTrak) as $typ => [$odMdia, $doMdia]) {
            if ($typ !== 'mdia') {
                continue;
            }
            foreach (self::podkrabice($data, $odMdia, $doMdia) as $typPodkrabice => [$odPodkrabice, $doPodkrabice]) {
                // verze a příznaky (4), předdefinovaná hodnota (4), potom druh stopy
                if ($typPodkrabice === 'hdlr' && $doPodkrabice - $odPodkrabice >= 12) {
                    $druhy[] = substr($data, $odPodkrabice + 8, 4);
                } elseif ($typPodkrabice === 'minf' && self::maStopaObraz($data, $odPodkrabice, $doPodkrabice)) {
                    // stopa, která se hlásí jako zvuk, ale podle hlavičky nebo popisu vzorků je obraz
                    $druhy[] = 'vide';
                }
            }
        }

        return $druhy;
    }

    private static function maStopaObraz(string $data, int $odMinf, int $doMinf): bool
    {
        foreach (self::podkrabice($data, $odMinf, $doMinf) as $typ => [$od, $do]) {
            if ($typ === 'vmhd') {
                return true;
            }
            if ($typ !== 'stbl') {
                continue;
            }
            foreach (self::podkrabice($data, $od, $do) as $typTabulky => [$odStsd, $doStsd]) {
                // verze a příznaky (4), počet záznamů (4), potom první záznam: velikost (4) a kodek (4)
                if ($typTabulky === 'stsd'
                    && $doStsd - $odStsd >= 16
                    && in_array(substr($data, $odStsd + 12, 4), self::MP4_KODEKY_OBRAZU, true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Krabice ISO BMFF jedné úrovně v rozsahu `<$od, $do)`: čtyřznakový typ => rozsah obsahu krabice. Obsah se
     * nekopíruje, vnořené krabice se čtou ze stejného řetězce.
     *
     * @return \Generator<string, array{int, int}>
     */
    private static function podkrabice(string $data, int $od, int $do): \Generator
    {
        $pozice = $od;
        while ($pozice + 8 <= $do) {
            $velikost = (int) unpack('N', substr($data, $pozice, 4))[1];
            $typ = substr($data, $pozice + 4, 4);
            $delkaHlavicky = 8;
            if ($velikost === 1) {
                if ($pozice + 16 > $do) {
                    return;
                }
                $velikost = (int) unpack('J', substr($data, $pozice + 8, 8))[1];
                $delkaHlavicky = 16;
            } elseif ($velikost === 0) {
                $velikost = $do - $pozice;
            }
            if ($velikost < $delkaHlavicky || $pozice + $velikost > $do) {
                return;
            }
            yield $typ => [$pozice + $delkaHlavicky, $pozice + $velikost];
            $pozice += $velikost;
        }
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
