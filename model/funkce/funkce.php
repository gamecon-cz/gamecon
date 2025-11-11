<?php

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\Exceptions\NeznamyKlicSystemovehoNastaveni;
use Granam\RemoveDiacritics\RemoveDiacritics;
use Symfony\Component\Filesystem\Filesystem;
use Michelf\MarkdownExtra;

$GLOBALS['SKRIPT_ZACATEK'] = microtime(true); // profiling

/**
 * Vrátí míru diverzifikace aktivit v poli udávajícím počty aktivit od jedno-
 * tlivých typů. Délka pole ovlivňuje výsledek (je potřeba aby obsahovalo i 0)
 */
function aktivityDiverzifikace(
    $poleTypu,
)
{
    $typu = count($poleTypu);
    $pocet = array_sum($poleTypu);
    if ($pocet == 0) return 0.0;
    $pocty = $poleTypu;
    rsort($pocty, SORT_NUMERIC);
    $max = ($pocet - $pocty[0]) / ($pocet * ($typu - 1));
    $nPocty = [];
    for ($i = 1; $i < $typu; $i++) { //první počet přeskočit
        if ($pocty[$i] / $pocet > $max)
            $nPocty[] = $max;
        else
            $nPocty[] = $pocty[$i] / $pocet;
    }

    return array_sum($nPocty) * $typu / ($typu - 1); //výsledná míra diverzifikace 0.0 - 1.0
}

/**
 * 1 okno
 * 2 okna
 * 5 oken
 * @todo 22 okna (volitelně)
 * @todo záporná čísla
 * @todo nepovinné přepisování ('%d' => 'přihlásil se 1 uživatel', případně slovy apod)
 */
function cislo(
    $i,
    $jeden,
    $dva,
    $pet,
)
{
    if ($i == 1) return $i . $jeden;
    if (1 < $i && $i < 5) return $i . $dva;
    else return $i . $pet;
}

/** Vrací datum ve stylu "pátek 14:00-18:00" na základě řádku db */
function datum2(
    $dbRadek,
)
{
    if ($dbRadek['zacatek'])
        return (new DateTimeCz($dbRadek['zacatek']))->format('l G:i') . '–' . (new DateTimeCz($dbRadek['konec']))->format('G:i');
    else
        return '';
}

/**
 * Vrací datum ve stylu 1. července
 *  akceptuje vše, co žere strtotime
 */
