<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Tests\Model\Web\UklidNovychAdresaru;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Přihlášení do skutečné administrace a požadavky na ni (Apache, session, multipart formuláře), tak jak je posílá
 * prohlížeč. Třída, která trait používá, musí dědit z AbstractTestWeb a mít pole `$kUklidu` se soubory, které se po
 * testu smažou.
 */
trait ZadostiDoAdmina
{
    use UklidNovychAdresaru;

    /**
     * Před setUp() třídy, která trait používá.
     *
     * @before
     */
    protected function pripravZadostiDoAdmina(): void
    {
        self::vyresDnsPredPrvniZadosti();
    }

    /**
     * Po tearDown() třídy, která trait používá.
     *
     * @after
     */
    protected function uklidPoZadostechDoAdmina(): void
    {
        (new Filesystem())->remove($this->kUklidu);
    }

    /**
     * Přihlášení posíláme na adresu modulu. Na `/admin` a `/admin/` (to dělá loginToAdmin()) Apache
     * přesměruje a curl z POSTu udělá GET, takže se přihlašovací údaje k PHP vůbec nedostanou.
     */
    private function prihlas(string $login): string
    {
        $cookieFile = $this->getNovaRelace();
        $curl = $this->curlPrihlaseni($cookieFile, $login, 'aktivity/medailonky');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $odpoved = $this->proved($curl);
        unset($curl); // až teď curl zapíše cookies do souboru

        self::assertStringNotContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            $odpoved['telo'],
            "Přihlášení '{$login}' se nepovedlo",
        );

        return $cookieFile;
    }

    /**
     * Relace bez cookies, tedy nepřihlášený návštěvník; soubor s cookies se po testu smaže.
     */
    private function getNovaRelace(): string
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        $this->kUklidu[] = $cookieFile;

        return $cookieFile;
    }

    /**
     * Odešle přihlašovací formulář v už existující relaci na zadanou adresu, přesměrování nenásleduje.
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function prihlasVRelaci(string $cookieFile, string $login, string $url): array
    {
        return $this->proved($this->curlPrihlaseni($cookieFile, $login, $url));
    }

    private function curlPrihlaseni(string $cookieFile, string $login, string $url): \CurlHandle
    {
        $curl = $this->curl($cookieFile, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            Login::LOGIN_INPUT_NAME    => $login,
            Login::PASSWORD_INPUT_NAME => UNIVERZALNI_HESLO,
        ]);

        return $curl;
    }

    /**
     * GET na adresu adminu, přesměrování nenásleduje.
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function otevri(string $cookieFile, string $url): array
    {
        return $this->proved($this->curl($cookieFile, $url));
    }

    /**
     * Totéž co @see otevri() pro absolutní adresu, třeba z přesměrování.
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function otevriAdresu(string $cookieFile, string $adresa): array
    {
        return $this->proved($this->curlNaAdresu($cookieFile, $adresa));
    }

    private function vyberPracovnihoUzivatele(string $cookieFile, int $idUzivatele): void
    {
        self::assertSame(303, $this->odesli($cookieFile, 'uzivatel', [
            'vybratUzivateleProPraci' => 1,
            'id'                      => $idUzivatele,
        ]));
    }

    /**
     * @param array<string, mixed>|string $pole pole se pošle jako multipart, řetězec jako hotové tělo požadavku
     *                                          (curl pole v poli neumí)
     *
     * @return int HTTP kód odpovědi, přesměrování se nenásledují
     */
    private function odesli(string $cookieFile, string $url, array|string $pole): int
    {
        return $this->odesliSTelem($cookieFile, $url, $pole)['kod'];
    }

    /**
     * @param array<string, mixed>|string $pole
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function odesliSTelem(string $cookieFile, string $url, array|string $pole): array
    {
        $curl = $this->curl($cookieFile, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);

        return $this->proved($curl);
    }

    private function stahni(string $cookieFile, string $url): string
    {
        return $this->stahniOdpoved($this->curl($cookieFile, $url));
    }

    /**
     * @param string $cesta absolutní cesta na serveru, třeba `/web/sponzori`
     */
    private function stahniVerejnou(string $cookieFile, string $cesta): string
    {
        return $this->stahniOdpoved($this->curlNaAdresu($cookieFile, 'http://localhost' . $cesta));
    }

    private function stahniOdpoved(\CurlHandle $curl): string
    {
        $odpoved = $this->proved($curl);
        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);

        return $odpoved['telo'];
    }

    /**
     * @return array{kod: int, kam: string, telo: string} `kam` je cíl přesměrování, bez něj prázdné
     */
    private function proved(\CurlHandle $curl): array
    {
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));

        return [
            'kod'  => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'kam'  => (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL),
            'telo' => (string) $odpoved,
        ];
    }

    private function curl(string $cookieFile, string $url): \CurlHandle
    {
        return $this->curlNaAdresu($cookieFile, 'http://localhost/' . basename(__DIR__ . '/../../admin') . '/' . $url);
    }

    private function curlNaAdresu(string $cookieFile, string $adresa): \CurlHandle
    {
        $curl = curl_init($adresa);
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
