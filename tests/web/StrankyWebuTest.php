<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class StrankyWebuTest extends TestCase
{
    protected const LOCAL_WEB_SERVER = 'localhost:9999';

    protected static ?Process $localServerProcess = null;

    protected function setUp(): void {
        $this->startLocalWebServer(self::LOCAL_WEB_SERVER);
    }

    protected function startLocalWebServer(string $localAddress) {
        if (!static::$localServerProcess) {
            static::$localServerProcess = new Process(['php', '-S', $localAddress]);
            static::$localServerProcess->start();

            $this->waitForLocalServerBoot();

            $this->skipTestIfLocalWebServerIsNotRunning();
        }
    }

    protected function waitForLocalServerBoot() {
        $curlHandle     = curl_init(self::LOCAL_WEB_SERVER);
        $anotherAttempt = false;
        do {
            curl_setopt($curlHandle, CURLOPT_NOBODY, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, false);
            curl_exec($curlHandle);
            if ($anotherAttempt) {
                usleep(20000);
            }
            $anotherAttempt = true;
        } while (curl_errno($curlHandle) === 7);
        curl_close($curlHandle);
    }

    protected function skipTestIfLocalWebServerIsNotRunning() {
        if (!static::$localServerProcess->isRunning()) {
            self::markTestSkipped(
                sprintf(
                    "Local web server via `%s` controlled by tests is not running. Exit code %d (%s), message: '%s'",
                    (string)static::$localServerProcess->getCommandLine(),
                    static::$localServerProcess->getExitCode(),
                    static::$localServerProcess->getExitCodeText(),
                    trim(static::$localServerProcess->getErrorOutput())
                )
            );
        }
    }

    /**
     * @test
     * @dataProvider provideWebUrls
     * @param string[] $urls
     */
    public function Muzu_si_zobrazit_kazdou_stranku_na_webu(array $urls) {

        $multiCurl   = curl_multi_init();
        $curlHandles = [];
        $errors      = [];

        foreach ($urls as $url) {
            $curlHandle = curl_init($url);
            if (!$curlHandle) {
                $errors[$url] = sprintf("Nelze otevřít CURL handle pro URL '%s'", $url);
                continue;
            }
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10); // timeout na připojení
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5); // timeout na stahování
            curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlHandle, CURLOPT_NOBODY, true);
            curl_setopt($curlHandle, CURLOPT_HEADER, true);
            curl_multi_add_handle($multiCurl, $curlHandle);

            $curlHandles[$url] = $curlHandle;
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

    public function provideWebUrls(): array {
        return [
            'základní' => [
                [
                    self::LOCAL_WEB_SERVER . '/' . basename(__DIR__ . '/../../web'),
                    self::LOCAL_WEB_SERVER . '/' . basename(__DIR__ . '/../../admin'),
                ],
            ],
        ];
    }
}
