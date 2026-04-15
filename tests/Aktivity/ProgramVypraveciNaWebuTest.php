<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Kernel;
use Gamecon\Aktivita\Program;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;

class ProgramVypraveciNaWebuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private const ID_AKTIVITY = 490001;

    private function createSystemoveNastaveniBehemRegistrace(): SystemoveNastaveni
    {
        return new SystemoveNastaveni(
            ROCNIK,
            new DateTimeImmutableStrict(ROCNIK . '-07-01 12:00:00'),
            false,
            false,
            DatabazoveNastaveni::vytvorZGlobals(),
            dirname(__DIR__, 2),
            SPEC,
            new Kernel('test', false),
            CACHE,
        );
    }

    private function vlozDrdAktivitu(): void
    {
        dbInsertUpdate(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::ID_AKCE      => self::ID_AKTIVITY,
            AktivitaSql::NAZEV_AKCE   => 'Testovací DrD',
            AktivitaSql::TYP          => TypAktivity::DRD,
            AktivitaSql::ROK          => ROCNIK,
            AktivitaSql::STAV         => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK      => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC        => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA     => 5,
            AktivitaSql::KAPACITA_F   => 0,
            AktivitaSql::KAPACITA_M   => 0,
            AktivitaSql::CENA         => 0,
            AktivitaSql::TEAMOVA      => 0,
            AktivitaSql::POPIS        => 'Popis testovacího DrD',
            AktivitaSql::POPIS_KRATKY => 'Krátký popis testovacího DrD',
            AktivitaSql::VYBAVENI     => '',
        ]);
    }

    private function registrujVypravece(): int
    {
        $idVypravece = \Uzivatel::registruj([
            UzivatelSql::EMAIL1_UZIVATELE       => 'eyron.' . uniqid('', true) . '@example.com',
            UzivatelSql::TELEFON_UZIVATELE      => '123456789',
            UzivatelSql::JMENO_UZIVATELE        => 'Michal',
            UzivatelSql::PRIJMENI_UZIVATELE     => 'Široký',
            UzivatelSql::ULICE_A_CP_UZIVATELE   => 'Testovací 1',
            UzivatelSql::MESTO_UZIVATELE        => 'Praha',
            UzivatelSql::PSC_UZIVATELE          => '11000',
            UzivatelSql::STAT_UZIVATELE         => '1',
            UzivatelSql::DATUM_NAROZENI         => '2000-01-01',
            UzivatelSql::STATNI_OBCANSTVI       => 'ČR',
            UzivatelSql::TYP_DOKLADU_TOTOZNOSTI => \Uzivatel::TYP_DOKLADU_OP,
            UzivatelSql::OP                     => '998009476',
            UzivatelSql::LOGIN_UZIVATELE        => 'Eyron',
            UzivatelSql::POHLAVI                => 'm',
            'heslo'                             => 'heslo123',
            'heslo_kontrola'                    => 'heslo123',
        ]);

        dbUpdate(UzivatelSql::UZIVATELE_HODNOTY_TABULKA, [
            UzivatelSql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI,
        ], [
            UzivatelSql::ID_UZIVATELE => $idVypravece,
        ]);

        dbInsertUpdate('akce_organizatori', [
            'id_akce'      => self::ID_AKTIVITY,
            'id_uzivatele' => $idVypravece,
        ]);

        return $idVypravece;
    }

    /**
     * @test
     */
    public function drdVypravecSeVProgramuZobraziPodleJmenaNaWebu(): void
    {
        $this->vlozDrdAktivitu();
        $this->registrujVypravece();

        $program = new Program(
            systemoveNastaveni: $this->createSystemoveNastaveniBehemRegistrace(),
            nastaveni: [
                Program::DRD_PJ => true,
                Program::DEN    => (int) (new \DateTimeImmutable(ROCNIK . '-07-16 00:00:00'))->format('z'),
            ],
        );

        ob_start();
        $program->tisk();
        $output = ob_get_clean();

        self::assertStringContainsString('Testovací DrD', $output);
        self::assertStringContainsString('Michal "Eyron" Široký', $output);
        self::assertStringNotContainsString('Michal „Eyron“ Široký', $output);
    }
}
