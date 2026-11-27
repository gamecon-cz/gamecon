<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Push notifikace přes skutečný Apache: vypnutá funkce navenek neexistuje, API pustí jen přihlášeného s požadavkem
 * ze stránky webu a stránky v adminu jen toho, kdo má právo. Odesílání samo se tu nezkouší (testovací prostředí má
 * místo VAPID klíčů jen hodnoty jejich tvaru), to ověřují tests/PushNotifikace s falešnou push službou.
 */
class PushNotifikaceTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_UCASTNIKA = 9871;

    private const string LOGIN_UCASTNIKA = 'test-push-ucastnik';

    private const string API = 'http://localhost/web/api/pushSubscription';

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (%1$d, '%2$s', 'Test', 'Účastník', 'test-push-ucastnik@example.invalid')
SQL,
                self::ID_UCASTNIKA,
                self::LOGIN_UCASTNIKA,
            ),
        ];
    }

    protected function tearDown(): void
    {
        $this->nastavZapnuti(false);
        parent::tearDown();
    }

    public function testVypnuteNotifikaceNavenekNeexistuji(): void
    {
        $this->nastavZapnuti(false);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        self::assertSame(404, $this->posliJson($relace, [
            'akce' => 'stav',
        ])['kod'], 'Vypnuté API se netváří, že existuje.');
        $nastaveni = $this->stahniVerejnou($relace, '/web/registrace');
        self::assertStringNotContainsString('data-push-notifikace', $nastaveni);
        self::assertStringNotContainsString('gc-push-notifikace', $nastaveni);
        self::assertStringNotContainsString('rel="manifest"', $nastaveni);
    }

    public function testZapnuteNotifikaceVidiKazdyPrihlaseny(): void
    {
        $this->nastavZapnuti(true);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        $nastaveni = $this->stahniVerejnou($relace, '/web/registrace');
        self::assertStringContainsString('data-push-notifikace', $nastaveni, 'Ovládání je v Nastavení i pro účastníka bez práv.');
        self::assertStringContainsString('data-api="http://localhost/web/api/pushSubscription"', $nastaveni);
        self::assertStringContainsString('data-service-worker="http://localhost/web/sw.js"', $nastaveni);
        self::assertStringContainsString('data-scope="http://localhost/web/"', $nastaveni);
        self::assertStringNotContainsString(VAPID_PRIVATE_KEY, $nastaveni, 'Soukromý klíč do stránky nesmí.');

        $uvodNeprihlaseneho = $this->stahniVerejnou($this->getNovaRelace(), '/web/');
        self::assertStringContainsString('rel="manifest"', $uvodNeprihlaseneho, 'Web na plochu se v iOS přidává ještě před přihlášením.');
        self::assertStringNotContainsString('gc-push-notifikace', $uvodNeprihlaseneho, 'Skript notifikací je jen pro přihlášené.');
    }

    /**
     * Service worker i manifest jsou statické soubory v kořeni webu, aby jejich scope byl celý web.
     */
    public function testServiceWorkerAManifestJsouVKoreniWebu(): void
    {
        $serviceWorker = $this->stahniVerejnouStranku('web/sw.js');
        self::assertSame(200, $serviceWorker['status']);
        self::assertStringContainsString('javascript', $serviceWorker['contentType']);
        self::assertStringContainsString('notificationclick', $serviceWorker['telo']);

        $manifest = $this->stahniVerejnouStranku('web/manifest.webmanifest');
        self::assertSame(200, $manifest['status']);
        self::assertStringContainsString('application/manifest+json', $manifest['contentType']);
        $obsah = json_decode($manifest['telo'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('standalone', $obsah['display'], 'Bez toho iOS web na ploše nebere jako aplikaci a notifikace nepovolí.');
        foreach ($obsah['icons'] as $ikona) {
            self::assertSame(200, $this->stahniVerejnouStranku('web/' . $ikona['src'])['status'], "Ikona {$ikona['src']} z manifestu musí existovat.");
        }
    }

    public function testApiPustiJenPrihlasenehoSPozadavkemZeStrankyWebu(): void
    {
        $this->nastavZapnuti(true);
        $neprihlaseny = $this->getNovaRelace();
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        self::assertSame(401, $this->posliJson($neprihlaseny, [
            'akce' => 'stav',
        ])['kod']);
        self::assertSame(405, $this->otevriAdresu($relace, self::API)['kod'], 'Jen POST.');
        self::assertSame(
            404,
            $this->posliJson($relace, [
                'akce' => 'stav',
            ], referer: null)['kod'],
            'POST bez Refereru z webu zahodí omezCsrf() dřív, než se API načte.',
        );
        self::assertSame(404, $this->posliJson($relace, [
            'akce' => 'stav',
        ], referer: 'https://example.com/')['kod']);
        self::assertSame(
            415,
            $this->posliJson($relace, [
                'akce' => 'stav',
            ], typObsahu: 'application/x-www-form-urlencoded')['kod'],
            'Formulář z cizí stránky umí poslat jen tenhle typ obsahu, JSON ne.',
        );
        self::assertSame(400, $this->posliJson($relace, [
            'akce' => 'neznama',
        ])['kod']);

        $stav = $this->posliJson($relace, [
            'akce' => 'stav',
        ]);
        self::assertSame(200, $stav['kod'], $stav['telo']);
        self::assertSame(
            [
                'stav'              => 'bez-trvaleho-prihlaseni',
                'zrusitVProhlizeci' => false,
            ],
            json_decode($stav['telo'], true, flags: JSON_THROW_ON_ERROR),
            'Přihlášení do adminu bez „Trvale přihlásit“ trvalé přihlášení nezakládá.',
        );
    }

    /**
     * Celá cesta přes Apache s cookie trvalého přihlášení, jak ji nese prohlížeč přihlášený „trvale“:
     * zapnout, stav, vypnout.
     */
    public function testTrvalePrihlasenySiNotifikaceZapneAVypne(): void
    {
        $this->nastavZapnuti(true);
        $selektor = bin2hex(random_bytes(32));
        $token = bin2hex(random_bytes(32));
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni SET id_uzivatele = $0, selektor_hash = $1, token_hash = $2
            SQL, [
            0 => self::ID_UCASTNIKA,
            1 => hash('sha256', $selektor),
            2 => hash('sha256', $token),
        ]);
        // prohlížeč bez session, jen s cookie; první požadavek ji uplatní a vymění token, další jdou se session
        $relace = $this->getNovaRelace();
        $cookie = TrvalePrihlaseni::COOKIE . '=' . $selektor . '.0.' . $token;
        $endpoint = 'https://fcm.googleapis.com/fcm/send/' . bin2hex(random_bytes(8));
        $subscription = [
            'endpoint' => $endpoint,
            'keys'     => [
                'p256dh' => 'B' . str_repeat('A', 86),
                'auth'   => str_repeat('A', 22),
            ],
        ];

        $zapnuti = $this->posliJson($relace, [
            'akce'         => 'zapnout',
            'subscription' => $subscription,
        ], cookie: $cookie);
        self::assertSame(200, $zapnuti['kod'], $zapnuti['telo']);
        self::assertSame(
            [
                'stav'              => 'zapnuto',
                'zrusitVProhlizeci' => false,
            ],
            json_decode($zapnuti['telo'], true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            'neznámé zařízení',
            dbOneCol(<<<'SQL'
                SELECT zarizeni FROM uzivatele_push_subscriptions WHERE id_uzivatele = $0
                SQL, [
                0 => self::ID_UCASTNIKA,
            ]),
            'Uloží se jen krátký popis zařízení, ne hlavička User-Agent.',
        );

        $vypnuti = $this->posliJson($relace, [
            'akce'     => 'vypnout',
            'endpoint' => $endpoint,
        ]);
        self::assertSame(200, $vypnuti['kod'], $vypnuti['telo']);
        self::assertSame('vypnuto', json_decode($vypnuti['telo'], true, flags: JSON_THROW_ON_ERROR)['stav']);
        self::assertSame(0, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_push_subscriptions
            SQL));
    }

    public function testIdUzivateleVTelePozadavkuNicNeznamena(): void
    {
        $this->nastavZapnuti(true);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        // bez trvalého přihlášení nejde zapnout nikomu, ani když tělo tvrdí, že jde o jiného uživatele
        $odpoved = $this->posliJson($relace, [
            'akce'         => 'zapnout',
            'idUzivatele'  => \Uzivatel::SYSTEM,
            'id_uzivatele' => \Uzivatel::SYSTEM,
            'subscription' => [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
                'keys'     => [
                    'p256dh' => 'B' . str_repeat('A', 86),
                    'auth'   => str_repeat('A', 22),
                ],
            ],
        ]);

        self::assertSame(409, $odpoved['kod'], $odpoved['telo']);
        self::assertSame(0, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_push_subscriptions
            SQL));
    }

    public function testStrankyVAdminuJenSPravem(): void
    {
        $this->nastavZapnuti(true);
        $ucastnik = $this->prihlas(self::LOGIN_UCASTNIKA);
        $spravce = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        foreach (['web/push-notifikace', 'web/push-notifikace-test'] as $stranka) {
            $proUcastnika = $this->otevri($ucastnik, $stranka);
            self::assertStringNotContainsString('Příjemce', $proUcastnika['telo'], "Účastník bez práva stránku {$stranka} vidět nesmí.");

            $proSpravce = $this->stahni($spravce, $stranka);
            self::assertStringContainsString('Příjemce', $proSpravce);
            self::assertStringContainsString('neznamená „zobrazeno“', $proSpravce);
        }
        self::assertStringContainsString('(test)', $this->stahni($spravce, 'web/push-notifikace-test'));
    }

    public function testFormularAdminuBezPravaNicNeodesle(): void
    {
        $this->nastavZapnuti(true);
        $ucastnik = $this->prihlas(self::LOGIN_UCASTNIKA);

        $this->odesli($ucastnik, 'web/push-notifikace', [
            'pushNotifikaceAkce' => 'rucni',
            'idPrijemce'         => self::ID_UCASTNIKA,
            'nadpis'             => 'Nadpis',
            'text'               => 'Text',
        ]);

        self::assertSame(0, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM push_notifikace_odeslani
            SQL));
    }

    private function nastavZapnuti(bool $zapnuto): void
    {
        dbQuery(<<<'SQL'
            UPDATE systemove_nastaveni SET hodnota = $0 WHERE klic = 'PUSH_NOTIFIKACE_ZAPNUTE'
            SQL, [
            0 => $zapnuto ? '1' : '0',
        ]);
    }

    /**
     * POST na API webu tak, jak ho posílá skript stránky: JSON a Referer ze stránky webu.
     *
     * @param array<string, mixed> $telo
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function posliJson(
        string $cookieFile,
        array $telo,
        ?string $referer = 'http://localhost/web/registrace',
        string $typObsahu = 'application/json',
        ?string $cookie = null,
    ): array {
        $curl = $this->curlNaAdresu($cookieFile, self::API);
        if ($cookie !== null) {
            // k cookies testovací databáze, které nastavuje curlNaAdresu()
            curl_setopt($curl, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1; ' . $cookie);
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($telo, JSON_THROW_ON_ERROR));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: ' . $typObsahu]);
        if ($referer !== null) {
            curl_setopt($curl, CURLOPT_REFERER, $referer);
        }

        return $this->proved($curl);
    }
}
