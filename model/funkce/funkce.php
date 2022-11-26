<?php

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

$GLOBALS['SKRIPT_ZACATEK'] = microtime(true); // profiling

/**
 * Vrátí míru diverzifikace aktivit v poli udávajícím počty aktivit od jedno-
 * tlivých typů. Délka pole ovlivňuje výsledek (je potřeba aby obsahovalo i 0)
 */
function aktivityDiverzifikace($poleTypu) {
    $typu  = count($poleTypu);
    $pocet = array_sum($poleTypu);
    if ($pocet == 0) return 0.0;
    $pocty = $poleTypu;
    rsort($pocty, SORT_NUMERIC);
    $max    = ($pocet - $pocty[0]) / ($pocet * ($typu - 1));
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
function cislo($i, $jeden, $dva, $pet) {
    if ($i == 1) return $i . $jeden;
    if (1 < $i && $i < 5) return $i . $dva;
    else return $i . $pet;
}

/** Vrací datum ve stylu "pátek 14:00-18:00" na základě řádku db */
function datum2($dbRadek) {
    if ($dbRadek['zacatek'])
        return (new DateTimeCz($dbRadek['zacatek']))->format('l G:i') . '–' . (new DateTimeCz($dbRadek['konec']))->format('G:i');
    else
        return '';
}

/** Vrací datum ve stylu 1. července
 *  akceptuje vše, co žere strtotime */
function datum3($datum) {
    $mesic = ['ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
    return date('j. ', strtotime($datum)) .
        $mesic[date('n', strtotime($datum)) - 1];
}

/** Vrátí markdown textu daného hashe (cacheované, text musí být v DB) */
function dbMarkdown($hash) {
    if ($hash == 0) return '';
    $out = kvs('markdown', $hash);
    if (!$out) {
        $text = dbOneCol('SELECT text FROM texty WHERE id = ' . (int)$hash);
        if (!$text) throw new Exception('Text s daným ID se nenachází v databázi');
        $out = markdown($text);
    }
    return $out;
}

/**
 * Vrátí / nastaví text daného hashe v DB.
 * Možné použití (místo 0 funguje všude false ekvivalent):
 *  dbText(123)         - vrátí text s ID 123
 *  dbText(0)           - vrátí 0
 *  dbText(0, 'ahoj')   - vloží text a vrátí jeho ID
 *  dbText(123, 'ahoj') - odstraní text 123 a vloží místo něj nový, vrátí nové ID
 *  dbText(123, '')     - odstraní text 123 a vrátí 0
 *  dbText(0, '')       - vrátí 0
 *  TODO vše implementovat a otestovat
 *  TODO co s duplicitami
 */
function dbText($hash) {
    if (func_num_args() == 1) {
        return dbOneCol('SELECT text FROM texty WHERE id = ' . (int)$hash);
    } elseif (func_num_args() == 2 and !func_get_arg(1)) {
        dbQuery('DELETE FROM texty WHERE id = ' . (int)$hash);
        return 0;
    } else {
        $text  = func_get_arg(1);
        $nhash = scrc32($text);
        $nrow  = ['text' => $text, 'id' => $nhash];
        if ($hash) dbUpdate('texty', $nrow, ['id' => $hash]);
        else dbInsert('texty', $nrow);
        return $nhash;
    }
}

/**
 * Uloží daný text do databáze a vrátí id (hash) kterým se na něj odkázat
 */
function dbTextHash($text): int {
    $text = (string)$text;
    $hash = scrc32($text);
    dbInsertIgnore('texty', ['id' => $hash, 'text' => $text]);
    return $hash;
}

/**
 * Vymaže text s daným hashem z DB pokud je to možné
 */
function dbTextClean($hash) {
    try {
        dbQuery('DELETE FROM texty WHERE id = ' . (int)$hash);
    } catch (DbException $e) {
        // Cannot delete or update a parent row: a foreign key constraint fails
        // mažeme pouze texty, které nejsou nikde použité
    }
}

/** Načte / uloží hodnotu do key-value storage s daným názvem */
function kvs($nazev, $index, $hodnota = null) {
    if (!isset($GLOBALS['CACHEDB'][$nazev])) {
        $db                         = new SQLite3(SPEC . '/' . $nazev . '.sqlite');
        $GLOBALS['CACHEDB'][$nazev] = $db;
        $db->exec("create table if not exists kvs (k integer primary key, v text)");
    }
    $db = $GLOBALS['CACHEDB'][$nazev];
    if ($hodnota === null) {
        // načítání
        $o = $db->query('select v from kvs where k = ' . $index)->fetchArray(SQLITE3_NUM);
        if ($o === false) return null;
        else return $o[0];
    } else {
        $db->exec('insert into kvs values(' . $index . ',\'' . SQLite3::escapeString($hodnota) . '\')');
    }
}

/**
 * Převede text na odpovídající html pomocí markdownu
 * @see Originální implementace markdownu je rychlejší jak Parsedown, ale díky
 *  cacheování je to jedno
 */
function markdown($text) {
    $hash = scrc32($text);
    $out  = kvs('markdown', $hash);
    if ($out === null) {
        kvs('markdown', $hash, markdownNoCache($text));
        $out = kvs('markdown', $hash);
    }
    return $out;
}

/** Převede text markdown na html (přímo on the fly) */
function markdownNoCache($text): string {
    if (!$text) {
        return '';
    }
    $text = \Michelf\MarkdownExtra::defaultTransform($text);
    $text = Smartyp::defaultTransform($text);
    return $text;
}

/** Multibyte (utf-8) první písmeno velké */
function mb_ucfirst($string, $encoding = null) {
    if (!$encoding) {
        $encoding = mb_internal_encoding();
    }
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then      = mb_substr($string, 1, mb_strlen($string), $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}

/**
 * Vrací true, pokud je aktuální čas mezi $od a $do. Formáty jsou stejné jaké
 * akceptují php funce (např. strtotime)
 */
function mezi($od, $do) {
    return strtotime($od) <= time() && time() <= strtotime($do);
}

/**
 * Zamezení csrf pro POST požadavky podle referreru.
 *
 * OWASP compliance: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#identifying-source-origin-via-originreferer-header
 */
function omezCsrf() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (in_array($_SERVER['REQUEST_URI'], ['/web/wp/xmlrpc.php', '/web/wordpress/xmlrpc.php'])) {
        /** Když vy takhle, tak my takhle web/moduly/wordpress/xmlrpc.php */
        return;
    }

    $referrerHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);

    if ($referrerHost !== $_SERVER['SERVER_NAME'] && $referrerHost !== parse_url(URL_ADMIN, PHP_URL_HOST)) {
        // výjimka, aby došlo k zalogování
        throw new Exception(
            "Referrer POST '$referrerHost' požadavku neodpovídá doméně '{$_SERVER['SERVER_NAME']}' ani '" . parse_url(URL_ADMIN, PHP_URL_HOST) . "'"
        );
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
function perfectcache(/* variadic */) {
    $args  = perfectcacheExpandujArgumenty(func_get_args());
    $lastf = end($args);
    $typ   = substr($lastf, -3) == '.js' ? 'js' : 'css';
    $last  = 0;
    foreach ($args as $a) {
        if (!$a) continue;
        $m = filemtime($a);
        if ($last < $m) $last = $m;
    }
    $mind = CACHE . '/' . $typ;
    $minf = $mind . '/' . md5(implode('', $args)) . '.' . $typ;
    $minu = URL_CACHE . '/' . $typ . '/' . md5(implode('', $args)) . '.' . $typ;
    $m    = @filemtime($minf);
    // případná rekompilace
    if ($m < $last) {
        pripravCache($mind);
        if (is_file($minf)) unlink($minf);
        if ($typ == 'js') {
            foreach ($args as $a) if ($a) file_put_contents($minf, file_get_contents($a), FILE_APPEND);
        } else {
            $parser = new Less_Parser(['compress' => true]);
            foreach ($args as $a) if ($a) {
                if (substr($a, -4) != '.ttf') {
                    $tmpSouborStylu = tempnam(sys_get_temp_dir(), 'perfectcacheCss');
                    $css            = file_get_contents($a);
                    $css            = pefrectcacheProcessRel($css, 1920, 1200);
                    file_put_contents($tmpSouborStylu, $css);
                    $parser->parseFile($tmpSouborStylu, URL_WEBU . '/soubory/styl/');
                    unlink($tmpSouborStylu);
                } else {
                    // prozatím u fontu stačí věřit, že modifikace odpovídá modifikaci stylu
                    $parser->ModifyVars([perfectcacheFontNazev($a) => 'url("' . perfectcacheFont($a) . '")']);
                }
            }
            file_put_contents($minf, $parser->getCss());
        }
    }
    return $minu . '?v=' . $last;
}

function perfectcacheExpandujArgumenty($argumenty) {
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

function perfectcacheFont($font) {
    // font musí pocházet ze stejné url - nelze použít cache
    return URL_WEBU . '/' . $font . '?v=' . filemtime($font);
}

function perfectcacheFontNazev($font) {
    return 'font' . preg_replace('@.*/([^/]+)\.ttf$@', '$1', $font);
}

/**
 * Přeformátuje speciální jednotku `rel` (pixel relative) v css řetězci na
 * kombinaci vw (odpovídající $originalWidth) s relativním zmenšením až na
 * $minWidth, kde se zmenšování zastaví (pomocí media queries).
 */
function pefrectcacheProcessRel($css, $originalWidth, $minWidth) {
    $toVw = function ($line) use ($originalWidth) {
        return preg_replace_callback(
            '/(\d+)rel/',
            function ($m) use ($originalWidth) {
                return round($m[1] / ($originalWidth / 100), 3) . 'vw';
            },
            $line
        );
    };

    $toPx = function ($line) use ($originalWidth, $minWidth) {
        $new = preg_replace_callback(
            '/(\d+)rel/',
            function ($m) use ($minWidth, $originalWidth) {
                return round($m[1] * ($minWidth / $originalWidth), 0) . 'px';
            },
            $line
        );

        return
            "    @media (max-width: " . $minWidth . "px) {\n" .
            "        " . $new . "\n" .
            "    }";
    };

    return preg_replace_callback(
        '/^.*\drel.*$/m',
        function ($m) use ($toVw, $toPx) {
            return $toVw($m[0]) . "\n" . $toPx($m[0]);
        },
        $css,
    );
}

function po($cas) {
    return strtotime($cas) < time();
}

function pred($cas) {
    return time() < strtotime($cas);
}

/**
 * Vytvoří zapisovatelnou složku, pokud taková už neexistuje
 */
function pripravCache($slozka) {
    if (is_writable($slozka)) {
        return;
    }
    if (is_dir($slozka)) {
        throw new Exception("Do existující cache složky '$slozka' není možné zapisovat");
    }
    if (!mkdir($slozka, 0777, true)) {
        throw new Exception("Složku '$slozka' se nepodařilo vytvořit");
    }
    chmod($slozka, CACHE_SLOZKY_PRAVA);
}

/** Znaménkové crc32 chovající se stejně na 32bit i 64bit systémech */
function scrc32($data) {
    $crc = crc32($data);
    if ($crc & 0x80000000) {
        $crc ^= 0xffffffff;
        $crc += 1;
        $crc = -$crc;
    }
    return $crc;
}

function potrebujePotvrzeni(DateTimeImmutable $datumNarozeni): bool {
    // cilene bez hodin, minut a sekund
    return vekNaZacatkuLetosnihoGameconu($datumNarozeni) < 15;
}

function serazenePodle($pole, $kriterium) {
    if (is_string($kriterium)) {
        usort($pole, function ($a, $b) use ($kriterium) {
            return $a->$kriterium() <=> $b->$kriterium();
        });
    } else {
        $prvek = $pole ? $kriterium(current($pole)) : null;
        if ($prvek && is_string($prvek) && !is_numeric($prvek)) {
            $razeni = new Collator('cs');
            usort($pole, function ($a, $b) use ($kriterium, $razeni) {
                return $razeni->compare($kriterium($a), $kriterium($b));
            });
        } else {
            usort($pole, function ($a, $b) use ($kriterium) {
                return $kriterium($a) <=> $kriterium($b);
            });
        }
    }
    return $pole;
}

function seskupenePodle($pole, $funkce) {
    $out = [];

    foreach ($pole as $prvek) {
        $klic         = $funkce($prvek);
        $out[$klic][] = $prvek;
    }

    return $out;
}

function vekNaZacatkuLetosnihoGameconu(DateTimeImmutable $datumNarozeni): int {
    // cilene bez hodin, minut a sekund
    return vek($datumNarozeni->setTime(0, 0, 0), DateTimeGamecon::zacatekGameconu()->setTime(0, 0, 0));
}

function vek(DateTimeInterface $datumNarozeni, ?DateTimeInterface $kDatu): int {
    $kDatu = $kDatu ?? new DateTimeImmutable(date('Y-m-d 00:00:00'));
    return $kDatu->diff($datumNarozeni)->y;
}

function odstranDiakritiku(string $value): string {
    $valueWithoutDiacritics    = '';
    $valueWithSpecialsReplaced = \str_replace(
        ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
        ['', '', '', 'E', 'e', "'", "'", "'",],
        $value
    );
    \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $valueWithSpecialsReplaced, $matches);
    foreach ($matches['words'] as $index => $word) {
        $wordWithoutDiacritics  = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
        $valueWithoutDiacritics .= $wordWithoutDiacritics . $matches['nonWords'][$index];
    }
    return $valueWithoutDiacritics;
}

if (!function_exists('array_key_first')) {
    function array_key_first(array $values) {
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
function hromadneStazeni(array $urls, int $timeout = 60, string $dirToSaveTo = null): array {
    $urls   = array_map('trim', $urls);
    $urls   = array_filter($urls, static function (string $url) {
        return $url !== '';
    });
    $result = [
        'errorUrls'     => [],
        'errors'        => [],
        'files'         => [],
        'responseCodes' => [],
    ];
    if (count($urls) === 0) {
        return $result;
    }
    $urls          = array_unique($urls);
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
    $multiCurl   = curl_multi_init();
    $curlHandles = [];
    $fileHandles = [];

    // Add curl multi handles, one per file we don't already have
    foreach ($sanitizedUrls as $originalUrl => $sanitizedUrl) {
        $path       = parse_url($sanitizedUrl, PHP_URL_PATH);
        $basename   = basename($path);
        $file       = $dirToSaveTo . '/' . uniqid('image', true) . $basename;
        $curlHandle = curl_init($sanitizedUrl);
        if (!$curlHandle) {
            $result['errors'][$originalUrl] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $sanitizedUrl);
            $result['errorUrls'][]          = $originalUrl;
            continue;
        }
        $fileHandle = fopen($file, 'wb');
        if (!$fileHandle) {
            $result['errors'][$originalUrl] = sprintf("Nelze otevřít file handle pro soubor '%s'", $file);
            $result['errorUrls'][]          = $originalUrl;
            continue;
        }
        curl_setopt($curlHandle, CURLOPT_FILE, $fileHandle);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5); // timeout na připojení
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout); // timeout na stahování
        curl_multi_add_handle($multiCurl, $curlHandle);

        $result['files'][$originalUrl] = $file;
        $curlHandles[$sanitizedUrl]    = $curlHandle;
        $fileHandles[$sanitizedUrl]    = $fileHandle;
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
            $totalResultCode
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

        $info                                  = curl_getinfo($curlHandle);
        $result['responseCodes'][$info['url']] = $info['http_code'];
        if ($info['http_code'] >= 400) {
            $result['errors'][$info['url']] = sprintf(
                'Stahování %s skončilo s response code %d%s',
                $sanitizedUrl,
                $info['http_code'],
                $info['http_code'] === 404
                    ? ' (nenalezeno)'
                    : ''
            );
            $originalUrl                    = array_search($sanitizedUrl, $sanitizedUrls, true);
            $result['errorUrls'][]          = $originalUrl;
            unset($result['files'][$originalUrl]);
        }
        curl_multi_remove_handle($multiCurl, $curlHandle);
        curl_close($curlHandle);
    }

    curl_multi_close($multiCurl);

    $result['errorUrls'] = array_unique($result['errorUrls']);

    return $result;
}

function sanitizeUrlForCurl(string $url): string {
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

function removeDiacritics(string $value) {
    $withoutDiacritics = '';
    $specialsReplaced  = \str_replace(
        ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
        ['', '', '', 'E', 'e', "'", "'", "'",],
        $value
    );
    \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $specialsReplaced, $matches);
    foreach ($matches['words'] as $index => $word) {
        $wordWithoutDiacritics = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
        $withoutDiacritics     .= $wordWithoutDiacritics . $matches['nonWords'][$index];
    }
    return $withoutDiacritics;
}

function nahradPlaceholderZaKonstantu(?string $value): ?string {
    if (!$value) {
        return $value;
    }
    if (!preg_match_all('~%(?<constant>[^%]+)%~', $value, $matches)) {
        return $value;
    }
    foreach ($matches['constant'] as $potentialConstant) {
        if (defined($potentialConstant)) {
            $value = str_replace("%$potentialConstant%", constant($potentialConstant), $value);
        }
    }
    return $value;
}

function omnibox(
    string $term,
    bool   $hledatTakeVMailech = true,
    array  $dataVOdpovedi = [],
    array  $labelSlozenZ = null,
    array  $kromeIdUzivatelu = [],
    bool   $jenPrihlaseniAPritomniNaGc = false,
    int    $minimumZnaku = 3,
    array  $jenSeZidlemi = null
): array {

    $uzivatele = Uzivatel::zHledani(
        $term,
        [
            'mail'                       => $hledatTakeVMailech,
            'jenPrihlaseniAPritomniNaGc' => $jenPrihlaseniAPritomniNaGc,
            'kromeIdUzivatelu'           => $kromeIdUzivatelu,
            'jenSeZidlemi'               => $jenSeZidlemi,
        ],
        20,
        $minimumZnaku
    );

    $sestavData = static function (Uzivatel $uzivatel, array $dataVOdpovedi): array {
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
                    $data['zustatek'] = $uzivatel->finance()->stavHr(false);
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

    $sestavLabel = static function (Uzivatel $uzivatel, ?array $labelSlozenZ) use ($sestavData): string {
        $labelSlozenZ = $labelSlozenZ ?: ['id', 'jmenoNick', 'mail'];
        $data         = $sestavData($uzivatel, $labelSlozenZ);
        $labelCasti   = [];
        if (isset($data['gcPritomen'])) {
            if ($labelCasti) {
                $labelCasti[] = '; ';
            }
            $labelCasti[] = $data['gcPritomen'] ? '✅' : '❌';
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
        static function (Uzivatel $uzivatel) use ($sestavLabel, $sestavData, $dataVOdpovedi, $labelSlozenZ) {
            return [
                'label' => $sestavLabel($uzivatel, $labelSlozenZ),
                'data'  => $sestavData($uzivatel, $dataVOdpovedi),
                'value' => $uzivatel->id(),
            ];
        },
        $uzivatele
    );
}

function pridejNaZacatekPole(string $klic, $hodnota, array $pole): array {
    unset($pole[$klic]); // pro případ, že by byl klíč obsazen - potom by původní honota přepsala novou níže a to nechceme
    return array_merge([$klic => $hodnota], $pole);
}
