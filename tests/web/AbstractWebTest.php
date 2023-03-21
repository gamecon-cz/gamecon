<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractDbTest;
use Uzivatel;
use Gamecon\Login\Login;
use Gamecon\Pravo;
use Symfony\Component\Process\Process;

abstract class AbstractWebTest extends AbstractDbTest
{

    protected static int $unusedLocalServerPort = 8888;
    /**
     * @var Process[]
     */
    protected array $localServersProcesses = [];
    /** @var string[] */
    protected array $cookieFilesPerServer = [];

    protected static function keepDbChangesInTransaction(): bool {
        return false;
    }

    /**
     * @return string[]
     */
    protected static function getInitQueries(): array {
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

    protected function tearDown(): void {
        parent::tearDown();
        /*        foreach ($this->localServersProcesses as $port => $localServersProcess) {
                    $localServersProcess->stop();
                    if (static::$unusedLocalServerPort === $port + 1) {
                        static::$unusedLocalServerPort = $port; // port se uvolnil
                    }
                }*/
        foreach ($this->cookieFilesPerServer as $cookieFile) {
            unlink($cookieFile);
        }
    }

    protected function testAdminPagesAccessibility(array $urls) {
        $this->testPagesAccessibility($urls, Uzivatel::SYSTEM_LOGIN, UNIVERZALNI_HESLO);
    }

    /**
     * @param string[] $urls
     */
    protected function testPagesAccessibility(array $urls, string $username = null, string $password = null) {
        $urlsOfUnusedLocalServers = $this->getUrlsOfUnusedLocalServers(count($urls), $username, $password);
        reset($urlsOfUnusedLocalServers);

        $multiCurl   = curl_multi_init();
        $curlHandles = [];
        $errors      = [];

        foreach ($urls as $url) {
            $localServerUrl = current($urlsOfUnusedLocalServers);
            next($urlsOfUnusedLocalServers);
            $absoluteUrl = $localServerUrl . '/' . $url;
            $curlHandle  = curl_init($absoluteUrl);
            if (!$curlHandle) {
                $errors[] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $absoluteUrl);
                continue;
            }

            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10); // timeout na připojení
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10); // timeout na stahování
            curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, true);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            $this->setLocalServerCookieWithGameconTestDb($curlHandle);
            curl_setopt($curlHandle, CURLOPT_COOKIEFILE, $this->getCookieFileForServer($localServerUrl));

            curl_multi_add_handle($multiCurl, $curlHandle);

            $curlHandles[$absoluteUrl] = $curlHandle;
        }

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Potíže s CURL: %s", implode(',', $errors))
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
                implode("\n", $outputs)
            );
        }

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Chyby během stahování stránek: %s", implode(',', $errors))
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
            if ($info['http_code'] >= 400) {
                $errors[$info['url']] = sprintf(
                    "nepodařilo se stáhnout stránku '%s', response code %d%s",
                    $url,
                    $info['http_code'],
                    $info['http_code'] === 404
                        ? ' (nenalezeno)'
                        : ''
                );
            } else {
                $content = curl_multi_getcontent($curlHandle);
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
            sprintf("Chyby během stahování stránek: %s", implode('; ', $errors))
        );
    }

    protected function getUrlsOfUnusedLocalServers(int $count, ?string $username, ?string $password): array {
        $localServersUrls = [];
        for ($current = 1; $current <= $count; $current++) {
            $localServersUrls[] = $this->getUrlOfUnusedLocalServer(false);
        }

        $this->waitForLocalServersBoot($localServersUrls);

        if ($username && $password) {
            foreach ($localServersUrls as $localServerUrl) {
                // ke každé instanci PHP serveru se musíme přihlásit zvlášť, protože každá má své cookies
                $this->loginToAdmin($localServerUrl, $username, $password);
            }
        }

        return $localServersUrls;
    }

    /**
     * PHP -S server is single thread only - so we will create one instance for every tested URL to speed tests up
     * @param string[] $localServersUrls
     */
    protected function waitForLocalServersBoot(array $localServersUrls) {
        $multiCurl   = curl_multi_init();
        $curlHandles = [];

        foreach ($localServersUrls as $localServersUrl) {
            $curlHandle = curl_init($localServersUrl);
            if (!$curlHandle) {
                $this->fail(
                    sprintf("Nelze otevřít CURL handle pro URL '%s'", $localServersUrl)
                );
            }
            curl_setopt($curlHandle, CURLOPT_NOBODY, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, false);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5); // timeout na připojení
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5); // timeout na stahování
            curl_multi_add_handle($multiCurl, $curlHandle);
            $curlHandles[] = $curlHandle;
        }

        do {
            $someNotYetReady = false;
            do {
                ob_start();
                $totalResultCode = curl_multi_exec($multiCurl, $running);
                ob_end_clean();
                // Wait for activity on any curl-connection
                if ($running > 0 && curl_multi_select($multiCurl) === -1) {
                    usleep(2000);
                }
            } while ($running > 0 && $totalResultCode === CURLM_OK);
            if ($totalResultCode !== CURLM_OK) {
                $this->fail(
                    sprintf(
                        'Nepodařilo se načíst URLs %s s chybou %s (%d)',
                        implode('; ', $localServersUrls),
                        curl_multi_strerror($totalResultCode),
                        $totalResultCode
                    )
                );
            }

            do {
                $multiInfo = curl_multi_info_read($multiCurl, $remainingMessages);
                if ($multiInfo) {
                    ['result' => $resultCode] = $multiInfo;
                    if ($resultCode === 7) { // server not yet ready
                        $someNotYetReady = true;
                        usleep(20000);
                        break; // break current do - while, therefore continue parent do - while
                    }
                    if ($resultCode !== CURLE_OK) {
                        $this->fail(
                            sprintf(
                                'Nepodařilo se načíst URLs %s s chybou %s (%d)',
                                implode('; ', $localServersUrls),
                                curl_strerror($resultCode),
                                $resultCode
                            )
                        );
                    }
                }
            } while ($multiInfo && $remainingMessages);
        } while ($someNotYetReady);

        foreach ($curlHandles as $curlHandle) {
            curl_multi_remove_handle($multiCurl, $curlHandle);
            curl_close($curlHandle);
        }

        curl_multi_close($multiCurl);
    }

    protected function getUrlOfUnusedLocalServer(
        bool $waitForBoot = true,
    ): string {
        $localServerProcess = null;
        $attemptsRemains    = 5;
        do {
            $port = $this->getUnusedPort();
            static::$unusedLocalServerPort++;
            $localServerUrl = "localhost:$port";
            try {
                $localServerProcess = $this->startLocalWebServer($localServerUrl, $waitForBoot);
            } catch (\RuntimeException $runtimeException) {
                $attemptsRemains--;
            }
        } while (!$localServerProcess && $attemptsRemains > 0);
        if (!$localServerProcess) {
            self::fail("Local web server on URL `%s` does not start",);
        }
        $this->localServersProcesses[$port] = $localServerProcess;
        return $localServerUrl;
    }

    protected function startLocalWebServer(
        string $localServerUrl,
        bool   $waitForBoot = true
    ): Process {
        $localServerProcess = new Process(['php', '-S', $localServerUrl]);
        $localServerProcess->start();

        if (!$localServerProcess->isRunning()) {
            throw new \RuntimeException(
                sprintf(
                    "Failed command %s with exit code %d and output %s (%s)",
                    $localServerProcess->getCommandLine(),
                    $localServerProcess->getExitCode(),
                    $localServerProcess->getExitCodeText(),
                    $localServerProcess->getErrorOutput(),
                )
            );
        }

        if ($waitForBoot) {
            $this->waitForLocalServerBoot($localServerUrl);
        }

        return $localServerProcess;
    }

    protected function getUnusedPort(): int {
        $localServerProcess = new Process(['ss', '--listening', '--tcp', '--udp', '--raw', '--no-header']);
        $localServerProcess->start();
        $localServerProcess->wait();
        $output = $localServerProcess->getOutput();
        preg_match_all('~\d+[.]\d+[.]\d+([.]\d+)*:(?<ports>\d+)~', $output, $matches);
        $usedPorts = $matches['ports'];
        if (!$usedPorts) {
            return static::$unusedLocalServerPort;
        }
        while (in_array(static::$unusedLocalServerPort, $usedPorts, false)) {
            static::$unusedLocalServerPort++;
        }
        return static::$unusedLocalServerPort;
    }

    protected function failTestIfLocalWebServerIsNotRunning(Process $process) {
        if (!$process->isRunning()) {
            self::fail(
                sprintf(
                    "Local web server via `%s` controlled by tests is not running. Exit code %d (%s), message: '%s'",
                    (string)$process->getCommandLine(),
                    $process->getExitCode(),
                    $process->getExitCodeText(),
                    trim($process->getErrorOutput())
                )
            );
        }
    }

    protected function waitForLocalServerBoot(string $url) {
        $curlHandle     = curl_init($url);
        $anotherAttempt = false;
        do {
            curl_setopt($curlHandle, CURLOPT_NOBODY, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, false);
            ob_start();
            curl_exec($curlHandle);
            ob_end_clean();
            if ($anotherAttempt) {
                usleep(20000);
            }
            $anotherAttempt = true;
        } while (curl_errno($curlHandle) === 7);
        curl_close($curlHandle);
    }

    protected function loginToAdmin(string $serverUrl, string $username, string $password) {
        $cookieFile       = $this->getCookieFileForServer($serverUrl);
        $adminUrl         = basename(__DIR__ . '/../../admin');
        $adminAbsoluteUrl = $serverUrl . '/' . $adminUrl;
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
            [Login::LOGIN_INPUT_NAME => $username, Login::PASSWORD_INPUT_NAME => $password]
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
                    $output
                )
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
                    $output
                )
            );
        }
        curl_close($curlHandle);
    }

    protected function getCookieFileForServer(string $serverUrl): string {
        if (empty($this->cookieFilesPerServer[$serverUrl])) {
            $this->cookieFilesPerServer[$serverUrl] = tempnam(sys_get_temp_dir(), 'server_cookie_file_');
        }
        return $this->cookieFilesPerServer[$serverUrl];
    }

    protected function setLocalServerCookieWithGameconTestDb(\CurlHandle $curlHandle) {
        curl_setopt($curlHandle, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1');
    }

}