function datum3(
    string|DateTimeInterface $datum,
): string
{
    $datumTimestamp = ($datum instanceof DateTimeInterface)
        ? $datum->getTimestamp()
        : strtotime($datum);
    $mesic = [
        'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];

    return date('j. ', $datumTimestamp) . $mesic[date('n', $datumTimestamp) - 1];
}

function datum4(
    string|DateTimeInterface $datum,
): string
{
    $datumTimestamp = ($datum instanceof DateTimeInterface)
        ? $datum->getTimestamp()
        : strtotime($datum);

    $mesic = [
        'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];

    return date('j. ', $datumTimestamp)
        . $mesic[date('n', $datumTimestamp) - 1]
        . date(' H:i', $datumTimestamp);
}

/** Načte / uloží hodnotu do key-value storage s daným názvem */
function kvs(
    string $group,
    string $key,
    string $value = null,
): ?string {
    // Ensure LOGY directory exists
    static $logyDirCreated = false;

    if (!$logyDirCreated) {
        (new Filesystem())->mkdir(LOGY);
        $logyDirCreated = true;
    }

    // Acquire file lock to prevent parallel access issues
    $lockFile = LOGY . '/' . $group . '.lock';
    $lock = fopen($lockFile, 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new Exception("Cannot acquire lock for KVS: $group");
    }

    try {
        if (!isset($GLOBALS['CACHEDB'][$group])) {
            $db = new SQLite3(SPEC . '/' . $group . '.sqlite');
            // Enable WAL (Write-Ahead Log) mode for better concurrent access, @see https://sqlite.org/wal.html
            $db->exec('PRAGMA journal_mode=WAL');
            // Wait up to 5 seconds if database is locked
            $db->busyTimeout(5000);
            $GLOBALS['CACHEDB'][$group] = $db;
            $db->exec("CREATE TABLE IF NOT EXISTS kvs (k INTEGER PRIMARY KEY, v TEXT)");
        } else {
            $db = $GLOBALS['CACHEDB'][$group];
            assert($db instanceof SQLite3);
        }

        $numbericKey = scrc32($key);

        if ($value === null) {
            // načítání
            $v = $db->query('SELECT v FROM kvs WHERE k = ' . $numbericKey)->fetchArray(SQLITE3_NUM);
            if ($v === false) {
                return null;
            }
            assert(count($v) === 1, sprintf('Expected single result for index %d, got %d', $numbericKey, count($v)));

            return reset($v);
        }

        // WRITE (UPSERT)
        $stmt = $db->prepare(<<<SQLITE3
            INSERT OR REPLACE INTO kvs (k, v) VALUES (:k, :v)
        SQLITE3);
        $stmt->bindValue(':k', $numbericKey, SQLITE3_INTEGER);
        $stmt->bindValue(':v', $value, SQLITE3_TEXT);
        $stmt->execute();

        return $value;
    } finally {
        // finally block is executed even if return is called before
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Převede text na odpovídající html pomocí markdownu
 * @see Originální implementace markdownu je rychlejší jak Parsedown, ale díky
 *  cacheování je to jedno
 */
function markdown(
    ?string $text,
): ?string
{
    if ($text === null) {
        return null;
    }

    if ($text === '') {
        return '';
    }

    $out = kvs('markdown', $text);
    if ($out === null) {
        $out = markdownNoCache($text);
        kvs('markdown', $text, $out);
    }

    return $out;
}

/** Převede text markdown na html (přímo on the fly) */
function markdownNoCache(
    ?string $text,
): string
{
    if (!$text) {
        return '';
    }
    $text = MarkdownExtra::defaultTransform($text);
    $text = Smartyp::defaultTransform($text);
    assert(is_string($text), sprintf('Expected string, got %s', gettype($text)));

    return $text;
}

/**
 * Zamezení csrf pro POST požadavky podle referreru.
 *
 * OWASP compliance:
 * https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#identifying-source-origin-via-originreferer-header
 */
function omezCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (in_array($_SERVER['REQUEST_URI'], ['/web/wp/xmlrpc.php', '/web/wordpress/xmlrpc.php'])) {
        /** Když vy takhle, tak my takhle web/moduly/wordpress/xmlrpc.php */
        return;
    }

    $referrerHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';

    if (strcasecmp($referrerHost, $_SERVER['SERVER_NAME'] ?? '') !== 0
        && strcasecmp($referrerHost, parse_url(URL_ADMIN, PHP_URL_HOST)) !== 0
    ) {
        require __DIR__ . '/../../web/moduly/nenalezeno.php';
        exit;
    }
}

/**
 * Kompiluje a minifikuje soubory předané v argumentech a vrací url s časovou
 * značkou (jako url proměnnou)
 * V složce soubory/perfectcache nutno (např. htaccessem) povolit cacheování
 * navždy
 * Poslední soubor slouží jako referenční k určení cesty, kam cache uložit
 * @todo nějaký hash počtu / názvu souborů? (když se přidá nový soubor se starým
 *  timestampem, nic se nestane)
 */
function perfectcache(/* variadic */)
{
    $args = perfectcacheExpandujArgumenty(func_get_args());
    $lastf = end($args);
    $typ = substr($lastf, -3) == '.js'
        ? 'js'
        : 'css';
    $last = 0;
    foreach ($args as $a) {
        if (!$a) continue;
        $m = filemtime($a);
        if ($last < $m) $last = $m;
    }
    $mind = CACHE . '/' . $typ;
    $minf = $mind . '/' . md5(implode('', $args)) . '.' . $typ;
    $minu = URL_CACHE . '/' . $typ . '/' . md5(implode('', $args)) . '.' . $typ;
    $m = @filemtime($minf);
    // případná rekompilace
    if ($m < $last) {
        pripravCache($mind);
        if (is_file($minf)) unlink($minf);
        if ($typ == 'js') {
            foreach ($args as $a) {
                if ($a) file_put_contents($minf, file_get_contents($a), FILE_APPEND);
            }
        } else {
            $parser = new Less_Parser(['compress' => true]);
            foreach ($args as $a) {
                if ($a) {
                    if (substr($a, -4) != '.ttf') {
                        $tmpSouborStylu = tempnam(sys_get_temp_dir(), 'perfectcacheCss');
                        $css = file_get_contents($a);
                        $css = pefrectcacheProcessRel($css, 1920, 1200);
                        file_put_contents($tmpSouborStylu, $css);
                        $parser->parseFile($tmpSouborStylu, URL_WEBU . '/soubory/styl/');
                        unlink($tmpSouborStylu);
                    } else {
                        // prozatím u fontu stačí věřit, že modifikace odpovídá modifikaci stylu
                        $parser->ModifyVars([perfectcacheFontNazev($a) => 'url("' . perfectcacheFont($a) . '")']);
                    }
                }
            }
            file_put_contents($minf, $parser->getCss());
        }
    }

    return $minu . '?v=' . $last;
}

function perfectcacheExpandujArgumenty(
    $argumenty,
)
{
    $out = [];
    foreach ($argumenty as $argument) {
        if (str_contains($argument, '*')) {
            $out = array_merge($out, glob($argument));
        } else {
            $out[] = $argument;
        }
    }

    return $out;
}

function perfectcacheFont(
    $font,
)
{
    // font musí pocházet ze stejné url - nelze použít cache
    return URL_WEBU . '/' . $font . '?v=' . filemtime($font);
}

function perfectcacheFontNazev(
    $font,
)
{
    return 'font' . preg_replace('@.*/([^/]+)\.ttf$@', '$1', $font);
}

/**
 * Přeformátuje speciální jednotku `rel` (pixel relative) v css řetězci na
 * kombinaci vw (odpovídající $originalWidth) s relativním zmenšením až na
 * $minWidth, kde se zmenšování zastaví (pomocí media queries).
 */
function pefrectcacheProcessRel(
    $css,
    $originalWidth,
    $minWidth,
)
{
    $toVw = function (
        $line,
    ) use (
        $originalWidth,
    ) {
        return preg_replace_callback(
            '/(\d+)rel/',
            function (
                $m,
            ) use (
                $originalWidth,
            ) {
                return round($m[1] / ($originalWidth / 100), 3) . 'vw';
            },
            $line,
        );
    };

    $toPx = function (
        $line,
    ) use (
        $originalWidth,
        $minWidth,
    ) {
        $new = preg_replace_callback(
            '/(\d+)rel/',
            function (
                $m,
            ) use (
                $minWidth,
                $originalWidth,
            ) {
                return round($m[1] * ($minWidth / $originalWidth), 0) . 'px';
            },
            $line,
        );

        return
            "    @media (max-width: " . $minWidth . "px) {\n" .
            "        " . $new . "\n" .
            "    }";
    };

    return preg_replace_callback(
        '/^.*\drel.*$/m',
        function (
            $m,
        ) use (
            $toVw,
            $toPx,
        ) {
            return $toVw($m[0]) . "\n" . $toPx($m[0]);
        },
        $css,
    );
}

function po(
    string|DateTimeInterface $cas,
): bool
{
    $casTimestamp = ($cas instanceof DateTimeInterface)
        ? $cas->getTimestamp()
        : strtotime($cas);

    return $casTimestamp < time();
}

function pred(
    string|DateTimeInterface $cas,
): bool
{
    $casTimestamp = ($cas instanceof DateTimeInterface)
        ? $cas->getTimestamp()
        : strtotime($cas);

    return time() < $casTimestamp;
}

/**
 * Vrací true, pokud je aktuální čas mezi $od a $do. Formáty jsou stejné jaké
 * akceptují php funce (např. strtotime)
 */
function mezi(
    string|DateTimeInterface $od,
    string|DateTimeInterface $do,
)
{
    $odTimestamp = ($od instanceof DateTimeInterface)
        ? $od->getTimestamp()
        : strtotime($od);
    $doTimestamp = ($do instanceof DateTimeInterface)
        ? $do->getTimestamp()
        : strtotime($do);

    return $odTimestamp <= time() && time() <= $doTimestamp;
}

/**
 * Vytvoří zapisovatelnou složku, pokud taková už neexistuje
 */
function pripravCache(
    string $slozka,
)
{
    if (is_writable($slozka)) {
        return;
    }
    if (is_dir($slozka) && !is_writable($slozka)) {
        throw new Exception("Do existující cache složky '$slozka' není možné zapisovat");
    }
    if (!@mkdir($slozka, 0700, true) && !is_dir($slozka)) {
        throw new Exception("Složku '$slozka' se nepodařilo vytvořit");
    }
    chmod($slozka, CACHE_SLOZKY_PRAVA);
}

/** Znaménkové crc32 chovající se stejně na 32bit i 64bit systémech */
function scrc32(
    string $data,
)
{
    $crc = crc32($data);
    if ($crc & 0x80000000) {
        $crc ^= 0xffffffff;
        $crc += 1;
        $crc = -$crc;
    }

    return $crc;
}

function potrebujePotvrzeni(
    DateTimeImmutable $datumNarozeni,
): bool
{
    // cilene bez hodin, minut a sekund
    return vekNaZacatkuLetosnihoGameconu($datumNarozeni) < 15;
}

function serazenePodle(
    $pole,
    $kriterium,
)
{
    if (is_string($kriterium)) {
        usort($pole, function (
            $a,
            $b,
        ) use (
            $kriterium,
        ) {
            return $a->$kriterium() <=> $b->$kriterium();
        });
    } else {
        $prvek = $pole
            ? $kriterium(current($pole))
            : null;
        if ($prvek && is_string($prvek) && !is_numeric($prvek)) {
            $razeni = new Collator('cs');
            usort($pole, function (
                $a,
                $b,
            ) use (
                $kriterium,
                $razeni,
            ) {
                return $razeni->compare($kriterium($a), $kriterium($b));
            });
        } else {
            usort($pole, function (
                $a,
                $b,
            ) use (
                $kriterium,
            ) {
                return $kriterium($a) <=> $kriterium($b);
            });
        }
    }

    return $pole;
}

function seskupenePodle(
    $pole,
    $funkce,
)
{
    $out = [];

    foreach ($pole as $prvek) {
        $klic = $funkce($prvek);
        $out[$klic][] = $prvek;
    }

    return $out;
}

function vekNaZacatkuLetosnihoGameconu(
    DateTimeImmutable $datumNarozeni,
): int
{
    // cilene bez hodin, minut a sekund
    return vek($datumNarozeni->setTime(0, 0, 0), DateTimeGamecon::zacatekGameconu()->setTime(0, 0, 0));
}

function vek(
    DateTimeInterface  $datumNarozeni,
    ?DateTimeInterface $kDatu,
): int
{
    $kDatu = $kDatu ?? new DateTimeImmutable(date('Y-m-d 00:00:00'));

    return $kDatu->diff($datumNarozeni)->y;
}

function kodZNazvu(
    string $nazev,
): string
{
    return RemoveDiacritics::toSnakeCaseId($nazev);
}

function odstranDiakritiku(
    string $value,
): string
{
    $valueWithoutDiacritics = '';
    $valueWithSpecialsReplaced = \str_replace(
        ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
        ['', '', '', 'E', 'e', "'", "'", "'",],
        $value,
    );
    \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $valueWithSpecialsReplaced, $matches);
    foreach ($matches['words'] as $index => $word) {
        $wordWithoutDiacritics = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
        $valueWithoutDiacritics .= $wordWithoutDiacritics . $matches['nonWords'][$index];
    }

    return $valueWithoutDiacritics;
}

if (!function_exists('array_key_first')) {
    function array_key_first(
        array $values,
    )
    {
        foreach ($values as $key => $unused) {
            return $key;
        }

        return null;
    }
}

/**
 * @param array|string[] $urls
 * @param int $timeout
 * @param string|null $dirToSaveTo
 * @return string[][] Cesty ke staženým souborům a chyby [ ['files'][], ['errors'][] ]
 */
function hromadneStazeni(
    array  $urls,
    int    $timeout = 60,
    string $dirToSaveTo = null,
): array
{
    $urls = array_map('trim', $urls);
    $urls = array_filter($urls, static function (
        string $url,
    ) {
        return $url !== '';
    });
    $result = [
        'errorUrls' => [],
        'errors' => [],
        'files' => [],
        'responseCodes' => [],
    ];
    if (count($urls) === 0) {
        return $result;
    }
    $urls = array_unique($urls);
    $sanitizedUrls = [];
    foreach ($urls as $url) {
        $sanitizedUrls[$url] = sanitizeUrlForCurl($url);
    }
    if ($dirToSaveTo === null) {
        $dirToSaveTo = sys_get_temp_dir() . '/' . uniqid(__FUNCTION__, true);
    }
    if (!mkdir($dirToSaveTo, 0777, true) && !is_dir($dirToSaveTo)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirToSaveTo));
    }
    $multiCurl = curl_multi_init();
    $curlHandles = [];
    $fileHandles = [];

    // Add curl multi handles, one per file we don't already have
    foreach ($sanitizedUrls as $originalUrl => $sanitizedUrl) {
        $path = parse_url($sanitizedUrl, PHP_URL_PATH);
        $basename = basename($path);
        $file = $dirToSaveTo . '/' . uniqid('image', true) . $basename;
        $curlHandle = curl_init($sanitizedUrl);
        if (!$curlHandle) {
            $result['errors'][$originalUrl] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $sanitizedUrl);
            $result['errorUrls'][] = $originalUrl;
            continue;
        }
        $fileHandle = fopen($file, 'wb');
        if (!$fileHandle) {
            $result['errors'][$originalUrl] = sprintf("Nelze otevřít file handle pro soubor '%s'", $file);
            $result['errorUrls'][] = $originalUrl;
            continue;
        }
        curl_setopt($curlHandle, CURLOPT_FILE, $fileHandle);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5); // timeout na připojení
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout); // timeout na stahování
        curl_multi_add_handle($multiCurl, $curlHandle);

        $result['files'][$originalUrl] = $file;
        $curlHandles[$sanitizedUrl] = $curlHandle;
        $fileHandles[$sanitizedUrl] = $fileHandle;
    }

    // stahování souborů
    do {
        $totalResultCode = curl_multi_exec($multiCurl, $running);
        // Wait for activity on any curl-connection
        if ($running > 0 && curl_multi_select($multiCurl) === -1) {
            usleep(100000);
        }
    } while ($running > 0 && $totalResultCode === CURLM_OK);
    if ($totalResultCode !== CURLM_OK) {
        $result['errors'][] = sprintf(
            'Nepodařilo se stahovat soubory z URLs %s s chybou %s (%d)',
            implode('; ', $sanitizedUrls),
            curl_multi_strerror($totalResultCode),
            $totalResultCode,
        );

        return $result;
    }

    do {
        $multiInfo = curl_multi_info_read($multiCurl, $remainingMessages);
        if ($multiInfo) {
            ['result' => $resultCode] = $multiInfo;
            if ($resultCode !== CURLE_OK) {
                $result['errors'][] = sprintf('%s (%d)', curl_strerror($resultCode), $resultCode);
            }
        }
    } while ($multiInfo && $remainingMessages);

    foreach ($curlHandles as $sanitizedUrl => $curlHandle) {
        fclose($fileHandles[$sanitizedUrl]);

        $info = curl_getinfo($curlHandle);
        $result['responseCodes'][$info['url']] = $info['http_code'];
        if ($info['http_code'] >= 400) {
            $result['errors'][$info['url']] = sprintf(
                'Stahování %s skončilo s response code %d%s',
                $sanitizedUrl,
                $info['http_code'],
                $info['http_code'] === 404
                    ? ' (nenalezeno)'
                    : '',
            );
            $originalUrl = array_search($sanitizedUrl, $sanitizedUrls, true);
            $result['errorUrls'][] = $originalUrl;
            unset($result['files'][$originalUrl]);
        }
        curl_multi_remove_handle($multiCurl, $curlHandle);
        curl_close($curlHandle);
    }

    curl_multi_close($multiCurl);

    $result['errorUrls'] = array_unique($result['errorUrls']);

    return $result;
}

