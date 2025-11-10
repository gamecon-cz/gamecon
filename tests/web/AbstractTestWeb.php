<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;
use Uzivatel;
use Gamecon\Login\Login;
use Gamecon\Pravo;

abstract class AbstractTestWeb extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $queries = [];

        $queries[] = [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, vyznam_role, typ_role, rocnik_role)
VALUES (1, 'VSEMOCNA', 'všemocná', 'všemocná', $0, $1, @rocnik)
SQL,
            [Role::VYZNAM_ADMIN, Role::TYP_TRVALA],
        ];

        $idsPrav       = Pravo::dejIdsVsechPrav();
        $pravaSqlArray = [];
        foreach ($idsPrav as $nazevKonstanty => $idPrava) {
            $pravaSqlArray[] = "($idPrava, '$nazevKonstanty', '$nazevKonstanty')";
        }
        $pravaSql  = implode(',', $pravaSqlArray);
        $queries[] = <<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
$pravaSql
SQL;

        $queries[] = <<<SQL
INSERT INTO prava_role(id_role, id_prava)
SELECT 1, id_prava
FROM r_prava_soupis
SQL;

        $idUzivateleSystem = Uzivatel::SYSTEM;
        $queries[]         = <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role)
VALUES ($idUzivateleSystem, 1)
SQL;

        return $queries;
    }

    protected function testAdminPagesAccessibility(array $urls): void
    {
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);

        $this->testPagesAccessibility($urls, Uzivatel::SYSTEM_LOGIN, UNIVERZALNI_HESLO);
    }

    /**
     * @param string[] $urls
     */
    protected function testPagesAccessibility(
        array  $urls,
        string $username = null,
        string $password = null,
    ): void {
        $multiCurl   = curl_multi_init();
        $curlHandles = [];
        $errors      = [];

        foreach ($urls as $url) {
            $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
            if ($username !== null && $password !== null) {
                $this->loginToAdmin($cookieFile, $username, $password);
            }
            $absoluteUrl = 'http://localhost/' . $url;
            $curlHandle  = curl_init($absoluteUrl);
            if (!$curlHandle) {
                $errors[] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $absoluteUrl);
                continue;
            }

            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30); // timeout na připojení
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 20);        // timeout na stahování
            curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, true);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            $this->setLocalServerCookieWithGameconTestDb($curlHandle);
            curl_setopt($curlHandle, CURLOPT_COOKIEFILE, $cookieFile);

            curl_multi_add_handle($multiCurl, $curlHandle);

            $curlHandles[$absoluteUrl] = $curlHandle;
        }

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Potíže s CURL: %s", implode(',', $errors)),
        );

        do {
            $totalResultCode = curl_multi_exec($multiCurl, $running);
        } while ($totalResultCode === CURLM_CALL_MULTI_PERFORM);

        $outputs = [];
        while ($running > 0 && $totalResultCode === CURLM_OK) {
            ob_start();
            $totalResultCode = curl_multi_exec($multiCurl, $running);
            $outputs[]       = ob_get_clean();
            // Wait for activity on any curl-connection
            if ($running > 0 && curl_multi_select($multiCurl) === -1) {
                usleep(100000);
            }
        }
        if ($totalResultCode !== CURLM_OK) {
            $errors[] = sprintf(
                "Nepodařilo se stáhnout stránky z URLs %s s chybou %s (%d) a výstupy '%s'",
                implode('; ', $urls),
                curl_multi_strerror($totalResultCode),
                $totalResultCode,
                implode("\n", $outputs),
            );
        }

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Chyby během stahování stránek: %s", implode(',', $errors)),
        );

        do {
            $multiInfo = curl_multi_info_read($multiCurl, $remainingMessages);
            if ($multiInfo) {
                ['result' => $resultCode] = $multiInfo;
                if ($resultCode !== CURLE_OK) {
                    $errors[] = sprintf('Nelze číst ze serveru: %s (%d)', curl_strerror($resultCode), $resultCode);
                }
            }
        } while ($multiInfo && $remainingMessages);

        foreach ($curlHandles as $url => $curlHandle) {
            $info = curl_getinfo($curlHandle);
            $content = curl_multi_getcontent($curlHandle);

            if ($info['http_code'] >= 400 && $info['http_code'] !== 401) {
                // Parse headers and body for diagnostic information
                $parts = explode("\r\n\r\n", $content, 2);
                $headers = $parts[0] ?? '';
                $body = $parts[1] ?? '';

                // Get first 10 lines of body for diagnostics
                $bodyLines = explode("\n", $body);
                $firstBodyLines = array_slice($bodyLines, 0, 10);
                $bodyPreview = implode("\n", $firstBodyLines);
                if (count($bodyLines) > 10) {
                    $bodyPreview .= "\n... (" . (count($bodyLines) - 10) . " more lines)";
                }

                $errors[$info['url']] = sprintf(
                    "nepodařilo se stáhnout stránku '%s', response code %d%s\n\nHeaders:\n%s\n\nFirst lines of body:\n%s",
                    $url,
                    $info['http_code'],
                    $info['http_code'] === 404
                        ? ' (nenalezeno)'
                        : '',
                    $headers,
                    $bodyPreview
                );

                $file = LOGY . '/' . DB_NAME . '_' . parse_url($url, PHP_URL_PATH) . '.html';
                $dir = dirname($file);
                if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    self::fail("Nelze vytvořit adresář '$dir' pro výstup selhaného testu URL '$url'");
                }
                $bytes = file_put_contents(
                    $file,
                    $content,
                );
                if ($bytes === false) {
                    self::fail("Nelze uložit data do souboru '$file' pro výstup selhaného testu URL '$url'");
                }
            } else {
                $parts   = explode("\r\n\r\n", $content);
                $body    = $parts[1] ?? false;
                if (!$body) {
                    $errors[$info['url']] = sprintf("stránka '%s' je prázdná", $url);
                }
            }

            curl_multi_remove_handle($multiCurl, $curlHandle);
            curl_close($curlHandle);
        }

        curl_multi_close($multiCurl);

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Chyby během stahování stránek: %s", implode('; ', $errors)),
        );
    }

    protected function loginToAdmin(
        string $cookieFile,
        string $username,
        string $password,
    ): void {
        $adminUrl         = basename(__DIR__ . '/../../admin');
        $adminAbsoluteUrl = 'http://localhost/' . $adminUrl;
        $curlHandle       = curl_init($adminAbsoluteUrl);

        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlHandle, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($curlHandle, CURLOPT_COOKIESESSION, true); // force fresh new cookies session
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        $this->setLocalServerCookieWithGameconTestDb($curlHandle);
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt(
            $curlHandle,
            CURLOPT_POSTFIELDS,
            [Login::LOGIN_INPUT_NAME => $username, Login::PASSWORD_INPUT_NAME => $password],
        );
        $output      = curl_exec($curlHandle);
        $errorNumber = curl_errno($curlHandle);
        if ($errorNumber !== 0) {
            self::fail(
                sprintf(
                    "Chyba při přihlašování do adminu přes CURL: %s, %s (%d). Odpověď:'%s'",
                    $adminAbsoluteUrl,
                    curl_error($curlHandle),
                    $errorNumber,
                    $output,
                ),
            );
        }
        $info = curl_getinfo($curlHandle);
        if ($info['http_code'] >= 300) {
            $this->fail(
                sprintf(
                    "Nepodařilo se přihlásit do adminu přes stránku %s, response code %d%s, output:\n'%s'",
                    $adminAbsoluteUrl,
                    $info['http_code'],
                    $info['http_code'] === 404
                        ? ' (nenalezeno)'
                        : '',
                    $output,
                ),
            );
        }
        curl_close($curlHandle);
    }

    protected function setLocalServerCookieWithGameconTestDb(\CurlHandle $curlHandle): void
    {
        curl_setopt($curlHandle, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1');
    }
}
