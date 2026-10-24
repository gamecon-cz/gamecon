<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Tests\Model\Web\UklidNovychAdresaru;

/**
 * Přihlášení do skutečné administrace a požadavky na ni (Apache, session, multipart formuláře), tak jak je posílá
 * prohlížeč. Třída, která trait používá, musí mít pole `$kUklidu` se soubory k smazání po testu.
 */
trait ZadostiDoAdmina
{
    use UklidNovychAdresaru;

    /**
     * Přihlášení posíláme na adresu modulu. Na `/admin` a `/admin/` (to dělá loginToAdmin()) Apache
     * přesměruje a curl z POSTu udělá GET, takže se přihlašovací údaje k PHP vůbec nedostanou.
     */
    private function prihlas(string $login): string
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        $this->kUklidu[] = $cookieFile;
        $curl = $this->curl($cookieFile, 'aktivity/medailonky');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            Login::LOGIN_INPUT_NAME    => $login,
            Login::PASSWORD_INPUT_NAME => UNIVERZALNI_HESLO,
        ]);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));
        unset($curl); // až teď curl zapíše cookies do souboru

        self::assertStringNotContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            (string) $odpoved,
            "Přihlášení '{$login}' se nepovedlo",
        );

        return $cookieFile;
    }

    /**
     * @param array<string, mixed>|string $pole pole se pošle jako multipart, řetězec jako hotové tělo požadavku
     *                                          (curl pole v poli neumí)
     *
     * @return int HTTP kód odpovědi, přesměrování se nenásledují
     */
    private function odesli(string $cookieFile, string $url, array|string $pole): int
    {
        $curl = $this->curl($cookieFile, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));

        return curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

    private function stahni(string $cookieFile, string $url): string
    {
        $curl = $this->curl($cookieFile, $url);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));
        self::assertSame(200, curl_getinfo($curl, CURLINFO_HTTP_CODE), (string) $odpoved);

        return (string) $odpoved;
    }

    private function curl(string $cookieFile, string $url): \CurlHandle
    {
        $curl = curl_init('http://localhost/' . basename(__DIR__ . '/../../admin') . '/' . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
        // hlášky (oznámení, chyby) chodí v cookies až na další stránku
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
        $this->setLocalServerCookieWithGameconTestDb($curl);

        return $curl;
    }
}