function sanitizeUrlForCurl(
    string $url,
): string
{
    $urlParts = parse_url($url);

    $sanitizedUrl = '';

    if (!empty($urlParts['scheme'])) {
        $sanitizedUrl .= $urlParts['scheme'] . '://';
    }

    if (!empty($urlParts['user'])) {
        $sanitizedUrl .= $urlParts['user'];
        if (!empty($urlParts['pass'])) {
            $sanitizedUrl .= ':' . $urlParts['user'];
        }
        $sanitizedUrl .= '@';
    }

    if (!empty($urlParts['host'])) {
        $sanitizedUrl .= $urlParts['host'];
    }

    if (!empty($urlParts['port'])) {
        $sanitizedUrl .= ':' . $urlParts['port'];
    }

    if (($urlParts['path'] ?? null) !== null) {
        $sanitizedUrl .= str_replace(' ', rawurldecode(' '), $urlParts['path']);
    }

    if (($urlParts['query'] ?? null) !== null) {
        $sanitizedUrl .= '?' . str_replace(' ', rawurldecode(' '), $urlParts['query']);
    }

    if (($urlParts['fragment'] ?? null) !== null) {
        $sanitizedUrl .= '#' . str_replace(' ', rawurldecode(' '), $urlParts['fragment']);
    }

    return $sanitizedUrl;
}

