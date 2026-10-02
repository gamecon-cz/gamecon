<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Sizes and nights are variants of their product. Their old catalog rows, kept while legacy read
// them, go; so does the size a group owner's code still names, since the owner is the product.

$this->q(<<<'SQL'
CREATE TEMPORARY TABLE tmp_zbyle_radky AS
SELECT radek.id_predmetu
FROM shop_predmety AS radek
    INNER JOIN product_variant ON product_variant.code = radek.kod_predmetu
WHERE product_variant.product_id <> radek.id_predmetu
SQL);

// Only tags still point at these rows; anything else would lose what it points at, so stop
// before writing anything.
foreach ([
    'shop_nakupy'         => 'id_predmetu',
    'shop_nakupy_zrusene' => 'id_predmetu',
    'product_discount'    => 'product_id',
    'product_variant'     => 'product_id',
] as $tabulka => $sloupec) {
    $odkazu = (int) $this->q(
        "SELECT COUNT(*) FROM {$tabulka} WHERE {$sloupec} IN (SELECT id_predmetu FROM tmp_zbyle_radky)",
    )->fetchColumn();
    if ($odkazu > 0) {
        throw new \RuntimeException("Na zbylé řádky velikostí a nocí ukazuje {$odkazu} řádků {$tabulka}.{$sloupec} — převeď je na produkt a migraci spusť znovu.");
    }
}
$bunek = (int) $this->q(
    'SELECT COUNT(*) FROM obchod_bunky WHERE typ = 0 AND cil_id IN (SELECT id_predmetu FROM tmp_zbyle_radky)',
)->fetchColumn();
if ($bunek > 0) {
    throw new \RuntimeException("Na zbylé řádky velikostí a nocí ukazuje {$bunek} buněk mřížkového prodeje — převeď je na produkt a migraci spusť znovu.");
}

// A group owner's code is its first size's code; drop exactly the size its own variant is named.
$vlastnici = $this->q(<<<'SQL'
SELECT shop_predmety.id_predmetu, shop_predmety.kod_predmetu, vlastni.name AS velikost
FROM shop_predmety
    INNER JOIN product_variant AS vlastni
        ON vlastni.product_id = shop_predmety.id_predmetu AND vlastni.code = shop_predmety.kod_predmetu
WHERE (SELECT COUNT(*) FROM product_variant WHERE product_variant.product_id = shop_predmety.id_predmetu) > 1
SQL)->fetchAll(\PDO::FETCH_ASSOC);
$obsazene = array_flip(array_merge(
    $this->q('SELECT kod_predmetu FROM shop_predmety')->fetchAll(\PDO::FETCH_COLUMN),
    $this->q('SELECT code FROM product_variant')->fetchAll(\PDO::FETCH_COLUMN),
));
$noveKody = [];
foreach ($vlastnici as $vlastnik) {
    $tokeny = preg_split('~[_-]~', $vlastnik['kod_predmetu']);
    $velikost = preg_split('~[_\- ]~', mb_strtolower((string) $vlastnik['velikost']));
    $nalezy = [];
    for ($index = 0; $index + count($velikost) <= count($tokeny); ++$index) {
        if (array_map('mb_strtolower', array_slice($tokeny, $index, count($velikost))) === $velikost) {
            $nalezy[] = $index;
        }
    }
    if (count($nalezy) !== 1) {
        throw new \RuntimeException(sprintf(
            'Kód „%s" nese velikost „%s" %d×, nejde z něj poznat kód produktu — přejmenuj ho ručně a migraci spusť znovu.',
            $vlastnik['kod_predmetu'],
            $vlastnik['velikost'],
            count($nalezy),
        ));
    }
    array_splice($tokeny, $nalezy[0], count($velikost));
    $kod = implode('_', $tokeny);
    if (isset($obsazene[$kod])) {
        $kod .= '_' . $vlastnik['id_predmetu'];
    }
    $obsazene[$kod] = true;
    $noveKody[(int) $vlastnik['id_predmetu']] = $kod;
}
foreach ($noveKody as $idPredmetu => $kod) {
    $this->q('UPDATE shop_predmety SET kod_predmetu = ' . $this->connection->quote($kod) . ' WHERE id_predmetu = ' . $idPredmetu);
}

$this->q('DELETE FROM product_product_tag WHERE product_id IN (SELECT id_predmetu FROM tmp_zbyle_radky)');
$this->q('DELETE FROM shop_predmety WHERE id_predmetu IN (SELECT id_predmetu FROM tmp_zbyle_radky)');
$this->q('DROP TEMPORARY TABLE tmp_zbyle_radky');
