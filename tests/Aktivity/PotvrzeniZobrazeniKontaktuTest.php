<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\OnlinePrezence\PotvrzeniZobrazeniKontaktu;
use Gamecon\Logger\LogUdalosti;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

class PotvrzeniZobrazeniKontaktuTest extends AbstractUzivatelTestDb
{
    private const ID_AKTIVITY = 123456;

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['potvrzeneZobrazeniKontaktu']);
    }

    public function testVychoziStavJeZamceno(): void
    {
        self::assertFalse($this->potvrzeni()->jePotvrzeno(self::ID_AKTIVITY));
    }

    public function testPotvrzeniOdemkneJenSvouAktivitu(): void
    {
        $potvrzeni = $this->potvrzeni();
        $potvrzeni->potvrd(self::prihlasenyUzivatel(), self::ID_AKTIVITY);

        self::assertTrue($potvrzeni->jePotvrzeno(self::ID_AKTIVITY));
        self::assertFalse(
            $potvrzeni->jePotvrzeno(self::ID_AKTIVITY + 1),
            'Souhlas u jedné aktivity nesmí odemknout kontakty u jiné',
        );
    }

    public function testPotvrzeniSeZaloguje(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $pocetPred = $this->pocetLogu($uzivatel->id());

        $this->potvrzeni()->potvrd($uzivatel, self::ID_AKTIVITY);

        self::assertSame(
            $pocetPred + 1,
            $this->pocetLogu($uzivatel->id()),
            'Odemčení kontaktů se musí zaznamenat kvůli dohledatelnosti',
        );
    }

    public function testOpakovanePotvrzeniNezaloguDvakrat(): void
    {
        $uzivatel = self::prihlasenyUzivatel();
        $potvrzeni = $this->potvrzeni();

        $potvrzeni->potvrd($uzivatel, self::ID_AKTIVITY);
        $pocetPoPrvnim = $this->pocetLogu($uzivatel->id());
        $potvrzeni->potvrd($uzivatel, self::ID_AKTIVITY);

        self::assertSame(
            $pocetPoPrvnim,
            $this->pocetLogu($uzivatel->id()),
            'Znovunačtení stránky nesmí log zahltit duplicitami',
        );
    }

    private function pocetLogu(int $idUzivatele): int
    {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM log_udalosti WHERE id_logujiciho = $0 AND zprava = $1',
            [$idUzivatele, PotvrzeniZobrazeniKontaktu::ZPRAVA_LOGU],
        );
    }

    private function potvrzeni(): PotvrzeniZobrazeniKontaktu
    {
        return new PotvrzeniZobrazeniKontaktu(new LogUdalosti());
    }
}
