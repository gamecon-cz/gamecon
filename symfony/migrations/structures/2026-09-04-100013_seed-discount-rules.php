<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The thirteen discount rules Cenik implements, expressed as data.
//
// Everything here is a literal on purpose — no constants, no enums, no validation
// through application code. A migration is a historical statement, but it replays on
// every fresh database including every test run, so a reference to live code makes it
// keep changing: rename a constant or tighten a validator and this migration starts
// failing years later, over rows that were always fine. The numbers below therefore
// record what the values were when the rules were introduced, and are allowed to drift
// from the constants if those ever change.
//
// Rights (id_prava), not roles: Cenik checks Pravo throughout, so a role gaining or
// losing a right keeps working with no change here.
//
// The meal amount and the bonus threshold stay in systemove_nastaveni and are named
// through DiscountSetting case names rather than copied, so an admin changing a setting
// changes the rule instead of moving legacy and data apart.
//
// Seeded for the current ROCNIK. Copying rules into a new year is a separate concern.

$rules = [
    [
        'code'           => 'kostka_zdarma',
        'name'           => 'Kostka zdarma',
        'description'    => 'Jedna kostka zdarma pro toho, kdo má právo na kostku zdarma. Druhá a další už za plnou cenu.',
        'required_right' => 1003, // Pravo::KOSTKA_ZDARMA
        'parameters'     => '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}',
    ],
    [
        'code'           => 'placka_zdarma',
        'name'           => 'Placka zdarma',
        'description'    => 'Jedna placka zdarma. Nárok na kostku se tím nevyčerpá, jsou to dvě samostatná práva.',
        'required_right' => 1002, // Pravo::PLACKA_ZDARMA
        'parameters'     => '{"scope":"code_contains","effect":"free","codeFragment":"placka","maxQuantity":1}',
    ],
    [
        'code'           => 'tricko_za_bonus',
        'name'           => 'Tričko zdarma za bonus',
        'description'    => 'Kdo dosáhne bonusu za vedení aktivit, má zdarma libovolné tričko — vždy to nejlevnější v košíku, bez ohledu na barvu a typ.',
        'required_right' => 1012, // Pravo::MODRE_TRICKO_ZDARMA
        'parameters'     => '{"scope":"tag_cheapest","effect":"free","tag":"tricko","maxQuantity":1,"thresholdSetting":"freeShirtBonusThreshold"}',
    ],
    [
        'code'           => 'jedno_tricko_zdarma',
        'name'           => 'Jedno tričko zdarma',
        'description'    => 'Jedno libovolné tričko zdarma, nezávisle na bonusu.',
        'required_right' => 1035, // Pravo::JAKEKOLIV_TRICKO_ZDARMA
        'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}',
    ],
    [
        'code'           => 'dve_tricka_zdarma',
        'name'           => 'Dvě trička zdarma',
        'description'    => 'Dvě libovolná trička zdarma. Kdo má obě práva, dostane dvě.',
        'required_right' => 1020, // Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA
        'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}',
    ],
    [
        'code'           => 'ubytovani_zdarma',
        'name'           => 'Ubytování zdarma',
        'description'    => 'Ubytování zdarma po celou dobu festivalu, všechny noci.',
        'required_right' => 1008, // Pravo::UBYTOVANI_ZDARMA
        'parameters'     => '{"scope":"tag","effect":"free","tag":"ubytovani"}',
    ],
    [
        'code'           => 'jidlo_zdarma',
        'name'           => 'Jídlo zdarma',
        'description'    => 'Všechna jídla zdarma.',
        'required_right' => 1005, // Pravo::JIDLO_ZDARMA
        'parameters'     => '{"scope":"tag","effect":"free","tag":"jidlo"}',
    ],
    [
        'code'           => 'jidlo_se_slevou',
        'name'           => 'Jídlo se slevou',
        'description'    => 'Sleva pevnou částkou na každé jídlo — jediná sleva, která není 100 %.',
        'required_right' => 1004, // Pravo::JIDLO_SE_SLEVOU
        'parameters'     => '{"scope":"tag","effect":"fixed_amount","tag":"jidlo","amountSetting":"organizerMealDiscount"}',
    ],
    [
        'code'           => 'ubytovani_stredecni_noc_zdarma',
        'name'           => 'Ubytování středeční noc zdarma',
        'description'    => 'Zdarma středeční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1015, // Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":0}',
    ],
    [
        'code'           => 'ubytovani_ctvrtecni_noc_zdarma',
        'name'           => 'Ubytování čtvrteční noc zdarma',
        'description'    => 'Zdarma čtvrteční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1029, // Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":1}',
    ],
    [
        'code'           => 'ubytovani_patecni_noc_zdarma',
        'name'           => 'Ubytování páteční noc zdarma',
        'description'    => 'Zdarma páteční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1030, // Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":2}',
    ],
    [
        'code'           => 'ubytovani_sobotni_noc_zdarma',
        'name'           => 'Ubytování sobotní noc zdarma',
        'description'    => 'Zdarma sobotní noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1031, // Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":3}',
    ],
    [
        'code'           => 'ubytovani_nedelni_noc_zdarma',
        'name'           => 'Ubytování nedělní noc zdarma',
        'description'    => 'Zdarma nedělní noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1018, // Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":4}',
    ],
];

foreach ($rules as $rule) {
    dbQuery(
        'INSERT INTO discount_rule (code, name, description, required_right, parameters, year, active)
         VALUES ($0, $1, $2, $3, $4, $5, 1)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            description = VALUES(description),
            required_right = VALUES(required_right),
            parameters = VALUES(parameters)',
        [
            0 => $rule['code'],
            1 => $rule['name'],
            2 => $rule['description'],
            3 => $rule['required_right'],
            4 => $rule['parameters'],
            5 => ROCNIK,
        ],
    );
}
