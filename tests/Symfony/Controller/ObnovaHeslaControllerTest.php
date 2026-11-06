<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony\Controller;

use App\Controller\ObnovaHeslaController;
use Gamecon\Kanaly\MailLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\ObnovaHeslaLog;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

/**
 * Kolik mailů s odkazem na obnovu hesla odejde a co se o žádostech zapíše.
 */
class ObnovaHeslaControllerTest extends AbstractTestDb
{
    private const string IP = '198.51.100.7';

    public function testUcetDostaneNejvysLimitMailuDalsiZadostiSeJenZapisou(): void
    {
        $email = $this->novyEmail();
        $idUzivatele = $this->vytvorUzivateleSHeslem($email);

        for ($i = 0; $i < ObnovaHeslaLog::MAX_MAILU_NA_UCET + 2; ++$i) {
            self::assertTrue(
                $this->vyridZadost($email, self::IP),
                'Limit účtu se navenek neprojeví, jinak by prozradil, že k e-mailu účet je',
            );
        }

        self::assertSame(ObnovaHeslaLog::MAX_MAILU_NA_UCET, (new MailLogger(LOGY . '/maily.sqlite'))->spocitej($email));
        $zadosti = $this->zadostiZRozsahu(self::IP);
        self::assertSame([...array_fill(0, ObnovaHeslaLog::MAX_MAILU_NA_UCET, 1), 0, 0], array_map(static fn (array $zadost): int => (int) $zadost['odeslano'], $zadosti));
        self::assertSame(
            array_fill(0, ObnovaHeslaLog::MAX_MAILU_NA_UCET + 2, $idUzivatele),
            array_map(static fn (array $zadost): int => (int) $zadost['id_uzivatele'], $zadosti),
        );
    }

    public function testZadostONeznamyEmailSeZapiseBezUctu(): void
    {
        self::assertTrue($this->vyridZadost($this->novyEmail(), self::IP));

        $zadosti = $this->zadostiZRozsahu(self::IP);
        self::assertCount(1, $zadosti);
        self::assertNull($zadosti[0]['id_uzivatele']);
        self::assertSame(0, (int) $zadosti[0]['odeslano']);
    }

    public function testZadostPoKtereMailNeodeselLimitUctuNezabira(): void
    {
        $email = $this->novyEmail();
        // Rychloregistrace heslo nenastaví a účet bez hesla odkaz nedostane.
        $idUzivatele = (int) \Uzivatel::rychloregistrace(
            SystemoveNastaveni::zGlobals(),
            [
                Sql::EMAIL1_UZIVATELE => $email,
            ],
        );

        self::assertTrue($this->vyridZadost($email, self::IP));

        self::assertSame(0, (new MailLogger(LOGY . '/maily.sqlite'))->spocitej($email));
        $zadosti = $this->zadostiZRozsahu(self::IP);
        self::assertCount(1, $zadosti);
        self::assertSame($idUzivatele, (int) $zadosti[0]['id_uzivatele']);
        self::assertSame(0, (int) $zadosti[0]['odeslano'], 'Zapsaná žádost počítala s mailem, ten ale neodešel');
    }

    public function testPoLimituIpZadostOdmitneANicNeposle(): void
    {
        $obnovaHeslaLog = new ObnovaHeslaLog();
        for ($i = 0; $i < ObnovaHeslaLog::MAX_ZADOSTI_Z_IP; ++$i) {
            $obnovaHeslaLog->zaznamenej(self::IP, null, false);
        }
        $email = $this->novyEmail();
        $this->vytvorUzivateleSHeslem($email);

        self::assertFalse($this->vyridZadost($email, self::IP));

        self::assertSame(0, (new MailLogger(LOGY . '/maily.sqlite'))->spocitej($email));
        self::assertCount(
            ObnovaHeslaLog::MAX_ZADOSTI_Z_IP,
            $this->zadostiZRozsahu(self::IP),
            'Odmítnutá žádost v logu nezůstane, vytrvalý bot by jinak plnil tabulku bez omezení',
        );
    }

    public function testLimitIpPlatiNaCelouSitIpv6(): void
    {
        // Klient, který si adresu uvnitř své sítě /64 mění s každou žádostí.
        for ($i = 1; $i <= ObnovaHeslaLog::MAX_ZADOSTI_Z_IP; ++$i) {
            self::assertTrue($this->vyridZadost($this->novyEmail(), sprintf('2001:db8:5:6::%x', $i)));
        }
        $email = $this->novyEmail();
        $this->vytvorUzivateleSHeslem($email);

        self::assertFalse(
            $this->vyridZadost($email, '2001:db8:5:6:aaaa:bbbb:cccc:dddd'),
            'Změnou adresy uvnitř své sítě /64 limit IP obejít nejde',
        );
        self::assertSame(0, (new MailLogger(LOGY . '/maily.sqlite'))->spocitej($email));
        self::assertCount(
            ObnovaHeslaLog::MAX_ZADOSTI_Z_IP,
            $this->zadostiZRozsahu('2001:db8:5:6::'),
            'Odmítnutá žádost v logu nezůstane ani u IPv6',
        );
        self::assertTrue(
            $this->vyridZadost($this->novyEmail(), '2001:db8:5:7::1'),
            'Sousední síť /64 má svůj limit',
        );
    }

    private function vyridZadost(string $email, string $ip): bool
    {
        // Akce zadost() kolem toho ověřuje Altchu a vykresluje stránku webu, rozhoduje tahle metoda.
        $controller = (new \ReflectionClass(ObnovaHeslaController::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod(ObnovaHeslaController::class, 'vyridZadost'))->invoke($controller, $email, $ip);
    }

    private function novyEmail(): string
    {
        return 'obnova.hesla.' . uniqid('', true) . '@example.test';
    }

    private function vytvorUzivateleSHeslem(string $email): int
    {
        $id = (int) \Uzivatel::rychloregistrace(
            SystemoveNastaveni::zGlobals(),
            [
                Sql::EMAIL1_UZIVATELE => $email,
            ],
        );
        \Uzivatel::zIdUrcite($id)->heslo('zapomenute-heslo');

        return $id;
    }

    /**
     * @param string $ipRozsah u IPv4 adresa sama, u IPv6 síť /64 (2001:db8:1:2::)
     *
     * @return array<int, array<string, mixed>>
     */
    private function zadostiZRozsahu(string $ipRozsah): array
    {
        return dbFetchAll(<<<'SQL'
SELECT id_uzivatele, odeslano
FROM obnova_hesla_log
WHERE ip_rozsah = $0
ORDER BY id_log
SQL,
            [
                0 => $ipRozsah,
            ],
        );
    }
}
