<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\ApiAdminu;
use Gamecon\Admin\OmniboxAdminu;
use Gamecon\Admin\PristupDoAdminu;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Admin API je jen pro toho, kdo v adminu smí aspoň jednu stránku: nepřihlášený dostane 401, přihlášený účastník bez
 * práv 403, od každého endpointu, i od toho, který přibude později, kromě výjimek z ApiAdminu::BEZ_PRIHLASENI.
 * Totéž platí pro zvláštní stránky adminu (program účastníka, reporty) kromě veřejných z
 * PristupDoAdminu::VEREJNE_STRANKY a omniboxu, který má vlastní okruh (OmniboxAdminuTest). Přes skutečnou administraci.
 */
class PristupDoApiAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_UCASTNIKA = 9831;
    private const string LOGIN_UCASTNIKA = 'test-api-ucastnik';

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (%1$d, '%2$s', 'Test', 'Účastník', 'test-api-ucastnik@example.invalid')
SQL,
                self::ID_UCASTNIKA,
                self::LOGIN_UCASTNIKA,
            ),
        ];
    }

    #[DataProvider('endpointyJenProAdmin')]
    public function testNeprihlasenyDostane401(string $endpoint, string $metoda): void
    {
        $this->assertOdmitnuto($this->getNovaRelace(), $endpoint, $metoda, 401);
    }

    /**
     * Účastník bez práv v adminu nesmí žádnou stránku, a tak ani API. Přihlásí se jednou pro všechny endpointy.
     */
    public function testUcastnikBezPravDostane403(): void
    {
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);
        foreach (self::endpointyJenProAdmin() as [$endpoint, $metoda]) {
            $this->assertOdmitnuto($relace, $endpoint, $metoda, 403);
        }
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function endpointyJenProAdmin(): \Generator
    {
        $endpointy = array_map(
            static fn (string $soubor): string => basename($soubor, '.php'),
            glob(__DIR__ . '/../../admin/scripts/api/*.php'),
        );
        // neexistující endpoint neprozradí, že neexistuje
        $endpointy[] = 'neexistujici';
        foreach ($endpointy as $endpoint) {
            if (ApiAdminu::isBezPrihlaseni($endpoint)) {
                continue;
            }
            foreach (['GET', 'POST'] as $metoda) {
                yield "{$metoda} {$endpoint}" => [$endpoint, $metoda];
            }
        }
    }

    /**
     * Každá zvláštní stránka, i ta, která přibude později, kromě omniboxu: ten smí každý, kdo se smí přepnout na jiného
     * uživatele, a to je na locale, kde testy běží, každý. Kdo ho smí jinde, ověřuje OmniboxAdminuTest.
     */
    public function testUcastnikBezPravNeotevreZvlastniStranku(): void
    {
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);
        foreach (self::getZvlastniStrankyJenProAdmin() as $stranka) {
            $odpoved = $this->otevri($relace, $stranka);
            self::assertSame(403, $odpoved['kod'], $stranka);
            self::assertStringNotContainsString('@example.invalid', $odpoved['telo'], $stranka);
        }
    }

    public function testOrganizatorOmniboxPouzije(): void
    {
        $odpoved = $this->otevri($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'ajax-omnibox?term=Účastník');

        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
        self::assertIsArray(json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVerejnouZvlastniStrankuOtevreINeprihlaseny(): void
    {
        foreach (array_keys(PristupDoAdminu::VEREJNE_STRANKY) as $stranka) {
            $odpoved = $this->otevri($this->getNovaRelace(), $stranka);
            self::assertSame(200, $odpoved['kod'], "{$stranka}: {$odpoved['telo']}");
        }
    }

    /**
     * Adresy skriptů z admin/scripts/zvlastni, tedy `stranka` nebo `stranka/podstranka`, kromě veřejných a omniboxu.
     *
     * @return list<string>
     */
    private static function getZvlastniStrankyJenProAdmin(): array
    {
        $adresar = __DIR__ . '/../../admin/scripts/zvlastni';
        $stranky = [];
        foreach ([...glob($adresar . '/*.php'), ...glob($adresar . '/*/*.php')] as $soubor) {
            $stranka = substr($soubor, strlen($adresar) + 1, -strlen('.php'));
            if (! PristupDoAdminu::isVerejnaStranka($stranka) && $stranka !== OmniboxAdminu::STRANKA) {
                $stranky[] = $stranka;
            }
        }
        self::assertFileExists($adresar . '/' . OmniboxAdminu::STRANKA . '.php');
        self::assertContains('program-osobni', $stranky);

        return $stranky;
    }

    public function testVstupNaPreviewBezTokenuBranyNeexistuje(): void
    {
        $odpoved = $this->otevri($this->getNovaRelace(), 'api/preview-vstup');

        self::assertSame(404, $odpoved['kod'], $odpoved['telo']);
    }

    /**
     * Soubor endpointu nebo zvláštní stránky otevřený přímo by běžel bez admin/index.php, tedy bez kontroly přihlášení
     * i práv; admin/scripts/.htaccess ho proto odmítne. Jen přes routu `api/…` nebo `stranka`.
     */
    public function testSkriptAdminuNejdeOtevritPrimo(): void
    {
        $relace = $this->getNovaRelace();
        $adresar = __DIR__ . '/../../admin/scripts';
        $skripty = [...glob($adresar . '/api/*.php'), ...glob($adresar . '/zvlastni/*.php'), $adresar . '/prihlaseni.php'];
        foreach ($skripty as $skript) {
            $cesta = 'scripts/' . substr($skript, strlen($adresar) + 1);
            $odpoved = $this->otevri($relace, $cesta);
            self::assertSame(403, $odpoved['kod'], "{$cesta}: {$odpoved['telo']}");
        }
    }

    private function assertOdmitnuto(string $relace, string $endpoint, string $metoda, int $ocekavanyKod): void
    {
        $curl = $this->curl($relace, 'api/' . $endpoint);
        if ($metoda === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, [
                'akce'      => 'smazTym',
                'idTymu'    => 1,
                'prihlasit' => 1,
            ]);
        }
        $odpoved = $this->proved($curl);

        $popis = "{$metoda} {$endpoint}: {$odpoved['telo']}";
        self::assertSame($ocekavanyKod, $odpoved['kod'], $popis);
        self::assertStringStartsWith('application/json', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE), $popis);
        self::assertArrayHasKey('error', json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR), $popis);
    }
}
