<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\PrehledVypravecu;

class PrehledVypravecuTest extends AbstractTestDb
{
    private PrehledVypravecu $prehled;

    protected function setUp(): void
    {
        parent::setUp();

        dbQuery(<<<'SQL'
INSERT IGNORE INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, 'Vypravěč (test)', '', $2, 'rocnikova', $3)
SQL, [
    0         => Role::LETOSNI_VYPRAVEC(ROCNIK),
            1 => 'TEST_VYPRAVEC_' . ROCNIK,
            2 => ROCNIK,
            3 => Role::VYZNAM_VYPRAVEC,
]);
        // sekce jsou linie z menu webu, brigádnické aktivity v něm nejsou
        dbQuery(<<<'SQL'
UPDATE akce_typy SET zobrazit_v_menu = id_typu IN ($0)
WHERE id_typu IN ($0, $1)
SQL, [
    0         => [TypAktivity::RPG, TypAktivity::LARP, TypAktivity::WARGAMING],
            1 => TypAktivity::BRIGADNICKA,
]);

        \Uzivatel::smazCache();
        $this->prehled = new PrehledVypravecu(ROCNIK);
    }

    public function testVypraveciJsouLideSRoliVypraveceSerazeniPodleAbecedy(): void
    {
        $tomas = $this->vlozVypravece('Tomáš');
        $simon = $this->vlozVypravece('Šimon');
        $adam = $this->vlozVypravece('Adam');

        // podle bajtů by Šimon skončil až za Tomášem
        self::assertSame([$adam, $simon, $tomas], $this->idVypravecu($this->prehled->vypraveci()));
    }

    public function testVypravecemJeIKdoBezRoleVedeNetechnickouAktivitu(): void
    {
        $sRoli = $this->vlozVypravece('Bára');
        $this->vlozAktivitu($sRoli, TypAktivity::RPG);
        $bezRole = $this->vlozUzivatele('Cyril');
        $this->vlozAktivitu($bezRole, TypAktivity::LARP);
        $this->vlozAktivitu($bezRole, TypAktivity::TECHNICKA);
        $technik = $this->vlozUzivatele('Technik');
        $this->vlozAktivitu($technik, TypAktivity::TECHNICKA);
        $brigadnik = $this->vlozUzivatele('Brigádník');
        $this->vlozAktivitu($brigadnik, TypAktivity::BRIGADNICKA);
        $vPriprave = $this->vlozUzivatele('Připravující');
        $this->vlozAktivitu($vPriprave, TypAktivity::RPG, stav: StavAktivity::NOVA);
        $lonsky = $this->vlozUzivatele('Loňský');
        $this->vlozAktivitu($lonsky, TypAktivity::RPG, rok: ROCNIK - 1);

        self::assertSame([$sRoli, $bezRole], $this->idVypravecu($this->prehled->vypraveci()));
    }

    public function testSekceJsouLinieZMenuSViditelnouAktivitouVypravece(): void
    {
        $vypravec = $this->vlozVypravece('Vojta');
        $this->vlozAktivitu($vypravec, TypAktivity::RPG);
        $this->vlozAktivitu($vypravec, TypAktivity::RPG);
        $this->vlozAktivitu($vypravec, TypAktivity::LARP, stav: StavAktivity::NOVA);
        $this->vlozAktivitu($vypravec, TypAktivity::WARGAMING, rok: ROCNIK - 1);
        $this->vlozAktivitu($vypravec, TypAktivity::BRIGADNICKA);

        self::assertSame(
            [TypAktivity::RPG],
            $this->idLinii($this->prehled->sekceVypravece(\Uzivatel::zId($vypravec))),
        );
    }

    public function testFiltrSekceUkazeJenJejiVypravece(): void
    {
        $rpgcko = $this->vlozVypravece('Radek');
        $this->vlozAktivitu($rpgcko, TypAktivity::RPG);
        $larpar = $this->vlozVypravece('Lenka');
        $this->vlozAktivitu($larpar, TypAktivity::LARP);
        $this->vlozAktivitu($larpar, TypAktivity::RPG);
        $this->vlozVypravece('Nováček');
        $bezRole = $this->vlozUzivatele('Bezrole');
        $this->vlozAktivitu($bezRole, TypAktivity::WARGAMING);

        self::assertSame(
            [$larpar, $rpgcko],
            $this->idVypravecu($this->prehled->vypraveci(TypAktivity::zId(TypAktivity::RPG))),
        );
        self::assertSame([$larpar], $this->idVypravecu($this->prehled->vypraveci(TypAktivity::zId(TypAktivity::LARP))));
        // wargaming vede jen člověk bez role vypravěče
        self::assertSame(
            [$bezRole],
            $this->idVypravecu($this->prehled->vypraveci(TypAktivity::zId(TypAktivity::WARGAMING))),
        );
        self::assertSame(
            [TypAktivity::LARP, TypAktivity::RPG, TypAktivity::WARGAMING],
            $this->idLinii($this->prehled->sekce()),
        );
    }

    private function vlozVypravece(string $jmeno): int
    {
        $idUzivatele = $this->vlozUzivatele($jmeno);
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL, [
            0 => $idUzivatele,
            1 => Role::LETOSNI_VYPRAVEC(ROCNIK),
        ]);

        return $idUzivatele;
    }

    private function vlozUzivatele(string $jmeno): int
    {
        $login = $jmeno . uniqid('', false);
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET login_uzivatele = $0, email1_uzivatele = $1, jmeno_uzivatele = $2, prijmeni_uzivatele = 'Vypravěčský'
SQL, [
            0 => $login,
            1 => "{$login}@example.org",
            2 => $jmeno,
        ]);

        return (int) dbInsertId();
    }

    private function vlozAktivitu(
        int $idVypravece,
        int $typ,
        int $stav = StavAktivity::PUBLIKOVANA,
        int $rok = ROCNIK,
    ): void {
        dbQuery(<<<'SQL'
INSERT INTO akce_seznam(
    nazev_akce, rok, stav, cena, typ, zacatek, konec,
    kapacita, kapacita_f, kapacita_m, teamova, popis, popis_kratky, vybaveni
)
VALUES ('Vyprávění', $0, $1, 0, $2, $3, $4, 10, 0, 0, 0, '', '', '')
SQL, [
            0 => $rok,
            1 => $stav,
            2 => $typ,
            3 => "{$rok}-07-17 10:00:00",
            4 => "{$rok}-07-17 13:00:00",
        ]);
        dbQuery(<<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)
SQL, [
            0 => dbInsertId(),
            1 => $idVypravece,
        ]);
    }

    /**
     * @param list<\Uzivatel> $vypraveci
     *
     * @return list<int>
     */
    private function idVypravecu(array $vypraveci): array
    {
        return array_map(static fn (\Uzivatel $vypravec) => (int) $vypravec->id(), $vypraveci);
    }

    /**
     * @param list<TypAktivity> $linie
     *
     * @return list<int>
     */
    private function idLinii(array $linie): array
    {
        return array_map(static fn (TypAktivity $typ) => (int) $typ->id(), $linie);
    }
}
