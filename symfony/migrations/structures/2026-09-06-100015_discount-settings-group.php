<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Makes the discount quantities editable, in the Slevy group on the settings page.
//
// The counts (one free dice, one badge, one or two shirts) were hardcoded in Cenik and
// Finance. Putting them here rather than building a separate admin screen for
// discount_rule means the existing settings page handles them: it groups by `skupina`,
// orders by `poradi`, picks the input from `datovy_typ`, and already records who
// changed what and when. An admin looking for "how many free dice" finds it beside
// "how big is the organizer meal discount", which is where they already look.
//
// Only numbers live here. What a rule *is* — which tag it targets, whether it is free
// or a fixed amount — stays in discount_rule and is not admin-editable: changing a
// rule's tag would not tune it, it would turn free meals into free shirts under the
// old name.
//
// Literals only, no constants or enums: this migration replays on every fresh database
// including every test run, so anything it borrows from live code can break it years
// later over rows that were always fine.

$settings = [
    [
        'klic'    => 'SLEVA_KOSTEK_ZDARMA_POCET',
        'hodnota' => '1',
        'nazev'   => 'Kolik kostek zdarma',
        'popis'   => 'Kolik kostek dostane zdarma ten, kdo má právo „kostka zdarma“. Další už za plnou cenu.',
        'poradi'  => 40,
    ],
    [
        'klic'    => 'SLEVA_PLACEK_ZDARMA_POCET',
        'hodnota' => '1',
        'nazev'   => 'Kolik placek zdarma',
        'popis'   => 'Kolik placek dostane zdarma ten, kdo má právo „placka zdarma“. Nárok na kostku se tím nevyčerpá.',
        'poradi'  => 41,
    ],
    [
        'klic'    => 'SLEVA_TRICEK_ZDARMA_POCET',
        'hodnota' => '1',
        'nazev'   => 'Kolik triček zdarma za právo',
        'popis'   => 'Kolik triček dostane zdarma ten, kdo má právo „jedno jakékoliv tričko zdarma“.',
        'poradi'  => 42,
    ],
    [
        'klic'    => 'SLEVA_DVOU_TRICEK_ZDARMA_POCET',
        'hodnota' => '2',
        'nazev'   => 'Kolik triček zdarma za rozšířené právo',
        'popis'   => 'Kolik triček dostane zdarma ten, kdo má právo „dvě jakákoli trička zdarma“.',
        'poradi'  => 43,
    ],
    [
        'klic'    => 'SLEVA_BONUSOVYCH_TRICEK_ZDARMA_POCET',
        'hodnota' => '1',
        'nazev'   => 'Kolik triček zdarma za bonus',
        'popis'   => 'Kolik triček dostane zdarma ten, kdo dosáhl bonusu za vedení aktivit. Sleva vždy padne na nejlevnější tričko v košíku.',
        'poradi'  => 44,
    ],
];

foreach ($settings as $setting) {
    dbQuery(
        "INSERT INTO systemove_nastaveni
            (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi, pouze_pro_cteni, rocnik_nastaveni)
         VALUES (\$0, \$1, 1, 'integer', \$2, \$3, 'Slevy', \$4, 0, -1)
         ON DUPLICATE KEY UPDATE nazev = VALUES(nazev), popis = VALUES(popis), skupina = VALUES(skupina), poradi = VALUES(poradi)",
        [
            0 => $setting['klic'],
            1 => $setting['hodnota'],
            2 => $setting['nazev'],
            3 => $setting['popis'],
            4 => $setting['poradi'],
        ],
    );
}

// The two settings the discounts already used belong in the same group, so an admin
// sees every discount number together instead of hunting through Finance.
dbQuery(
    "UPDATE systemove_nastaveni
     SET skupina = 'Slevy', poradi = 10
     WHERE klic = 'SLEVA_ORGU_NA_JIDLO_CASTKA'",
);
