<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\KontextZarizeni;
use Gamecon\PushNotifikace\SpravaZarizeni;
use Gamecon\PushNotifikace\StavZarizeni;
use Gamecon\PushNotifikace\ZaznamOdeslani;
use Gamecon\Tests\Uzivatel\AbstractPrihlaseniNaZarizenichTestDb;
use Gamecon\Uzivatel\PrepnutiNaUzivatele;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Zapnutí, vypnutí a stav notifikací na zařízení: kdo se ptá, říká session a cookie trvalého přihlášení.
 * Zařízení je tu jako v testech přihlášení session a cookie, se kterými prohlížeč přijde.
 */
class SpravaZarizeniTest extends AbstractPrihlaseniNaZarizenichTestDb
{
    use PushNotifikaceTestTrait;

    private string $adresarLogu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adresarLogu = LOGY . '/sprava-zarizeni-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresarLogu);
        parent::tearDown();
    }

    public function testPrihlasenyTrvaleSiNotifikaceZapneAVypne(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $subscription = self::subscriptionProhlizece();

        $kontext = $this->kontextNa($telefon);
        self::assertFalse($kontext->prepnuto);
        self::assertNotNull($kontext->idTrvalehoPrihlaseni);
        self::assertSame(
            [
                'stav'              => StavZarizeni::VYPNUTO,
                'zrusitVProhlizeci' => false,
            ],
            $this->sprava()->zjistiStav($kontext, null),
        );

        $this->sprava()->zapni($kontext, $subscription, 'Mozilla/5.0 (Linux; Android 14) Chrome/131.0 Mobile Safari/537.36');
        self::assertSame(
            [
                'stav'              => StavZarizeni::ZAPNUTO,
                'zrusitVProhlizeci' => false,
            ],
            $this->sprava()->zjistiStav($this->kontextNa($telefon), $subscription['endpoint']),
        );
        self::assertSame('Chrome, Android', self::subscriptions()->getAktivniUzivatele($idUzivatele)[0]->zarizeni);

        $this->sprava()->vypni($this->kontextNa($telefon), $subscription['endpoint']);
        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
    }

    public function testBezTrvalehoPrihlaseniNotifikaceZapnoutNejdou(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $pocitac = $this->zarizeni();
        $login = $this->getLogin($idUzivatele);
        $this->pozadavek($pocitac, static fn () => \Uzivatel::prihlas($login, self::HESLO));

        $kontext = $this->kontextNa($pocitac);
        self::assertNull($kontext->idTrvalehoPrihlaseni);
        self::assertSame(StavZarizeni::BEZ_TRVALEHO_PRIHLASENI, $this->sprava()->zjistiStav($kontext, null)['stav']);

        try {
            $this->sprava()->zapni($kontext, self::subscriptionProhlizece(), '');
            self::fail('Bez trvalého přihlášení se notifikace zapnout nesmí.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(409, $chyba->getHttpKod());
        }
        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
    }

    /**
     * Admin přihlášený jako jiný uživatel sedí u svého zařízení: notifikace cizího účtu na něm nezapne,
     * a jeho vlastní subscription v prohlížeči zůstane, jak byla.
     */
    public function testPrepnutyAdminNezapneNotifikaceCizihoUctu(): void
    {
        $idAdmina = $this->vytvorUzivatele();
        $idUcastnika = $this->vytvorUzivatele();
        $pocitacAdmina = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idAdmina)['cookie']);
        $subscriptionAdmina = self::subscriptionProhlizece();
        $this->sprava()->zapni($this->kontextNa($pocitacAdmina), $subscriptionAdmina, '');
        $this->pozadavek($pocitacAdmina, static fn () => PrepnutiNaUzivatele::zSession()->prepni(
            \Uzivatel::zId($idAdmina),
            \Uzivatel::zId($idUcastnika),
        ));

        $kontext = $this->kontextNa($pocitacAdmina);
        self::assertSame($idUcastnika, $kontext->idUzivatele);
        self::assertTrue($kontext->prepnuto);
        self::assertNull($kontext->idTrvalehoPrihlaseni, 'Cookie admina trvalé přihlášení cizího účtu neurčuje.');

        try {
            $this->sprava()->zapni($kontext, self::subscriptionProhlizece(), '');
            self::fail('Přepnutý admin nesmí zaregistrovat subscription cizího účtu.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(403, $chyba->getHttpKod());
        }
        self::assertSame(0, $this->getPocetSubscriptions($idUcastnika));

        self::assertSame(
            [
                'stav'              => StavZarizeni::PREPNUTO,
                'zrusitVProhlizeci' => false,
            ],
            $this->sprava()->zjistiStav($kontext, $subscriptionAdmina['endpoint']),
        );
        self::assertSame(1, $this->getPocetSubscriptions($idAdmina), 'Na subscription admina kontrola v přepnutém účtu nesáhne.');
    }

    public function testKontextBezPrepnutiSTrvalymPrihlasenimJinehoUzivateleHoNevezme(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $idJineho = $this->vytvorUzivatele();
        // session patří uživateli, cookie v prohlížeči někomu jinému
        $zarizeni = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idJineho)['cookie']);

        $kontext = $this->pozadavek($zarizeni, static fn () => KontextZarizeni::zPrihlaseneho(\Uzivatel::zIdUrcite($idUzivatele)));

        self::assertNull($kontext->idTrvalehoPrihlaseni);
    }

    /**
     * Na zařízení se přihlásil někdo jiný. První stránka pošle endpoint, který prohlížeč nese po předchozím
     * uživateli: server jeho řádek smaže a prohlížeči řekne, ať subscription zruší.
     */
    public function testJinyUzivatelNaZarizeniUkonciNotifikacePredchoziho(): void
    {
        $idPredchoziho = $this->vytvorUzivatele();
        $idNoveho = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idPredchoziho)['cookie']);
        $subscription = self::subscriptionProhlizece();
        $this->sprava()->zapni($this->kontextNa($telefon), $subscription, '');
        // nový uživatel na témže prohlížeči, se svým trvalým přihlášením; řádek předchozího zůstal (nikdo ho neodhlásil)
        $telefonNoveho = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idNoveho)['cookie']);

        $stav = $this->sprava()->zjistiStav($this->kontextNa($telefonNoveho), $subscription['endpoint']);

        self::assertSame([
            'stav'              => StavZarizeni::VYPNUTO,
            'zrusitVProhlizeci' => true,
        ], $stav);
        self::assertSame(0, $this->getPocetSubscriptions($idPredchoziho), 'Notifikace předchozího uživatele na zařízení nesmí dál chodit.');
        self::assertSame(0, $this->getPocetSubscriptions($idNoveho), 'Nový uživatel si je musí zapnout sám, kliknutím.');
    }

    public function testOdhlaseniUkonciNotifikaceZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $tablet = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $subscriptionTelefonu = self::subscriptionProhlizece();
        $subscriptionTabletu = self::subscriptionProhlizece();
        $this->sprava()->zapni($this->kontextNa($telefon), $subscriptionTelefonu, '');
        $this->sprava()->zapni($this->kontextNa($tablet), $subscriptionTabletu, '');

        $this->pozadavek($telefon, static fn () => \Uzivatel::zSession()->odhlas(false));

        $zbyla = self::subscriptions()->getAktivniUzivatele($idUzivatele);
        self::assertCount(1, $zbyla, 'Odhlášení ukončí notifikace jen na tomto zařízení.');
        self::assertSame($subscriptionTabletu['endpoint'], $zbyla[0]->endpoint);
    }

    public function testOdhlaseniOstatnichZarizeniUkonciJejichNotifikace(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $ztracenyTablet = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $subscriptionTelefonu = self::subscriptionProhlizece();
        $this->sprava()->zapni($this->kontextNa($telefon), $subscriptionTelefonu, '');
        $this->sprava()->zapni($this->kontextNa($ztracenyTablet), self::subscriptionProhlizece(), '');

        $this->pozadavek($telefon, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        $zbyla = self::subscriptions()->getAktivniUzivatele($idUzivatele);
        self::assertCount(1, $zbyla, 'Ztracené zařízení přestane dostávat notifikace, i když na něm nikdo nic neudělá.');
        self::assertSame($subscriptionTelefonu['endpoint'], $zbyla[0]->endpoint);
        self::assertSame(
            StavZarizeni::ZAPNUTO,
            $this->sprava()->zjistiStav($this->kontextNa($telefon), $subscriptionTelefonu['endpoint'])['stav'],
            'Toto zařízení zůstává přihlášené i s notifikacemi.',
        );
    }

    public function testEndpointMimoPushSluzbySeOdmitneADoLoguJdeJenHostitel(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $subscription = [
            'endpoint' => 'https://utocnik.example.com/tajna/cesta?token=abc',
        ] + self::subscriptionProhlizece();

        try {
            $this->sprava()->zapni($this->kontextNa($telefon), $subscription, '');
            self::fail('Endpoint mimo známé push služby se nesmí uložit.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(400, $chyba->getHttpKod());
        }

        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
        $log = (string) file_get_contents(glob($this->adresarLogu . '/push-notifikace-*.log')[0]);
        self::assertStringContainsString('odmitnuty-endpoint sluzba=utocnik.example.com', $log);
        self::assertStringNotContainsString('tajna', $log);
        self::assertStringNotContainsString('token', $log);
    }

    public function testVymenaSubscriptionZeServiceWorkeru(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $stara = self::subscriptionProhlizece();
        $nova = self::subscriptionProhlizece();
        $this->sprava()->zapni($this->kontextNa($telefon), $stara, '');

        $this->sprava()->zmen($this->kontextNa($telefon), $stara['endpoint'], $nova, '');

        $zarizeni = self::subscriptions()->getAktivniUzivatele($idUzivatele);
        self::assertCount(1, $zarizeni);
        self::assertSame($nova['endpoint'], $zarizeni[0]->endpoint);
    }

    /**
     * Service worker běží i po odhlášení. Novou subscription smí dát jen místo té, kterou server pro tohoto
     * uživatele a toto přihlášení zná, jinak by notifikace zapnul bez kliknutí uživatele.
     */
    public function testVymenaNeznameSubscriptionNicNezapne(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $idJineho = $this->vytvorUzivatele();
        $telefon = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUzivatele)['cookie']);
        $ciziEndpoint = $this->zapniNaNovemZarizeni($idJineho);

        foreach ([null, 'https://fcm.googleapis.com/fcm/send/neznamy', $ciziEndpoint] as $staryEndpoint) {
            try {
                $this->sprava()->zmen($this->kontextNa($telefon), $staryEndpoint, self::subscriptionProhlizece(), '');
                self::fail('Výměna za subscription, kterou server pro uživatele nezná, nesmí projít.');
            } catch (ChybaPushNotifikace $chyba) {
                self::assertSame(409, $chyba->getHttpKod());
            }
        }
        self::assertSame(0, $this->getPocetSubscriptions($idUzivatele));
        self::assertSame(1, $this->getPocetSubscriptions($idJineho));
    }

    /**
     * Endpoint v požadavku píše klient. Text, který vypadá jako číslo, dává `dbQv()` do dotazu bez uvozovek a MariaDB
     * by pak sloupec porovnala jako číslo; cokoli mimo známé push služby se proto k databázi vůbec nedostane.
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['žluťoučký endpoint'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['https://example.com/push'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["https://fcm.googleapis.com/fcm/send/x' OR '1'='1"])]
    public function testPodvrzenyEndpointNikomuNicNesmaze(string $podvrzenyEndpoint): void
    {
        $idObeti = $this->vytvorUzivatele();
        $idUtocnika = $this->vytvorUzivatele();
        $this->zapniNaNovemZarizeni($idObeti);
        $telefonUtocnika = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idUtocnika)['cookie']);
        $kontext = $this->kontextNa($telefonUtocnika);

        $stav = $this->sprava()->zjistiStav($kontext, $podvrzenyEndpoint);
        $this->sprava()->vypni($kontext, $podvrzenyEndpoint);
        try {
            $this->sprava()->zmen($kontext, $podvrzenyEndpoint, self::subscriptionProhlizece(), '');
            self::fail('Výměna za podvržený endpoint nesmí projít.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(409, $chyba->getHttpKod());
        }

        self::assertSame([
            'stav'              => StavZarizeni::VYPNUTO,
            'zrusitVProhlizeci' => false,
        ], $stav);
        self::assertSame(1, $this->getPocetSubscriptions($idObeti), 'Cizí subscription musí zůstat.');
        self::assertSame(0, $this->getPocetSubscriptions($idUtocnika));
    }

    public function testPrepnutyAdminNevypneAniNevymeniNotifikaceCizihoUctu(): void
    {
        $idAdmina = $this->vytvorUzivatele();
        $idUcastnika = $this->vytvorUzivatele();
        $endpointUcastnika = $this->zapniNaNovemZarizeni($idUcastnika);
        $pocitacAdmina = $this->zarizeni(cookie: $this->zalozTrvalePrihlaseniPush($idAdmina)['cookie']);
        $this->idPrihlasenehoNa($pocitacAdmina);
        $this->pozadavek($pocitacAdmina, static fn () => PrepnutiNaUzivatele::zSession()->prepni(
            \Uzivatel::zId($idAdmina),
            \Uzivatel::zId($idUcastnika),
        ));
        $kontext = $this->kontextNa($pocitacAdmina);

        foreach ([
            fn () => $this->sprava()->vypni($kontext, $endpointUcastnika),
            fn () => $this->sprava()->zmen($kontext, $endpointUcastnika, self::subscriptionProhlizece(), ''),
        ] as $akce) {
            try {
                $akce();
                self::fail('Přepnutý admin nesmí měnit notifikace cizího účtu.');
            } catch (ChybaPushNotifikace $chyba) {
                self::assertSame(403, $chyba->getHttpKod());
            }
        }
        self::assertSame(1, $this->getPocetSubscriptions($idUcastnika));
    }

    private function sprava(): SpravaZarizeni
    {
        return new SpravaZarizeni(self::subscriptions(), new ZaznamOdeslani(new MockClock(), $this->adresarLogu));
    }

    /**
     * Kontext požadavku ze zařízení tak, jak ho složí API: přihlášený ze session nebo cookie, trvalé přihlášení
     * z cookie.
     *
     * @param array{session: array<mixed>, cookie: string|null} $zarizeni
     */
    private function kontextNa(array &$zarizeni): KontextZarizeni
    {
        return $this->pozadavek($zarizeni, static fn () => KontextZarizeni::zPrihlaseneho(
            \Uzivatel::zSession() ?? throw new \LogicException('Na zařízení není nikdo přihlášený.'),
        ));
    }
}
