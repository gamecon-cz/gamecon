<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\AktivitaTym;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Rozpuštění týmu maže přihlášky členů přímo (AktivitaTymService::smazat()) přes Doctrine spojení,
 * mimo Aktivita::odhlas(). Rezervace kostýmu musí zmizet i tak.
 */
class PujcovnaKostymuRozpusteniTymuTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    protected static bool $disableStrictTransTables = true;

    // Doctrine spojení musí vidět data vložená přes legacy spojení, takže bez transakcí testu.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public function testRozpustenimTymuZmiziRezervaceClena(): void
    {
        $kapitan = self::prihlasenyUzivatel();
        $clen = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        dbInsert('akce_tym', [
            'kod'        => 1234,
            'nazev'      => 'Kostýmovaní',
            'id_kapitan' => $kapitan->id(),
        ]);
        $idTymu = (int) dbInsertId();
        dbInsert('akce_tym_akce', [
            'id_tymu' => $idTymu,
            'id_akce' => $idAktivity,
        ]);
        foreach ([$kapitan, $clen] as $hrac) {
            dbInsert('akce_tym_prihlaseni', [
                'id_tymu'      => $idTymu,
                'id_uzivatele' => $hrac->id(),
            ]);
            self::vlozPrihlasku($idAktivity, $hrac->id());
        }
        self::vlozRezervaci(self::vlozKostym(), $idAktivity, $clen->id());
        SystemoveNastaveni::zGlobals()->kernel()->getContainer()->get('doctrine.orm.entity_manager')->clear();

        AktivitaTym::najdi($idTymu)->smazat();

        self::assertSame('0', (string) dbOneCol('SELECT COUNT(*) FROM akce_prihlaseni WHERE id_akce = $0', [$idAktivity]));
        self::assertSame([], self::rezervaceAktivity($idAktivity));
    }
}
