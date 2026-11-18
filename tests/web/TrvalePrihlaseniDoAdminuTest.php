<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * „Trvale přihlásit“ v přihlášení do adminu, které slouží i online prezenci, přes skutečnou administraci: zaškrtnuté
 * dá prohlížeči cookie, se kterou je přihlášený i po zavření prohlížeče, nezaškrtnuté ne. Jak cookie funguje,
 * ověřuje TrvalePrihlaseniTest.
 */
class TrvalePrihlaseniDoAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const string STRANKA = 'uzivatel';

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    /**
     * Admin se používá i na sdílených počítačích infopultu, na rozdíl od webu je proto volba nezaškrtnutá.
     */
    public function testFormularNabidneTrvalePrihlaseniNezaskrtnute(): void
    {
        $odpoved = $this->otevri($this->getNovaRelace(), self::STRANKA);

        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
        self::assertSame(
            1,
            preg_match('~<input\b[^>]*\bname="' . preg_quote(Login::TRVALE_INPUT_NAME, '~') . '"[^>]*>~', $odpoved['telo'], $volba),
            'Přihlašovací formulář má nabídnout trvalé přihlášení',
        );
        self::assertStringContainsString('type="checkbox"', $volba[0]);
        self::assertStringNotContainsString('checked', $volba[0]);
    }

    public function testZaskrtnuteTrvalePrihlaseniPrezijeZavreniProhlizece(): void
    {
        $prohlizec = $this->prihlasFormularem(trvale: true);

        $poZavreni = $this->zavriProhlizec($prohlizec);

        self::assertNotNull($this->getCookieTrvalehoPrihlaseni($poZavreni), 'Trvalé přihlášení má dát prohlížeči cookie');
        $stranka = $this->otevri($poZavreni, self::STRANKA);
        self::assertSame(200, $stranka['kod'], $stranka['telo']);
        self::assertStringNotContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            $stranka['telo'],
            'Cookie trvalého přihlášení má přihlásit i bez session',
        );
    }

    public function testNezaskrtnuteTrvalePrihlaseniSkonciZavrenimProhlizece(): void
    {
        $prohlizec = $this->prihlasFormularem(trvale: false);

        $poZavreni = $this->zavriProhlizec($prohlizec);

        self::assertNull($this->getCookieTrvalehoPrihlaseni($poZavreni));
        $stranka = $this->otevri($poZavreni, self::STRANKA);
        self::assertStringContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $stranka['telo']);
    }

    /**
     * @return string soubor s cookies přihlášeného prohlížeče
     */
    private function prihlasFormularem(bool $trvale): string
    {
        $prohlizec = $this->getNovaRelace();
        $odpoved = $this->odesliSTelem($prohlizec, self::STRANKA, [
            Login::LOGIN_INPUT_NAME    => \Uzivatel::SYSTEM_LOGIN,
            Login::PASSWORD_INPUT_NAME => UNIVERZALNI_HESLO,
            ...($trvale
                ? [
                    Login::TRVALE_INPUT_NAME => '1',
                ]
                : []),
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        $stranka = $this->otevri($prohlizec, self::STRANKA);
        self::assertStringNotContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            $stranka['telo'],
            'Přihlášení se nepovedlo, test nic nezkouší',
        );

        return $prohlizec;
    }

    /**
     * Cookie bez data vypršení (session) zavřením prohlížeče zmizí, ostatní zůstanou.
     *
     * @return string soubor s cookies, které prohlížeči zůstaly
     */
    private function zavriProhlizec(string $prohlizec): string
    {
        $poZavreni = $this->getNovaRelace();
        $zustanou = array_filter(
            $this->getCookies($prohlizec),
            static fn (array $cookie): bool => $cookie['vyprsi'] !== '0',
        );
        file_put_contents(
            $poZavreni,
            implode('', array_map(static fn (array $cookie): string => $cookie['radek'] . "\n", $zustanou)),
        );

        return $poZavreni;
    }

    private function getCookieTrvalehoPrihlaseni(string $prohlizec): ?string
    {
        foreach ($this->getCookies($prohlizec) as $cookie) {
            if ($cookie['nazev'] === TrvalePrihlaseni::COOKIE && $cookie['hodnota'] !== '') {
                return $cookie['hodnota'];
            }
        }

        return null;
    }

    /**
     * Cookies ze souboru curl (formát Netscape: sedm polí oddělených tabulátorem). Řádek s `#HttpOnly_` je cookie,
     * jiný začínající `#` komentář.
     *
     * @return list<array{radek: string, vyprsi: string, nazev: string, hodnota: string}>
     */
    private function getCookies(string $prohlizec): array
    {
        $cookies = [];
        foreach (file($prohlizec, FILE_IGNORE_NEW_LINES) ?: [] as $radek) {
            $pole = explode("\t", $radek);
            if (count($pole) !== 7 || (str_starts_with($radek, '#') && ! str_starts_with($radek, '#HttpOnly_'))) {
                continue;
            }
            $cookies[] = [
                'radek'   => $radek,
                'vyprsi'  => $pole[4],
                'nazev'   => $pole[5],
                'hodnota' => $pole[6],
            ];
        }

        return $cookies;
    }
}
