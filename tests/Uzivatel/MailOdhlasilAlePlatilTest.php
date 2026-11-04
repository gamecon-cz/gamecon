<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;

class MailOdhlasilAlePlatilTest extends AbstractTestDb
{
    public function testCastkaMaHalereAOddelenyTisice(): void
    {
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'    => 'odhlasil_ale_platil',
            'jmeno_uzivatele'    => 'Odhlásil',
            'prijmeni_uzivatele' => 'Zaplatil',
            'email1_uzivatele'   => 'odhlasil.ale.platil@test.cz',
        ]);
        $uzivatel = \Uzivatel::zId((int) dbInsertId());
        $odhlasujici = \Uzivatel::zId(\Uzivatel::SYSTEM, true);

        $vytvorMail = new \ReflectionMethod($uzivatel, 'mailOdhlasilAlePlatil');
        $mail = $vytvorMail->invoke($uzivatel, 1250.9, $odhlasujici);

        self::assertStringContainsString("poslal 1\u{A0}250,90 Kč", $mail->dejText());
    }
}
