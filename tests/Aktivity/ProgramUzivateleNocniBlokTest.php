<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Program;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

/**
 * Regrese pro zobrazení noční aktivity v admin programu účastníka.
 */
class ProgramUzivateleNocniBlokTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 910001;
    private const ID_AKTIVITY = 910101;

    protected static bool $disableStrictTransTables = true;

    public function testNocniBlokSeVOsobnimProgramuZobraziAzPoPulnoci(): void
    {
        $zacatekNocniAktivity = (clone DateTimeGamecon::zacatekProgramu(ROCNIK))
            ->plusDen()
            ->setTime(0, 0);
        $konecNocniAktivity = (clone $zacatekNocniAktivity)->modify('+4 hours');

        $this->vlozUzivatele(self::ID_UZIVATELE);
        $this->vlozAktivitu([
            AktivitaSql::ID_AKCE    => self::ID_AKTIVITY,
            AktivitaSql::NAZEV_AKCE => 'Nocni aktivita',
            AktivitaSql::ZACATEK    => $zacatekNocniAktivity->format('Y-m-d H:i:s'),
            AktivitaSql::KONEC      => $konecNocniAktivity->format('Y-m-d H:i:s'),
        ]);
        dbInsertUpdate('akce_prihlaseni', [
            'id_akce'             => self::ID_AKTIVITY,
            'id_uzivatele'        => self::ID_UZIVATELE,
            'id_stavu_prihlaseni' => StavPrihlaseni::PRIHLASEN,
        ]);

        $uzivatel = \Uzivatel::zId(self::ID_UZIVATELE, true);
        $program = new Program(
            systemoveNastaveni: SystemoveNastaveni::zGlobals(),
            uzivatel: $uzivatel,
            nastaveni: [
                Program::OSOBNI     => true,
                Program::TEAM_VYBER => false,
            ],
        );

        ob_start();
        $program->tisk();
        $output = ob_get_clean();

        self::assertIsString($output);

        $ocekavanyPocetPrazdnychBunek = array_search(0, Program::seznamHodinZacatku(), true);
        self::assertNotFalse($ocekavanyPocetPrazdnychBunek);
        $ocekavanyPocetPrazdnychBunek = (int) $ocekavanyPocetPrazdnychBunek;
        self::assertSame(
            $ocekavanyPocetPrazdnychBunek,
            $this->pocetPrazdnychBunekPredAktivitou($output, 'Nocni aktivita'),
            'Noční aktivita má začínat až ve sloupci 0:00, ne v prvním večerním sloupci.',
        );
    }

    private function pocetPrazdnychBunekPredAktivitou(string $output, string $nazevAktivity): int
    {
        $regex = '~<tr class="linie">\s*<td rowspan="1">.*?</td>(?<prazdne>(?:\s*<td></td>\s*)*)\s*<td colspan="4">.*?'
            . preg_quote($nazevAktivity, '~')
            . '.*?</td>~su';
        self::assertMatchesRegularExpression(
            $regex,
            $output,
            'Nepodařilo se najít řádek osobního programu s testovanou aktivitou.',
        );
        preg_match($regex, $output, $shody);

        return preg_match_all('~<td></td>~', $shody['prazdne'] ?? '');
    }

    private function vlozUzivatele(int $idUzivatele): void
    {
        dbInsertUpdate(UzivatelSql::UZIVATELE_HODNOTY_TABULKA, [
            UzivatelSql::ID_UZIVATELE       => $idUzivatele,
            UzivatelSql::LOGIN_UZIVATELE    => 'nocni-test-' . $idUzivatele,
            UzivatelSql::JMENO_UZIVATELE    => 'Nocni',
            UzivatelSql::PRIJMENI_UZIVATELE => 'Tester',
            UzivatelSql::EMAIL1_UZIVATELE   => 'nocni-test-' . $idUzivatele . '@example.test',
        ]);
    }

    private function vlozAktivitu(array $data): void
    {
        $defaults = [
            AktivitaSql::NAZEV_AKCE   => 'Test aktivita',
            AktivitaSql::POPIS_KRATKY => 'Krátký popis',
            AktivitaSql::POPIS        => '',
            AktivitaSql::ROK          => ROCNIK,
            AktivitaSql::STAV         => StavAktivity::AKTIVOVANA,
            AktivitaSql::TYP          => TypAktivity::DESKOHERNA,
            AktivitaSql::KAPACITA     => 5,
            AktivitaSql::KAPACITA_F   => 0,
            AktivitaSql::KAPACITA_M   => 0,
            AktivitaSql::CENA         => 0,
            AktivitaSql::TEAMOVA      => 0,
            AktivitaSql::VYBAVENI     => '',
        ];
        dbInsertUpdate(AktivitaSql::AKCE_SEZNAM_TABULKA, array_merge($defaults, $data));
    }
}
