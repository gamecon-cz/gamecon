<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\PushOdesilac;
use Gamecon\PushNotifikace\PushSubscriptions;
use Gamecon\PushNotifikace\StavOdeslani;
use Gamecon\PushNotifikace\TypNotifikace;
use Gamecon\PushNotifikace\Urgence;
use Gamecon\PushNotifikace\ZaznamOdeslani;
use Gamecon\PushNotifikace\ZpravaNotifikace;
use Gamecon\Tests\Db\AbstractTestDb;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Odeslání přes knihovnu web-push s falešnou push službou: žádný požadavek neopustí proces.
 */
class PushOdesilacTest extends AbstractTestDb
{
    use PushNotifikaceTestTrait;

    private const string TEXT = 'Tajný text notifikace, který nesmí do logu.';

    private FalesnyHttpKlient $pushSluzba;

    private string $adresarLogu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pushSluzba = new FalesnyHttpKlient();
        $this->adresarLogu = LOGY . '/push-odesilac-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresarLogu);
        parent::tearDown();
    }

    public function testPredanoPushSluzbe(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPrijemce);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertCount(1, $vysledky);
        self::assertSame(StavOdeslani::PREDANO, $vysledky[0]->stav);
        self::assertSame(201, $vysledky[0]->httpKod);
        self::assertSame('Chrome, Android', $vysledky[0]->zarizeni);
        self::assertSame('fcm.googleapis.com', $vysledky[0]->pushSluzba);
        self::assertSame([$endpoint], $this->pushSluzba->getEndpointy());
        self::assertNotNull(self::subscriptions()->getAktivniUzivatele($idPrijemce)[0]->naposledyPredano);
    }

    public function testPozadavekNeseDoruceniAZasifrovanyObsah(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);

        $this->odesilac()->posliUzivateli($idPrijemce, ZpravaNotifikace::vytvor(
            TypNotifikace::PRIPOMINKA_AKTIVITY,
            'Nadpis',
            self::TEXT,
            'program/muj',
        ));

        $pozadavek = $this->pushSluzba->pozadavky[0];
        self::assertSame('POST', $pozadavek->getMethod());
        self::assertSame((string) (15 * 60), $pozadavek->getHeaderLine('TTL'));
        self::assertSame(Urgence::VYSOKA->value, $pozadavek->getHeaderLine('Urgency'));
        self::assertSame('aktivita', $pozadavek->getHeaderLine('Topic'));
        self::assertSame('aes128gcm', $pozadavek->getHeaderLine('Content-Encoding'));
        self::assertStringStartsWith('vapid ', $pozadavek->getHeaderLine('Authorization'), 'Zprávu musí podepsat VAPID klíč prostředí.');
        $telo = (string) $pozadavek->getBody();
        self::assertNotSame('', $telo);
        self::assertStringNotContainsString(self::TEXT, $telo, 'Push služba smí obsah vidět jen zašifrovaný.');
        self::assertStringNotContainsString('program/muj', $telo);
    }

    public function testNotifikaciDostaneJenPrijemceNaVsechSvychZarizenich(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $idJineho = $this->vytvorUzivatelePush();
        $telefon = $this->zapniNaNovemZarizeni($idPrijemce);
        $pocitac = $this->zapniNaNovemZarizeni($idPrijemce, endpoint: 'https://updates.push.services.mozilla.com/wpush/v2/' . bin2hex(random_bytes(8)));
        $this->zapniNaNovemZarizeni($idJineho);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertCount(2, $vysledky);
        self::assertEqualsCanonicalizing([$telefon, $pocitac], $this->pushSluzba->getEndpointy(), 'Nikdo jiný než příjemce notifikaci dostat nesmí.');
    }

    public function testPoZmeneUzivateleNaZarizeniPredchozimuNicNejde(): void
    {
        $idPredchoziho = $this->vytvorUzivatelePush();
        $idNoveho = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPredchoziho);
        $this->zapniNaNovemZarizeni($idNoveho, endpoint: $endpoint);

        self::assertSame([], $this->odesilac()->posliUzivateli($idPredchoziho, self::zprava()));
        self::assertSame([], $this->pushSluzba->pozadavky);
    }

    public function testBezZarizeniSeNicNeposle(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();

        self::assertSame([], $this->odesilac()->posliUzivateli($idPrijemce, self::zprava()));
        self::assertSame([], $this->pushSluzba->pozadavky);
    }

    /**
     * @param int $httpKod odpověď push služby na subscription, kterou uživatel zrušil v prohlížeči
     */
    #[\PHPUnit\Framework\Attributes\TestWith([404])]
    #[\PHPUnit\Framework\Attributes\TestWith([410])]
    public function testNeplatnaSubscriptionSeSmaze(int $httpKod): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $zrusena = $this->zapniNaNovemZarizeni($idPrijemce);
        $platna = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->pushSluzba->odpovezNa($zrusena, $httpKod);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertSame(
            [StavOdeslani::NEPLATNA_SMAZANA, StavOdeslani::PREDANO],
            array_map(static fn ($vysledek) => $vysledek->stav, $vysledky),
        );
        self::assertSame($httpKod, $vysledky[0]->httpKod);
        $zbyla = self::subscriptions()->getAktivniUzivatele($idPrijemce);
        self::assertCount(1, $zbyla);
        self::assertSame($platna, $zbyla[0]->endpoint);
    }

    /**
     * @param int $httpKod chyba, po které subscription dál platí
     */
    #[\PHPUnit\Framework\Attributes\TestWith([400])]
    #[\PHPUnit\Framework\Attributes\TestWith([403])]
    #[\PHPUnit\Framework\Attributes\TestWith([429])]
    #[\PHPUnit\Framework\Attributes\TestWith([500])]
    public function testJinaChybaSubscriptionNecha(int $httpKod): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->pushSluzba->odpovezNa($endpoint, $httpKod);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertSame(StavOdeslani::CHYBA, $vysledky[0]->stav);
        self::assertSame($httpKod, $vysledky[0]->httpKod);
        self::assertNotSame('', $vysledky[0]->duvod);
        $zarizeni = self::subscriptions()->getAktivniUzivatele($idPrijemce);
        self::assertCount(1, $zarizeni);
        self::assertSame(1, $zarizeni[0]->pocetChybVRade);
        self::assertNull($zarizeni[0]->naposledyPredano);
    }

    public function testNedostupnaPushSluzbaJeChybaBezEndpointuVDuvodu(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->pushSluzba->odpovezNa($endpoint, null);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertSame(StavOdeslani::CHYBA, $vysledky[0]->stav);
        self::assertNull($vysledky[0]->httpKod);
        self::assertStringNotContainsString($endpoint, $vysledky[0]->duvod, 'Zpráva výjimky klienta nese celou adresu, do výsledku nesmí.');
        self::assertCount(1, self::subscriptions()->getAktivniUzivatele($idPrijemce));
    }

    /**
     * Pojistka z oddílu o prostředích: preview a beta mají v kopii ostré databáze i subscriptions z ostré,
     * a nesmí se na ně ani pokusit poslat.
     */
    public function testSubscriptionZJinehoProstrediSeNeposila(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce, new PushSubscriptions('klic-ostre', 'https://gamecon.cz'));
        $this->zapniNaNovemZarizeni($idPrijemce, new PushSubscriptions(self::vapidKlice()->verejny, 'https://gamecon.cz'));
        $tady = $this->zapniNaNovemZarizeni($idPrijemce);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertCount(1, $vysledky);
        self::assertSame([$tady], $this->pushSluzba->getEndpointy());
        self::assertSame(3, $this->getPocetSubscriptions($idPrijemce), 'Cizí řádky se nemažou, jen se jim nic neposílá.');
    }

    public function testEndpointMimoSeznamPushSluzebSeNevola(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);
        // řádek, který do tabulky zapsal někdo mimo aplikaci
        $this->zapniNaNovemZarizeni($idPrijemce, endpoint: 'https://169.254.169.254/latest/meta-data');

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertSame(
            [StavOdeslani::PREDANO, StavOdeslani::CHYBA],
            array_map(static fn ($vysledek) => $vysledek->stav, $vysledky),
        );
        self::assertCount(1, $this->pushSluzba->pozadavky, 'Na adresu mimo push služby server nesmí poslat nic.');
        self::assertStringStartsWith('https://fcm.googleapis.com/', $this->pushSluzba->getEndpointy()[0]);
    }

    public function testVadnyKlicJednohoZarizeniNezastaviOstatni(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $vadny = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->zapniNaNovemZarizeni($idPrijemce);
        // 65 bajtů s úvodním 0x04, ale není to bod křivky
        dbQuery(<<<'SQL'
            UPDATE uzivatele_push_subscriptions SET p256dh = $0 WHERE endpoint = $1
            SQL, [
            0 => self::base64Url("\x04" . str_repeat("\x01", 64)),
            1 => $vadny,
        ]);

        $vysledky = $this->odesilac()->posliUzivateli($idPrijemce, self::zprava());

        self::assertSame(
            [StavOdeslani::CHYBA, StavOdeslani::PREDANO],
            array_map(static fn ($vysledek) => $vysledek->stav, $vysledky),
        );
    }

    public function testZaznamNeneseEndpointKliceAniObsah(): void
    {
        $idOdesilatele = $this->vytvorUzivatelePush();
        $idPrijemce = $this->vytvorUzivatelePush();
        $predana = $this->zapniNaNovemZarizeni($idPrijemce);
        $zrusena = $this->zapniNaNovemZarizeni($idPrijemce);
        $nedostupna = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->pushSluzba->odpovezNa($zrusena, 410);
        $this->pushSluzba->odpovezNa($nedostupna, null);
        $klice = dbFetchAll(<<<'SQL'
            SELECT p256dh, auth FROM uzivatele_push_subscriptions WHERE id_uzivatele = $0
            SQL, [
            0 => $idPrijemce,
        ]);

        $this->odesilac()->posliUzivateli(
            $idPrijemce,
            ZpravaNotifikace::vytvor(TypNotifikace::ZRUSENI_AKTIVITY, 'Tajný nadpis', self::TEXT, 'tajny/odkaz'),
            $idOdesilatele,
        );

        $radek = dbOneLine(<<<'SQL'
            SELECT * FROM push_notifikace_odeslani WHERE id_prijemce = $0
            SQL, [
            0 => $idPrijemce,
        ]);
        self::assertSame(
            [
                'id_odesilatele' => $idOdesilatele,
                'typ'            => TypNotifikace::ZRUSENI_AKTIVITY->value,
                'pocet_zarizeni' => 3,
                'pocet_predano'  => 1,
                'pocet_smazano'  => 1,
                'pocet_chyb'     => 1,
            ],
            [
                'id_odesilatele' => (int) $radek['id_odesilatele'],
                'typ'            => $radek['typ'],
                'pocet_zarizeni' => (int) $radek['pocet_zarizeni'],
                'pocet_predano'  => (int) $radek['pocet_predano'],
                'pocet_smazano'  => (int) $radek['pocet_smazano'],
                'pocet_chyb'     => (int) $radek['pocet_chyb'],
            ],
        );

        $soubory = glob($this->adresarLogu . '/push-notifikace-*.log');
        self::assertCount(1, $soubory);
        $log = (string) file_get_contents($soubory[0]);
        self::assertSame(3, substr_count($log, "\n"), 'Řádek logu za každé zařízení.');
        self::assertStringContainsString('prijemce=' . $idPrijemce, $log);
        self::assertStringContainsString('odesilatel=' . $idOdesilatele, $log);
        self::assertStringContainsString('sluzba=fcm.googleapis.com', $log);
        self::assertStringContainsString('stav=neplatna_smazana http=410', $log);
        foreach ([$predana, $zrusena, $nedostupna, 'Tajný nadpis', self::TEXT, 'tajny/odkaz', '/fcm/send/'] as $tajne) {
            self::assertStringNotContainsString($tajne, $log, 'Log nesmí nést endpoint ani obsah notifikace.');
        }
        foreach ($klice as $klicZarizeni) {
            self::assertStringNotContainsString($klicZarizeni['p256dh'], $log);
            self::assertStringNotContainsString($klicZarizeni['auth'], $log);
        }
    }

    private function odesilac(): PushOdesilac
    {
        return new PushOdesilac(
            self::vapidKlice(),
            self::subscriptions(),
            new ZaznamOdeslani(new MockClock(), $this->adresarLogu),
            $this->pushSluzba,
            self::URL_WEBU_TESTU,
        );
    }

    private static function zprava(): ZpravaNotifikace
    {
        return ZpravaNotifikace::vytvor(TypNotifikace::OBECNA, 'Nadpis', self::TEXT, 'program/muj');
    }
}
