<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\PushSubscriptions;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\AnonymizovanyUzivatel;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Subscription je řádek na prohlížeč, patří k trvalému přihlášení zařízení a k prostředí, kde vznikla.
 */
class PushSubscriptionsTest extends AbstractTestDb
{
    use PushNotifikaceTestTrait;

    public function testOpakovanaRegistraceStejnehoZarizeniNechaJedenRadek(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $prihlaseni = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        $subscription = self::subscriptionProhlizece();
        $overena = [
            'endpoint' => $subscription['endpoint'],
            'p256dh'   => $subscription['keys']['p256dh'],
            'auth'     => $subscription['keys']['auth'],
        ];

        self::subscriptions()->uloz($idUzivatele, $prihlaseni['id'], $overena, 'Chrome, Android');
        // prohlížeč mezitím vyměnil klíče téže subscription
        $noveKlice = self::subscriptionProhlizece($subscription['endpoint']);
        self::subscriptions()->uloz(
            $idUzivatele,
            $prihlaseni['id'],
            [
                'endpoint' => $subscription['endpoint'],
                'p256dh'   => $noveKlice['keys']['p256dh'],
                'auth'     => $noveKlice['keys']['auth'],
            ],
            'Firefox, Linux',
        );

        $zarizeni = self::subscriptions()->getAktivniUzivatele($idUzivatele);
        self::assertCount(1, $zarizeni);
        self::assertSame($noveKlice['keys']['p256dh'], $zarizeni[0]->p256dh);
        self::assertSame('Firefox, Linux', $zarizeni[0]->zarizeni);
        self::assertSame('fcm.googleapis.com', $zarizeni[0]->getPushSluzba());
    }

    public function testJedenUzivatelMuzeMitVicZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();

        $this->zapniNaNovemZarizeni($idUzivatele);
        $this->zapniNaNovemZarizeni($idUzivatele);

