<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Structure\Sql\TagSqlStructure as TagSql;
use App\Structure\Sql\UserRoleSqlStructure as UserRoleSql;
use App\Structure\Sql\UserSqlStructure as UserSql;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\KontaktSefaLinie;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura as PrihlaseniSql;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\SqlStruktura\AkceSjednoceneTagySqlStruktura as AktivitaTagSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\Tag;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Pohlavi;

/**
 * Larp se štítkem věku, účastník s věkem k začátku aktivity a hlavička linie (šéf sekce) pro testy upozornění na věk.
 * Třída, která trait používá, má $this->systemoveNastaveni (ProbihaRegistraceAktivitTrait).
 */
trait VekAktivityTestTrait
{
    private const string ZACATEK_AKTIVITY = ROCNIK . '-07-16 10:00:00';

    private function vytvorAktivitu(string ...$stitkyVeku): Aktivita
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE => 'Larp pro starší ' . self::unikatniCislo(),
            AktivitaSql::TYP        => TypAktivity::LARP,
            AktivitaSql::ROK        => ROCNIK,
            AktivitaSql::STAV       => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK    => self::ZACATEK_AKTIVITY,
            AktivitaSql::KONEC      => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA   => 5,
            AktivitaSql::KAPACITA_M => 0,
            AktivitaSql::KAPACITA_F => 0,
            AktivitaSql::CENA       => 0,
            AktivitaSql::TEAMOVA    => 0,
        ]);
        $idAktivity = (int) dbInsertId();
        foreach ($stitkyVeku as $stitekVeku) {
            $this->pridejStitekVekuAktiviteSId($idAktivity, $stitekVeku);
        }

        return Aktivita::zId($idAktivity, false, $this->systemoveNastaveni);
    }

    /**
     * Načtená aktivita má štítky v paměti, nový uvidí až znovu načtená (Aktivita::zId()).
     */
    private function pridejStitekVeku(Aktivita $aktivita, string $stitekVeku): void
    {
        $this->pridejStitekVekuAktiviteSId((int) $aktivita->id(), $stitekVeku);
    }

    private function pridejStitekVekuAktiviteSId(int $idAktivity, string $stitekVeku): void
    {
        dbInsert(AktivitaTagSql::AKCE_SJEDNOCENE_TAGY_TABULKA, [
            AktivitaTagSql::ID_AKCE => $idAktivity,
            AktivitaTagSql::ID_TAGU => $this->getStitek($stitekVeku, KategorieTagu::OMEZENI_VEK)->id(),
        ]);
    }

    /**
     * Turnaj z larpů se štítky věku po kolech, kolo může mít víc aktivit na výběr.
     *
     * @param list<list<string|null>> $kola v každém kole štítek věku každé aktivity, null bez štítku
     *
     * @return list<list<Aktivita>> aktivity po kolech, načtené každá zvlášť (zId())
     */
    private function vytvorTurnaj(array $kola): array
    {
        dbInsert('turnaje', [
            'nazev' => 'Turnaj ' . self::unikatniCislo(),
            'rok'   => ROCNIK,
        ]);
        $idTurnaje = (int) dbInsertId();
        $aktivityPoKolech = [];
        foreach (array_values($kola) as $index => $stitkyAktivitKola) {
            foreach ($stitkyAktivitKola as $stitekVeku) {
                $aktivita = $stitekVeku === null
                    ? $this->vytvorAktivitu()
                    : $this->vytvorAktivitu($stitekVeku);
                dbUpdate(AktivitaSql::AKCE_SEZNAM_TABULKA, [
                    AktivitaSql::ID_TURNAJE  => $idTurnaje,
                    AktivitaSql::TURNAJ_KOLO => $index + 1,
                ], [
                    AktivitaSql::ID_AKCE => $aktivita->id(),
                ]);
                $aktivityPoKolech[$index][] = Aktivita::zId($aktivita->id(), false, $this->systemoveNastaveni);
            }
        }

        return $aktivityPoKolech;
    }

    private function getStitek(string $nazev, int $idKategorie): Tag
    {
        $existujici = Tag::zNazvu($nazev);
        if ($existujici) {
            self::assertSame($idKategorie, $existujici->idKategorieTagu(), "Štítek {$nazev} už je v jiné kategorii");

            return $existujici;
        }
        dbInsert(TagSql::_table, [
            TagSql::nazev             => $nazev,
            TagSql::poznamka          => '',
            TagSql::id_kategorie_tagu => $idKategorie,
        ]);

        return Tag::zId((int) dbInsertId());
    }

    /**
     * @param string $registrovan narození v den registrace nebo po ní je jen výplň (rychloregistrace), výchozí je proto
     *                            začátek ročníku, kdy už se každý z testů narodil
     */
    private function vytvorUcastnika(
        ?int $vekVDenAktivity = null,
        ?string $datumNarozeni = null,
        string $registrovan = ROCNIK . '-01-01 00:00:00',
    ): \Uzivatel {
        // další narozeniny až den po aktivitě, v den aktivity je mu tedy přesně $vekVDenAktivity
        $datumNarozeni ??= (new \DateTimeImmutable(self::ZACATEK_AKTIVITY))
            ->modify('-' . ($vekVDenAktivity + 1) . ' years +1 day')
            ->format('Y-m-d');
        $cislo = self::unikatniCislo();
        dbInsert(UserSql::_table, [
            UserSql::login_uzivatele  => 'test_vek_' . $cislo,
            UserSql::email1_uzivatele => 'godric.cz+gc_test_vek_' . $cislo . '@gmail.com',
            UserSql::pohlavi          => Pohlavi::ZENA_KOD,
            UserSql::datum_narozeni   => $datumNarozeni,
            UserSql::registrovan      => $registrovan,
        ]);
        $idUzivatele = dbInsertId();
        dbInsert(UserRoleSql::_table, [
            UserRoleSql::id_uzivatele => $idUzivatele,
            UserRoleSql::id_role      => Role::PRIHLASEN_NA_LETOSNI_GC,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);

        return $uzivatel;
    }

    /**
     * Přihlášení rovnou do databáze, bez kontrol a bez e-mailu šéfovi sekce.
     */
    private function prihlas(Aktivita $aktivita, \Uzivatel $ucastnik): void
    {
        dbInsert(PrihlaseniSql::AKCE_PRIHLASENI_TABULKA, [
            PrihlaseniSql::ID_AKCE             => $aktivita->id(),
            PrihlaseniSql::ID_UZIVATELE        => $ucastnik->id(),
            PrihlaseniSql::ID_STAVU_PRIHLASENI => StavPrihlaseni::PRIHLASEN,
        ]);
    }

    /**
     * Šéf sekce larpů z hlavičky linie (admin Web → Hlavičky linií); null hlavičku smaže.
     */
    private function nastavSefaSekceLarpu(?string $sekce, ?string $email = null): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM akce_typy_hlavicky WHERE id_typu = $0
            SQL, [
            0 => TypAktivity::LARP,
        ]);
        // hlavičky si pamatuje na celý požadavek
        KontaktSefaLinie::smazCache();
        if ($sekce === null) {
            return;
        }
        dbQuery(<<<'SQL'
            INSERT INTO akce_typy_hlavicky (id_typu, sekce, jmeno, email) VALUES ($0, $1, $2, $3)
            SQL, [
            0 => TypAktivity::LARP,
            1 => $sekce,
            2 => 'Lenka „Cemi“ Testovací',
            3 => $email,
        ]);
    }

    private static function unikatniCislo(): int
    {
        static $pocitadlo = null;
        // třídy s tímhle traitem mají každá svůj čítač, začátek podle času drží loginy různé i mezi nimi
        $pocitadlo ??= (int) (hrtime(true) % 1_000_000_000);

        return ++$pocitadlo;
    }
}
