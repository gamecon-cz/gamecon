<?php

/** Searches an array for all specified keys
 * @return bool true if all exist false otherwise
 */
function array_keys_exist($keys, $search) {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $search)) {
            return false;
        }
    }
    return true;
}

/** Flattens array in manner $pre.$element.$post for all elements, separated by $sep */
function array_flat($pre, $array, $post = '', $sep = '') {
    $out = '';
    foreach ($array as $e) {
        $out .= $pre . $e . $post;
    }
    return $out;
}

/**
 * Iterates trough array and prints combined output returned by function in
 * each iteration
 */
function array_uprint($array, callable $func, $sep = '') {
    $out = '';
    foreach ($array as $e) {
        $out .= $func($e) . $sep;
    }
    if ($sep) {
        $out = substr($out, 0, -strlen($sep));
    }
    return $out;
}

function reload() {
    header('Refresh: 0', true, 303);
    exit;
}

function parseRoute(): array {
    $rawReq = get('req');
    if (!$rawReq) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($requestUri, '/admin')) {
            $rawReq = substr($requestUri, strlen('/admin'));
        } elseif (str_starts_with($requestUri, '/web')) {
            $rawReq = substr($requestUri, strlen('/web'));
        } else {
            $rawReq = $requestUri;
        }
    }
    $rawReq = ltrim($rawReq, '/');
    $req    = explode('/', $rawReq ?? '');
    $req[1] = $req[1] ?? '';
    return $req;
}

/**
 * Ends current script execution and reloads page to http referrer.
 * @param string $to alternative location to go to instead of referrer
 */
function back(string $to = null) {
    if ($to) {
        header('Location: ' . $to, true, 303);
    } elseif (isset($_SERVER['HTTP_REFERER'])
        && (str_contains($_SERVER['HTTP_REFERER'], URL_WEBU) || str_contains($_SERVER['HTTP_REFERER'], URL_ADMIN))
    ) {
        header('Location: ' . $_SERVER['HTTP_REFERER'], true, 303);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SERVER['REDIRECT_URL'])
        && $_SERVER['REDIRECT_URL'] !== ($_SERVER['REQUEST_URI'] ?? '')
    ) {
        header('Location: ' . $_SERVER['REDIRECT_URL'], true, 303);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    } else {
        header('Location: ' . URL_WEBU, true, 303);
    }

    exit;
}

function getBackUrl(string $defaultBackUrl = null): string {
    $refererParts = parse_url($_SERVER['HTTP_REFERER'] ?? $defaultBackUrl ?? $_SERVER['REQUEST_URI']);
    return rtrim(implode('?', [$refererParts['path'], $refererParts['query'] ?? '']), '?');
}

