<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * „Trvale přihlásit“ v přihlášení do adminu, které slouží i online prezenci, přes skutečnou administraci: zaškrtnuté
 * dá prohlížeči cookie a řádek, se kterými je přihlášený i po zavření prohlížeče, nezaškrtnuté ani jedno, a odhlášení
 * v adminu obojí zruší. Jak cookie funguje, ověřuje TrvalePrihlaseniTest.
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
     * Řádky trvalého přihlášení zapisuje Apache. Test, který by běžel v transakci, by je ze svého snímku neviděl.
     */
    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

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
        $radkuPred = $this->getPocetTrvalychPrihlaseni();
        $prohlizec = $this->prihlasFormularem(trvale: true);

        $poZavreni = $this->zavriProhlizec($prohlizec);

        self::assertSame($radkuPred + 1, $this->getPocetTrvalychPrihlaseni());
        self::assertNotNull($this->getCookieTrvalehoPrihlaseni($poZavreni), 'Trvalé přihlášení má dát prohlížeči cookie');
        $this->assertPrihlasen($poZavreni, 'Cookie trvalého přihlášení má přihlásit i bez session');
    }

    public function testNezaskrtnuteTrvalePrihlaseniSkonciZavrenimProhlizece(): void
    {
        $radkuPred = $this->getPocetTrvalychPrihlaseni();
        $prohlizec = $this->prihlasFormularem(trvale: false);

        $poZavreni = $this->zavriProhlizec($prohlizec);

        self::assertSame($radkuPred, $this->getPocetTrvalychPrihlaseni(), 'Bez volby nemá vzniknout řádek bez cookie');
        self::assertNull($this->getCookieTrvalehoPrihlaseni($poZavreni));
        $this->assertNeprihlasen($poZavreni);
    }

    /**
     * Odhlášení v adminu ruší trvalé přihlášení tohoto prohlížeče: cookie i řádek, i když se session přihlásila
     * z cookie.
     */
    public function testOdhlaseniVAdminuZrusiCookieIRadek(): void
    {
        $radkuPred = $this->getPocetTrvalychPrihlaseni();
        $poZavreni = $this->zavriProhlizec($this->prihlasFormularem(trvale: true));
        $this->assertPrihlasen($poZavreni, 'Session z cookie, jinak test nic nezkouší');

        self::assertSame(303, $this->odesli($poZavreni, self::STRANKA, [
            'odhlasNAdm' => 1,
        ]));

        self::assertNull($this->getCookieTrvalehoPrihlaseni($poZavreni), 'Odhlášení má cookie smazat');
        self::assertSame($radkuPred, $this->getPocetTrvalychPrihlaseni(), 'Odhlášení má řádek zrušit');
        $this->assertNeprihlasen($this->zavriProhlizec($poZavreni));
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
        $this->assertPrihlasen($prohlizec, 'Přihlášení se nepovedlo, test nic nezkouší');

        return $prohlizec;
    }

    private function assertPrihlasen(string $prohlizec, string $zprava): void
    {
        $stranka = $this->otevri($prohlizec, self::STRANKA);
        self::assertSame(200, $stranka['kod'], $stranka['telo']);
        self::assertStringNotContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $stranka['telo'], $zprava);
    }

    private function assertNeprihlasen(string $prohlizec): void
    {
        $stranka = $this->otevri($prohlizec, self::STRANKA);
        self::assertStringContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $stranka['telo']);
    }

    /**
     * Cookie bez data vypršení (session) zavřením prohlížeče zmizí, ostatní zůstanou.
     *
     * @return string soubor s cookies, které prohlížeči zůstaly
     */
    private function zavriProhlizec(string $prohlizec): string
    {
        $poZavreni = $this->getNovaRelace();
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_COOKIEFILE, $prohlizec);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $poZavreni);
        curl_setopt($curl, CURLOPT_COOKIELIST, 'RELOAD');
        curl_setopt($curl, CURLOPT_COOKIELIST, 'SESS');
        unset($curl); // až teď curl zapíše cookies do souboru

        return $poZavreni;
    }

    private function getCookieTrvalehoPrihlaseni(string $prohlizec): ?string
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_COOKIEFILE, $prohlizec);
        curl_setopt($curl, CURLOPT_COOKIELIST, 'RELOAD');
        foreach (curl_getinfo($curl, CURLINFO_COOKIELIST) as $cookie) {
            // formát Netscape, sedm polí oddělených tabulátorem, název a hodnota jsou poslední dvě
            $pole = explode("\t", $cookie);
            if (($pole[5] ?? null) === TrvalePrihlaseni::COOKIE && ($pole[6] ?? '') !== '') {
                return $pole[6];
            }
        }

        return null;
    }

    /**
     * Trvalá přihlášení uživatele, za kterého se testy přihlašují. Apache píše do téže testovací databáze.
     */
    private function getPocetTrvalychPrihlaseni(): int
    {
        return (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_trvala_prihlaseni WHERE id_uzivatele = $0
            SQL, [
            0 => \Uzivatel::SYSTEM,
        ]);
    }
}
