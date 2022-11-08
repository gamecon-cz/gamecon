<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Tests\Db\DbTest;
use Symfony\Component\Process\Process;

abstract class AbstractWebTest extends DbTest
{

    protected static int $freeLocalServerPort = 8888;
    /**
     * @var Process[]
     */
    protected array $localServersProcesses = [];

    protected function tearDown(): void {
        parent::tearDown();
        foreach ($this->localServersProcesses as $port => $localServersProcess) {
            $localServersProcess->stop();
            if (static::$freeLocalServerPort + 1 === $port) {
                static::$freeLocalServerPort = $port;
            }
        }
    }

    /**
     * @param string[] $urls
     */
    protected function testPagesAccessibility(array $urls, string $username = null, string $password = null) {
        $urlsOfFreeLocalServers = $this->getUrlsOfFreeLocalServers(count($urls), $username, $password);
        reset($urlsOfFreeLocalServers);

        $multiCurl   = curl_multi_init();
        $curlHandles = [];
        $errors      = [];

        foreach ($urls as $url) {
            $localServerUrl = current($urlsOfFreeLocalServers);
            next($urlsOfFreeLocalServers);
            $absoluteUrl = $localServerUrl . '/' . $url;
            $curlHandle  = curl_init($absoluteUrl);
            if (!$curlHandle) {
                $errors[] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $absoluteUrl);
                continue;
            }
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10); // timeout na připojení
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5); // timeout na stahování
            curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, true);
            curl_multi_add_handle($multiCurl, $curlHandle);

            $curlHandles[$absoluteUrl] = $curlHandle;
        }

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Potíže s CURL: %s", implode(',', $errors))
        );

        $outputs = [];
        do {
            ob_start();
            $totalResultCode = curl_multi_exec($multiCurl, $running);
            $outputs[]       = ob_get_clean();
            // Wait for activity on any curl-connection
            if ($running > 0 && curl_multi_select($multiCurl) === -1) {
                usleep(100000);
            }
        } while ($running > 0 && $totalResultCode === CURLM_OK);
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
                    $errors[] = sprintf('%s (%d)', curl_strerror($resultCode), $resultCode);
                }
            }
        } while ($multiInfo && $remainingMessages);

        foreach ($curlHandles as $url => $curlHandle) {
            $info = curl_getinfo($curlHandle);
            if ($info['http_code'] >= 400) {
                $errors[$info['url']] = sprintf(
                    'Nepodařilo se stáhnout stránku %s, response code %d%s',
                    $url,
                    $info['http_code'],
                    $info['http_code'] === 404
                        ? ' (nenalezeno)'
                        : ''
                );
            }
            curl_multi_remove_handle($multiCurl, $curlHandle);
            curl_close($curlHandle);
        }

        curl_multi_close($multiCurl);

        self::assertCount(
            0,
            $errors ?? [],
            sprintf("Chyby během stahování stránek: %s", implode(',', $errors))
        );
    }

    protected function getUrlsOfFreeLocalServers(int $count, ?string $username, ?string $password): array {
        $localServersUrls = [];
        for ($current = 1; $current <= $count; $current++) {
            $localServersUrls[] = $this->getUrlOfFreeLocalServer(false);
        }

        $this->waitForLocalServersBoot($localServersUrls);

        if ($username && $password) {
            foreach ($localServersUrls as $localServerUrl) {
                // ke každé instanci PHP serveru se musíme přihlásit zvlášť, protože každá má své cookies
                // TODO solve keeping session
//                $this->loginToAdmin($localServerUrl . '/admin', $username, $password);
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

    protected function getUrlOfFreeLocalServer(
        bool $waitForBoot = true,
    ): string {
        $port = static::$freeLocalServerPort;
        static::$freeLocalServerPort++;
        $localServerUrl                     = "localhost:$port";
        $localServerProcess                 = $this->startLocalWebServer($localServerUrl, $waitForBoot);
        $this->localServersProcesses[$port] = $localServerProcess;
        return $localServerUrl;
    }

    protected function startLocalWebServer(
        string $localServerUrl,
        bool   $waitForBoot = true
    ): Process {
        $localServerProcess = new Process(['php', '-S', $localServerUrl]);
        $localServerProcess->start();

        $this->failTestIfLocalWebServerIsNotRunning($localServerProcess);

        if ($waitForBoot) {
            $this->waitForLocalServerBoot($localServerUrl);
        }

        return $localServerProcess;
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

    protected function loginToAdmin(string $adminAbsoluteUrl, string $username, string $password) {
        $curlHandle = curl_init($adminAbsoluteUrl);
        curl_setopt($curlHandle, CURLOPT_NOBODY, true);
        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
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
                    "Nepodařilo se přihlásit do adminu přes stránku %s, response code %d%s, output: '%s'",
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

}
