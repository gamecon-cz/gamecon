<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Tests\Db\AbstractTestDb;

class GoogleApiClientTest extends AbstractTestDb
{
    private const GOOGLE_CLIENT_ID = 'test-google-client-id.apps.googleusercontent.com';

    public function testOdebraniPristupuNechaTokenyOstatnichUzivateluNetknute()
    {
        $tokenStorage = new GoogleApiTokenStorage(self::GOOGLE_CLIENT_ID);

        $prihlasenyUzivatelId = $this->vytvorUzivatele();
        $jinyUzivatelId = $this->vytvorUzivatele();

        $tokenStorage->setTokensFor([
            'access_token' => 'prihlaseny',
        ], $prihlasenyUzivatelId);
        $tokenStorage->setTokensFor([
            'access_token' => 'jiny',
        ], $jinyUzivatelId);

        $googleApiClient = new GoogleApiClient(
            new GoogleApiCredentials([
                'web' => [
                    'client_id' => self::GOOGLE_CLIENT_ID,
                ],
            ]),
            $tokenStorage,
            $prihlasenyUzivatelId,
        );

        $googleApiClient->odeberPristup();

        self::assertFalse(
            $tokenStorage->hasTokensFor($prihlasenyUzivatelId),
            'Tokeny odhlašovaného uživatele měly být smazané',
        );
        self::assertTrue(
            $tokenStorage->hasTokensFor($jinyUzivatelId),
            'Odebrání přístupu jednoho uživatele nesmí odhlásit ostatní uživatele',
        );
    }

    private function vytvorUzivatele(): int
    {
        $jednoznacnyNazev = uniqid('google-test-', true);
        dbQuery(
            'INSERT INTO uzivatele_hodnoty (login_uzivatele, email1_uzivatele) VALUES ($1, $2)',
            [$jednoznacnyNazev, $jednoznacnyNazev . '@example.com'],
        );

        return (int) dbInsertId();
    }
}