function removeDiacritics(
    string $value,
): string
{
    if ($value === '') {
        return '';
    }

    static $cache = [];
    $withoutDiacritics = $cache[$value] ?? '';
    if ($withoutDiacritics === '') {
        $specialsReplaced = \str_replace(
            ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
            ['', '', '', 'E', 'e', "'", "'", "'",],
            $value,
        );
        \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $specialsReplaced, $matches);
        foreach ($matches['words'] as $index => $word) {
            $wordWithoutDiacritics = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
            $withoutDiacritics .= $wordWithoutDiacritics . $matches['nonWords'][$index];
        }
        $cache[$value] = $withoutDiacritics;
    }

    return $withoutDiacritics;
}

function nahradPlaceholderyZaNastaveni(
    ?string $value,
): ?string
{
    if (!$value) {
        return $value;
    }
    if (!preg_match_all('~%(?<nastaveni>[^%]+)%~', $value, $matches)) {
        return $value;
    }
    global $systemoveNastaveni;
    foreach ($matches['nastaveni'] as $puvodniKodNastaveni) {
        try {
            ['hodnota' => $kodNastaveni, 'modifikatory' => $modifikatory] = parsujModifikatory($puvodniKodNastaveni);
            $hodnotaNastaveni = $systemoveNastaveni->dejVerejnouHodnotu($kodNastaveni);
            if ($modifikatory) {
                $hodnotaNastaveni = aplikujModifikatory($hodnotaNastaveni, $modifikatory);
            }
            if ($hodnotaNastaveni instanceof DateTimeInterface) {
                $hodnotaNastaveni = $hodnotaNastaveni->format(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD);
            }
        } catch (NeznamyKlicSystemovehoNastaveni) {
            $hodnotaNastaveni = null;
        }
        if ($hodnotaNastaveni !== null) {
            $value = str_replace("%$puvodniKodNastaveni%", $hodnotaNastaveni, $value);
        }
    }

    return $value;
}

