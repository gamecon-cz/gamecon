<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use App\Enum\OrderItemChangeEnum;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\UzivatelSlucovani;

/**
 * Nákupy se při slučování uživatelů přesouvají přes Doctrine, takže je zachytí log nákupů.
 */
class UzivatelSlucovaniNakupyTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private int $idVarianty;

    protected function setUp(): void
    {
        parent::setUp();

        dbQuery(<<<'SQL'
            INSERT INTO shop_predmety SET
                id_predmetu = 78801,
                nazev = 'Placka slučování',
                kod_predmetu = 'placka_slucovani',
                cena_aktualni = 50,
                stav = 1,
                nabizet_do = '2099-01-01 00:00:00',
                vedlejsi = 0,
                popis = ''
            SQL);
        dbQuery(<<<'SQL'
            INSERT INTO product_variant (product_id, name, code, capacity, position, state)
            VALUES (78801, NULL, 'placka_slucovani', NULL, 0, 1)
            SQL);
        $this->idVarianty = (int) dbInsertId();
    }

    public function testNakupyStarehoUzivateleSePresunouAZalogujiSe(): void
    {
        $stary = $this->uzivatel('stary');
        $novy = $this->uzivatel('novy');
        $okolni = $this->uzivatel('okolni');

        // Starší nákupy mohou mít prázdné `product_tags`, protože je při převodu e-shopu doplnil
        // JSON_ARRAYAGG, který pro produkt bez štítků vrací NULL.
        $jehoNakup = $this->nakup((int) $stary->id(), null, tagyNull: true);
        self::assertNull(
            dbOneCol('SELECT product_tags FROM shop_nakupy WHERE id_nakupu = $0', [$jehoNakup]),
            'Předpoklad testu: nákup má v databázi product_tags = NULL',
        );
        $objednalZaJineho = $this->nakup((int) $okolni->id(), (int) $stary->id());
        $cizi = $this->nakup((int) $okolni->id(), null);
        $ciziNakupZmenenyStarym = $this->nakup((int) $okolni->id(), null);
        $ciziNakupBezStareho = $this->nakup((int) $okolni->id(), null);
        $this->radekLogu($jehoNakup, (int) $stary->id(), (int) $stary->id());
        $this->radekLogu($ciziNakupZmenenyStarym, (int) $okolni->id(), (int) $stary->id());
        $this->radekLogu($ciziNakupBezStareho, (int) $okolni->id(), (int) $okolni->id());

        (new UzivatelSlucovani())->sluc($stary, $novy, []);

        self::assertSame([(int) $novy->id(), null], $this->majitele($jehoNakup), 'Nákup starého uživatele patří novému');
        self::assertSame([(int) $okolni->id(), (int) $novy->id()], $this->majitele($objednalZaJineho), 'Kdo objednával, je nový uživatel; komu, zůstává');
        self::assertSame([(int) $okolni->id(), null], $this->majitele($cizi), 'Cizí nákup se nehýbe');

        self::assertSame(
            [(int) $stary->id(), (int) $novy->id()],
            $this->zmenaVLogu($jehoNakup, 'id_uzivatele'),
        );
        self::assertSame(
            [(int) $stary->id(), (int) $novy->id()],
            $this->zmenaVLogu($objednalZaJineho, 'id_objednatele'),
        );
        self::assertSame(0, $this->pocetRadkuLogu($cizi), 'Nákup, který se nezměnil, nemá co v logu dělat');
        self::assertSame(
            [(int) $novy->id(), (int) $novy->id()],
            $this->zakaznikAZmenilVStaremRadkuLogu($jehoNakup),
            'Starší řádek logu nesmí zůstat pod smazaným uživatelem',
        );
        self::assertSame(
            [(int) $okolni->id(), (int) $novy->id()],
            $this->zakaznikAZmenilVStaremRadkuLogu($ciziNakupZmenenyStarym),
            'Kdo změnil, je nový uživatel; zákazník v řádku zůstává',
        );
        self::assertSame(
            [(int) $okolni->id(), (int) $okolni->id()],
            $this->zakaznikAZmenilVStaremRadkuLogu($ciziNakupBezStareho),
            'Řádek logu, který se starého uživatele netýká, se nehýbe',
        );
    }

    private function uzivatel(string $prefix): \Uzivatel
    {
        $login = $prefix . '_' . uniqid();
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'  => $login,
            'email1_uzivatele' => $login . '@example.invalid',
        ]);

        return \Uzivatel::zId(dbInsertId());
    }

    private function nakup(int $idZakaznika, ?int $idObjednatele, bool $tagyNull = false): int
    {
        $radek = [
            'id_uzivatele'   => $idZakaznika,
            'id_objednatele' => $idObjednatele,
            'variant_id'     => $this->idVarianty,
            'rok'            => ROCNIK,
            'cena_nakupni'   => 50,
        ];
        if ($tagyNull) {
            $radek['product_tags'] = null;
        }
        dbInsert('shop_nakupy', $radek);

        return (int) dbInsertId();
    }

    private function radekLogu(int $idNakupu, int $idZakaznika, int $idZmenil): void
    {
        dbInsert('shop_order_item_log', [
            'order_item_id' => $idNakupu,
            'customer_id'   => $idZakaznika,
            'year'          => ROCNIK,
            'action'        => OrderItemChangeEnum::CREATED,
            'changes'       => '{}',
            'changed_by_id' => $idZmenil,
            'origin'        => 'test',
            'changed_at'    => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * @return array{0: int, 1: int|null} zákazník, objednatel
     */
    private function majitele(int $idNakupu): array
    {
        $radek = dbFetchRow(
            <<<'SQL'
            SELECT id_uzivatele, id_objednatele FROM shop_nakupy WHERE id_nakupu = $0
            SQL,
            [$idNakupu],
        );

        return [(int) $radek['id_uzivatele'], $radek['id_objednatele'] === null ? null : (int) $radek['id_objednatele']];
    }

    /**
     * @return array{0: int, 1: int} [před, po]
     */
    private function zmenaVLogu(int $idNakupu, string $sloupec): array
    {
        $json = dbOneCol(
            <<<'SQL'
            SELECT changes FROM shop_order_item_log WHERE order_item_id = $0 AND action = $1
            SQL,
            [$idNakupu, OrderItemChangeEnum::UPDATED],
        );
        self::assertNotEmpty($json, "Nákup {$idNakupu} nemá v logu řádek o změně");
        $zmeny = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

        return [(int) $zmeny[$sloupec][0], (int) $zmeny[$sloupec][1]];
    }

    private function pocetRadkuLogu(int $idNakupu): int
    {
        return (int) dbOneCol(
            <<<'SQL'
            SELECT COUNT(*) FROM shop_order_item_log WHERE order_item_id = $0
            SQL,
            [$idNakupu],
        );
    }

    /**
     * @return array{0: int, 1: int} zákazník, kdo změnil
     */
    private function zakaznikAZmenilVStaremRadkuLogu(int $idNakupu): array
    {
        $radek = dbFetchRow(
            <<<'SQL'
            SELECT customer_id, changed_by_id FROM shop_order_item_log WHERE order_item_id = $0 AND action = $1
            SQL,
            [$idNakupu, OrderItemChangeEnum::CREATED],
        );

        return [(int) $radek['customer_id'], (int) $radek['changed_by_id']];
    }
}
