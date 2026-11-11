<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\ApiAdminu;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Admin API je jen pro toho, kdo v adminu smí aspoň jednu stránku: nepřihlášený dostane 401, přihlášený účastník bez
 * práv 403, od každého endpointu, i od toho, který přibude později, kromě výjimek z ApiAdminu::BEZ_PRIHLASENI.
 * Přes skutečnou administraci.
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

    public function testVstupNaPreviewBezTokenuBranyNeexistuje(): void
    {
        $odpoved = $this->otevri($this->getNovaRelace(), 'api/preview-vstup');

        self::assertSame(404, $odpoved['kod'], $odpoved['telo']);
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