function aplikujModifikatory(
    $hodnota,
    array $modifikatory,
)
{
    foreach ($modifikatory as ['modifikator' => $modifikator, 'parametry' => $parametry]) {
        $hodnota = match ($modifikator) {
            'datum' => $hodnota
                ? DateTimeCz::formatujProSablonu($hodnota, $parametry)
                : '',
            default => $hodnota
        };
    }

    return $hodnota;
}

function parsujModifikatory(
    string $hodnota,
): array
{
    $casti = explode('|', $hodnota);
    $cistaHodnota = $casti[0];
    unset($casti[0]);
    $modifikatory = [];
    foreach ($casti as $cast) {
        $rozdelenaCast = explode(':', $cast);
        $modifikator = $rozdelenaCast[0];
        unset($rozdelenaCast[0]);
        $modifikatory[] = [
            'modifikator' => strtolower(trim($modifikator)),
            'parametry' => $rozdelenaCast,
        ];
    }

    return ['hodnota' => $cistaHodnota, 'modifikatory' => $modifikatory];
}

function omnibox(
    string $term,
    bool   $hledatTakeVMailech = true,
    array  $dataVOdpovedi = [],
    array  $labelSlozenZ = null,
    array  $kromeIdUzivatelu = [],
    bool   $jenPrihlaseniAPritomniNaGc = false,
    int    $minimumZnaku = 3,
    array  $jenSRolemi = null,
): array
{

    $uzivatele = Uzivatel::zHledani(
        $term,
        [
            'mail' => $hledatTakeVMailech,
            'jenPrihlaseniAPritomniNaGc' => $jenPrihlaseniAPritomniNaGc,
            'kromeIdUzivatelu' => $kromeIdUzivatelu,
            'jenSRolemi' => $jenSRolemi,
        ],
        20,
        $minimumZnaku,
    );

    $sestavData = static function (
        Uzivatel $uzivatel,
        array    $dataVOdpovedi,
    ): array {
        $data = [];
        foreach ($dataVOdpovedi as $polozka) {
            switch ($polozka) {
                case 'id' :
                    $data['id'] = $uzivatel->id();
                    break;
                case 'jmenoNick' :
                    $data['jmenoNick'] = $uzivatel->jmenoNick();
                    break;
                case 'jmeno' :
                    $data['jmeno'] = $uzivatel->jmeno();
                    break;
                case 'mail' :
                    $data['mail'] = $uzivatel->mail();
                    break;
                case 'zustatek' :
                    $data['zustatek'] = $uzivatel->finance()->formatovanyStav(false);
                    break;
                case 'telefon' :
                    $data['telefon'] = $uzivatel->telefon();
                    break;
                case 'gcPritomen' :
                    $data['gcPritomen'] = $uzivatel->gcPritomen();
                    break;
                default :
                    trigger_error("Nepodporovana polozka pro Omnibox: '$polozka'", E_USER_WARNING);
            }
        }

        return $data;
    };

    $sestavLabel = static function (
        Uzivatel $uzivatel,
        ?array   $labelSlozenZ,
    ) use (
        $sestavData,
    ): string {
        $labelSlozenZ = $labelSlozenZ
            ?: ['id', 'jmenoNick', 'mail'];
        $data = $sestavData($uzivatel, $labelSlozenZ);
        $labelCasti = [];
        if (isset($data['gcPritomen'])) {
            if ($labelCasti) {
                $labelCasti[] = '; ';
            }
            $labelCasti[] = $data['gcPritomen']
                ? '✅'
                : '❌';
        }
        if (!empty($data['id'])) {
            if ($labelCasti) {
                $labelCasti[] = ' - ';
            }
            $labelCasti[] = $data['id'];
        }
        if (!empty($data['jmenoNick'])) {
            if ($labelCasti) {
                $labelCasti[] = ' - ';
            }
            $labelCasti[] = $data['jmenoNick'];
        }
        if (!empty($data['jmeno'])) {
            if ($labelCasti) {
                $labelCasti[] = ' - ';
            }
            $labelCasti[] = $data['jmeno'];
        }
        if (!empty($data['mail'])) {
            if ($labelCasti) {
                $labelCasti[] = ' ';
            }
            $labelCasti[] = "({$data['mail']})";
        }
        if (!empty($data['zustatek'])) {
            if ($labelCasti) {
                $labelCasti[] = '; ';
            }
            $labelCasti[] = "{$data['zustatek']}";
        }

        return implode($labelCasti);
    };

    return array_map(
        static function (
            Uzivatel $uzivatel,
        ) use (
            $sestavLabel,
            $sestavData,
            $dataVOdpovedi,
            $labelSlozenZ,
        ) {
            return [
                'label' => $sestavLabel($uzivatel, $labelSlozenZ),
                'data' => $sestavData($uzivatel, $dataVOdpovedi),
                'value' => $uzivatel->id(),
            ];
        },
        $uzivatele,
    );
}