        self::assertCount(2, self::subscriptions()->getAktivniUzivatele($idUzivatele));
    }

    public function testJinyUzivatelNaStejnemZarizeniPrevezmeSubscription(): void
    {
        $idPredchoziho = $this->vytvorUzivatelePush();
        $idNoveho = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPredchoziho);

        $this->zapniNaNovemZarizeni($idNoveho, endpoint: $endpoint);

        self::assertSame([], self::subscriptions()->getAktivniUzivatele($idPredchoziho), 'Předchozímu uživateli zařízení už nesmí nic chodit.');
        self::assertCount(1, self::subscriptions()->getAktivniUzivatele($idNoveho));
    }

    public function testVypnutiSmazeJenVlastniZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $idJineho = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idUzivatele);
        $endpointJineho = $this->zapniNaNovemZarizeni($idJineho);

        self::subscriptions()->smazUzivateli($idUzivatele, $endpointJineho);
        self::assertSame(1, $this->getPocetSubscriptions($idJineho), 'Cizí zařízení nejde vypnout ani se znalostí endpointu.');

        self::subscriptions()->smazUzivateli($idUzivatele, $endpoint);
        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
    }

    /**
     * Na tomhle stojí odhlášení bez JavaScriptu: kdo smaže trvalé přihlášení zařízení (odhlášení, odhlášení
     * ostatních zařízení, změna a obnova hesla, sloučení účtů), smaže s ním i jeho notifikace.
     */
    public function testZruseniTrvalehoPrihlaseniSmazeSubscription(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $idJineho = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idUzivatele);
        $this->zapniNaNovemZarizeni($idUzivatele);
        $this->zapniNaNovemZarizeni($idJineho);

        TrvalePrihlaseni::zrusVsude($idUzivatele);

        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
        self::assertSame(1, $this->getPocetSubscriptions($idJineho));
    }

    public function testZruseniJednohoTrvalehoPrihlaseniNechaOstatniZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $prvni = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        $druhe = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        foreach ([$prvni, $druhe] as $prihlaseni) {
            $subscription = self::subscriptionProhlizece();
            self::subscriptions()->uloz(
                $idUzivatele,
                $prihlaseni['id'],
                [
                    'endpoint' => $subscription['endpoint'],
                    'p256dh'   => $subscription['keys']['p256dh'],
                    'auth'     => $subscription['keys']['auth'],
                ],
                'Chrome, Android',
            );
        }

        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni WHERE id = $0
            SQL, [
            0 => $prvni['id'],
        ]);

        $zbyla = self::subscriptions()->getAktivniUzivatele($idUzivatele);
        self::assertCount(1, $zbyla);
        self::assertSame($druhe['id'], $zbyla[0]->idTrvalehoPrihlaseni);
    }

    /**
     * Beta a preview běží nad kopií ostré databáze. Řádky z ostré v ní jsou, ale poslat se na ně nesmí.
     */
    public function testProstrediVidiJenSvojeSubscriptions(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $ostra = new PushSubscriptions('klic-ostre', 'https://gamecon.cz');
        $endpointOstre = $this->zapniNaNovemZarizeni($idUzivatele, $ostra);
        $prihlaseniOstre = (int) dbOneCol(<<<'SQL'
            SELECT id_trvaleho_prihlaseni FROM uzivatele_push_subscriptions WHERE endpoint = $0
            SQL, [
            0 => $endpointOstre,
        ]);

        $betaSJinymKlicem = new PushSubscriptions('klic-bety', 'https://beta.gamecon.cz');
        $betaSeStejnymKlicem = new PushSubscriptions('klic-ostre', 'https://beta.gamecon.cz');
        $jinyKlicNaStejneAdrese = new PushSubscriptions('klic-bety', 'https://gamecon.cz');

        self::assertCount(1, $ostra->getAktivniUzivatele($idUzivatele));
        self::assertSame([], $betaSJinymKlicem->getAktivniUzivatele($idUzivatele));
        self::assertSame(
            [],
            $betaSeStejnymKlicem->getAktivniUzivatele($idUzivatele),
            'I kdyby beta omylem dostala klíče ostré, adresa webu u subscription nesedí.',
        );
        self::assertSame([], $jinyKlicNaStejneAdrese->getAktivniUzivatele($idUzivatele));
        self::assertTrue($ostra->isAktivni($endpointOstre, $idUzivatele, $prihlaseniOstre));
        self::assertFalse($betaSJinymKlicem->isAktivni($endpointOstre, $idUzivatele, $prihlaseniOstre));
    }

    public function testAktivniJeJenProSvehoUzivateleASvePrihlaseni(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $idJineho = $this->vytvorUzivatelePush();
        $prihlaseni = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        $jinePrihlaseni = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        $subscription = self::subscriptionProhlizece();
        self::subscriptions()->uloz(
            $idUzivatele,
            $prihlaseni['id'],
            [
                'endpoint' => $subscription['endpoint'],
                'p256dh'   => $subscription['keys']['p256dh'],
                'auth'     => $subscription['keys']['auth'],
            ],
            'Chrome, Android',
        );

        self::assertTrue(self::subscriptions()->isAktivni($subscription['endpoint'], $idUzivatele, $prihlaseni['id']));
        self::assertFalse(self::subscriptions()->isAktivni($subscription['endpoint'], $idJineho, $prihlaseni['id']));
        self::assertFalse(self::subscriptions()->isAktivni($subscription['endpoint'], $idUzivatele, $jinePrihlaseni['id']));
        self::assertFalse(self::subscriptions()->isAktivni('https://fcm.googleapis.com/fcm/send/jiny', $idUzivatele, $prihlaseni['id']));
    }

    public function testUzivatelNezaplniTabulku(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        for ($i = 0; $i < PushSubscriptions::MAX_ZARIZENI_UZIVATELE; ++$i) {
            $endpoint = $this->zapniNaNovemZarizeni($idUzivatele);
        }
        // zařízení, které už notifikace má, je smí zapnout znovu
        $this->zapniNaNovemZarizeni($idUzivatele, endpoint: $endpoint);

        $this->expectException(ChybaPushNotifikace::class);
        $this->zapniNaNovemZarizeni($idUzivatele);
    }

    /**
     * Trvalé přihlášení nepoužité přes rok už nikoho nepřihlásí, jeho řádek ale zmizí až s úklidem při něčím dalším
     * přihlášení. Notifikace mu nesmí chodit ani do té doby.
     */
    public function testVyprseleTrvalePrihlaseniUzNotifikaceNedostava(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $pouzivane = $this->zapniNaNovemZarizeni($idUzivatele);
        $zapomenute = $this->zapniNaNovemZarizeni($idUzivatele);
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni
            JOIN uzivatele_push_subscriptions
                ON uzivatele_push_subscriptions.id_trvaleho_prihlaseni = uzivatele_trvala_prihlaseni.id
            SET uzivatele_trvala_prihlaseni.naposledy_pouzito = NOW() - INTERVAL $0 DAY
            WHERE uzivatele_push_subscriptions.endpoint = $1
            SQL, [
            0 => TrvalePrihlaseni::PLATNOST_DNU + 1,
            1 => $zapomenute,
        ]);

        $zarizeni = self::subscriptions()->getAktivniUzivatele($idUzivatele);

        self::assertCount(1, $zarizeni);
        self::assertSame($pouzivane, $zarizeni[0]->endpoint);
    }

    /**
     * Mezi ověřením přihlášení a zápisem může trvalé přihlášení zrušit jiné zařízení. Chyba databáze nese celý dotaz
     * i s endpointem a klíči a aplikace by ji uložila mezi zachycené výjimky; ven smí jen chyba bez nich.
     */
    public function testChybaDatabazeNevyneseEndpointAniKlice(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $prihlaseni = $this->zalozTrvalePrihlaseniPush($idUzivatele);
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni WHERE id = $0
            SQL, [
            0 => $prihlaseni['id'],
        ]);
        $subscription = self::subscriptionProhlizece();

        try {
            self::subscriptions()->uloz(
                $idUzivatele,
                $prihlaseni['id'],
                [
                    'endpoint' => $subscription['endpoint'],
                    'p256dh'   => $subscription['keys']['p256dh'],
                    'auth'     => $subscription['keys']['auth'],
                ],
                'Chrome, Android',
            );
            self::fail('Subscription bez trvalého přihlášení se uložit nesmí.');
        } catch (\Throwable $chyba) {
            self::assertInstanceOf(ChybaPushNotifikace::class, $chyba);
            self::assertSame(409, $chyba->getHttpKod());
            self::assertNull($chyba->getPrevious(), 'Původní výjimka nese text dotazu.');
            // argumenty volání v trace: tak by chybu uložil Vyjimkovač
            $vseOChybe = $chyba->getMessage() . print_r($chyba->getTrace(), true);
            foreach ([$subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth']] as $tajne) {
                self::assertStringNotContainsString($tajne, $vseOChybe);
            }
        }
        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
    }

    public function testAnonymizaceUctuSmazeJehoZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $idJineho = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idUzivatele);
        $this->zapniNaNovemZarizeni($idJineho);

        AnonymizovanyUzivatel::anonymizujUzivatele(\Uzivatel::zIdUrcite($idUzivatele));

        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
        self::assertSame(1, $this->getPocetSubscriptions($idJineho));
    }

    public function testZaznamPredaniAChyb(): void
    {
        $idUzivatele = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idUzivatele);
        $id = self::subscriptions()->getAktivniUzivatele($idUzivatele)[0]->id;

        self::subscriptions()->zaznamenejChybu($id);
        self::subscriptions()->zaznamenejChybu($id);
        $poChybach = self::subscriptions()->getAktivniUzivatele($idUzivatele)[0];
        self::assertSame(2, $poChybach->pocetChybVRade);
        self::assertNull($poChybach->naposledyPredano);

        self::subscriptions()->zaznamenejPredani($id);
        $poPredani = self::subscriptions()->getAktivniUzivatele($idUzivatele)[0];
        self::assertSame(0, $poPredani->pocetChybVRade, 'Předání počítadlo chyb v řadě vynuluje.');
        self::assertNotNull($poPredani->naposledyPredano);
    }
}