function getCurrentUrlPath(): string {
    return (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

function getCurrentUrlWithQuery(array $queryPartsToAddOrReplace = []): string {
    $path        = getCurrentUrlPath();
    $queryString = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    parse_str($queryString, $query);
    $newQuery = array_merge($query, $queryPartsToAddOrReplace);
    if ($newQuery === []) {
        return $path;
    }
    $newQueryString = http_build_query($newQuery);
    return $newQueryString !== '' // když je nějaká hodnota NULL, tak se z query smaže
        ? $path . '?' . $newQueryString
        : $path;
}

function get($name) {
    return $_GET[$name] ?? null;
}

/**
 * Options parsing, returns assoc. array with options.
 * if option in $default has key and value, then it's default,
 * if in $default is just value, it is assumed mandatory argument
 * and exception is thrown, if not set in $actual
 */
function opt($actual, $default) {
    $opt = [];
    foreach ($default as $key => $val) {
        if (is_numeric($key)) {
            if (!array_key_exists($val, $actual)) {
                throw new BadFunctionCallException('key "' . $val . '" in options missing');
            }
            $opt[$val] = $actual[$val];
        } else {
            if (array_key_exists($key, $actual)) {
                $opt[$key] = $actual[$key];
            } else {
                $opt[$key] = $val;
            }
        }
    }
    return $opt;
}

function post($name, $field = null) {
    if ($field === null) {
        return $_POST[$name] ?? null;
    }
    return $_POST[$name][$field] ?? null;
}

function postBody() {
    $rawdata = file_get_contents("php://input");
    $decoded = json_decode($rawdata, true);
    return $decoded;
}

/** Returns temporary filename for uploaded file or '' if none */
function postFile($name) {
    return $_FILES[$name]['tmp_name'] ?? '';
}

/**
 * Converts '"~"' to '"([^"])+"'
 */
function preg_quote_wildcard($re) {
    $re = preg_quote($re);
    $re = preg_replace('@~(\\\\?.)@', '([^$1]*)$1', $re);
    return $re;
}

/**
 * Returns random hexadecimal number in with $chars number of characters (ie.
 * half $chars bytes)
 */
function randHex($chars) {
    if (!($chars <= 32 && $chars >= 0)) {
        throw new Exception('maximum characters is 32 so far.');
    }
    return substr(md5(mt_rand()), 0, $chars);
}

/**
 * Convert localized string to [a-z0-9\-] suitable for files and urls.
 */
function slugify($text) {
    $text = removeDiacritics($text);
    $text = strtolower($text);
    $text = preg_replace('/[^0-9a-z]+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}

/**
 * Convert snake_case to camelCase
 */
function snakeToCamel($str) {
    $str = ucwords($str, '-');
    $str = str_replace('-', '', $str);
    $str = lcfirst($str);
    return $str;
}

/**
 * Returns part of string after rightmost occurence of delimiter
 */
function strrafter($string, $delimiter) {
    $pos = strrpos($string, $delimiter);
    return substr($string, $pos + 1);
}

/**
 * Returns part of string after rightmost occurence of delimiter
 */
function strrbefore($string, $delimiter) {
    $pos = strrpos($string, $delimiter);
    return substr($string, 0, $pos);
}

/**
 * Switches rows and columns
 */
function tabArrayR(array $ai): array {
    $ao = [];
    $ih = count($ai);
    $iw = count($ai[0]);
    for ($ic = 0; $ic < $iw; $ic++) {
        for ($ir = 0; $ir < $ih; $ir++) {
            $ao[$ic][$ir] = $ai[$ir][$ic];
        }
    }
    return $ao;
}

/**
 * Returns HTML formatted table from array
 */
function tabHtml(array $tab, string $title = ''): string {
    $tabOut = "<table>\n";
    if ($title !== '') {
        $tabOut .= "<caption>$title</caption>";
    }
    $tabOut .= "  <tr>\n    <th>" . implode("</th>\n    <th>", $tab[0] ?? []) . "</th>\n  </tr>\n";
    for ($i = 1, $tabsCount = count($tab); $i < $tabsCount; $i++) {
        $tabOut .= "  <tr>\n    <td>" . implode("</td>\n    <td>", $tab[$i]) . "</td>\n  </tr>\n";
    }
    $tabOut .= "</table>\n\n";
    return $tabOut;
}

/**
 * @param mysqli_result $a
 * @param string $title
 * @return string
 * Returns HTML formatted table from db answer
 */
function tabMysql($a, string $title = ''): string {
    $tabOut = "<table>\n";
    if ($title !== '') {
        $tabOut .= "<caption>$title</caption>";
    }
    if (!$r = mysqli_fetch_assoc($a)) {
        return '';
    }
    $tabOut .= "  <tr>\n    <th>" . implode("</th>\n    <th>", array_keys($r)) . "</th>\n  </tr>\n";
    $tabOut .= "  <tr>\n    <td>" . implode("</td>\n    <td>", $r) . "</td>\n  </tr>\n";
    while ($r = mysqli_fetch_row($a)) {
        $tabOut .= "  <tr>\n    <td>" . implode("</td>\n    <td>", $r) . "</td>\n  </tr>\n";
    }
    $tabOut .= "</table>\n\n";
    return $tabOut;
}

/**
 * @param mysqli_result $result
 * Returns table array from mysql answer
 */
function tabMysqlArray(mysqli_result $result): array {
    $header = mysqli_fetch_assoc($result) ?? [];
    $oa[]   = array_keys($header);
    $oa[]   = array_values($header);
    while ($values = mysqli_fetch_row($result)) {
        $oa[] = $values;
    }
    return $oa;
}

/**
 * Returns HTML formatted table from db answer, mirrored
 */
function tabMysqlR(mysqli_result $result, string $title = ''): string {
    return tabHtml(tabArrayR(tabMysqlArray($result)), $title);
}

/**
 * @param Iterator|array $multiDimensionalArray
 * @return array
 */
function flatten(Iterator|array $multiDimensionalArray): array {
    $flattened             = [];
    $multiDimensionalArray = (array)$multiDimensionalArray;
    array_walk_recursive($multiDimensionalArray, function ($array) use (&$flattened) {
        $flattened[] = $array;
    });
    return $flattened;
}

function nahradNazvyKonstantZaHodnoty(string $text): string {
    if (preg_match_all('~%(?<konstanta>[A-Z_]+)%~', $text, $matches)) {
        foreach ($matches['konstanta'] as $nazevKonstanty) {
            if (defined($nazevKonstanty)) {
                $text = str_replace("%$nazevKonstanty%", constant($nazevKonstanty), $text);
            }
        }
    }
    return $text;
}