function pridejNaZacatekPole(
    string $klic,
           $hodnota,
    array  $pole,
): array
{
    unset($pole[$klic]); // pro případ, že by byl klíč obsazen - potom by původní honota přepsala novou níže a to nechceme

    return array_merge([$klic => $hodnota], $pole);
}

function prevedNaFloat(
    $castka,
): float
{
    if (is_int($castka) || is_float($castka)) {
        return (float)$castka;
    }
    $original = $castka;
    $castka = preg_replace('~[^-\d,.]~', '', $castka);
    $castka = str_replace(',', '.', $castka);
    if (!preg_match('~^-?\d+[.]?(\d+)?$~', $castka)) { // 1. je OK, stane se z toho 1.0
        throw new \InvalidArgumentException("Chybné číslo '$original'");
    }

    return (float)$castka;
}

/**
 * @param string $text utf-8 řetězec
 * @return string enkódovaný řetězec pro použití v emailové hlavičce
 */
function encodeToUtf8(
    string $text,
)
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function requireOnceIsolated(
    string $path,
)
{
    // aby proměnné ze skriptu nepřepsaly jiné, něco jako local scope
    require_once $path;
}

function intvalOrNull(
    $val,
)
{
    return $val == null
        ? null
        : intval($val);
}

function jsmeNaLocale(): bool
{
    return ($_ENV['ENV'] ?? '') === 'local'
        || in_array(
            getDefinedHost() ?? '',
            ['127.0.0.1', '::1', 'localhost'],
            true,
        );
}

function getDefinedHost(): ?string
{
    return defined('SERVER_NAME')
        ? constant('SERVER_NAME')
        : ($_SERVER['SERVER_NAME']
            ?? (defined('URL_WEBU')
                ? parse_url(constant('URL_WEBU'), PHP_URL_HOST)
                : null
            )
        );
}

function jsmeNaBete(): bool
{
    $definedHost = getDefinedHost();

    return $definedHost !== null
        && in_array(
            $definedHost,
            ['beta.gamecon.cz', 'admin.beta.gamecon.cz', 'cache.beta.gamecon.cz'],
            true,
        );
}

function jsmeNaOstre(): bool
{
    $definedHost = getDefinedHost();

    return $definedHost !== null
        && (
            in_array(
                $definedHost,
                ['gamecon.cz', 'admin.gamecon.cz', 'cache.gamecon.cz'],
                true,
            )
            || preg_match('~(?<rocnik>[0-9]{4})[.]gamecon[.]cz$~', $definedHost)
        );
}

function quickReportPlaceholderReplace(
    string $sql,
): string
{
    return str_ireplace(['{ROK}', '{ROCNIK}'], ROCNIK, $sql);
}
