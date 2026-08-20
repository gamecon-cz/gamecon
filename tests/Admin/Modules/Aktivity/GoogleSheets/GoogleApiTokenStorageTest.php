<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Tests\Db\AbstractTestDb;

class GoogleApiTokenStorageTest extends AbstractTestDb
{
    private const GOOGLE_CLIENT_ID = 'test-google-client-id.apps.googleusercontent.com';

    public function testOdebraniPristupuSmazeTokenyPouzePrihlasenehoUzivatele()
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

        $tokenStorage->deleteTokensFor($prihlasenyUzivatelId);

        self::assertFalse(
            $tokenStorage->hasTokensFor($prihlasenyUzivatelId),
            'Tokeny odhlašovaného uživatele měly být smazané',
        );
        self::assertTrue(
            $tokenStorage->hasTokensFor($jinyUzivatelId),
            'Tokeny ostatních uživatelů měly zůstat zachované',
        );
    }

    public function testTokenyJsouOddeleneProKazdyGoogleClientId()
    {
        $uzivatelId = $this->vytvorUzivatele();

        $tokenStorage = new GoogleApiTokenStorage(self::GOOGLE_CLIENT_ID);
        $jinyClientStorage = new GoogleApiTokenStorage('jiny-client-id.apps.googleusercontent.com');

        $tokenStorage->setTokensFor([
            'access_token' => 'puvodni',
        ], $uzivatelId);
        $jinyClientStorage->setTokensFor([
            'access_token' => 'novy',
        ], $uzivatelId);

        $tokenStorage->deleteTokensFor($uzivatelId);

        self::assertFalse($tokenStorage->hasTokensFor($uzivatelId));
        self::assertTrue(
            $jinyClientStorage->hasTokensFor($uzivatelId),
            'Smazání tokenů jednoho Google klienta nesmí zasáhnout tokeny jiného',
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
