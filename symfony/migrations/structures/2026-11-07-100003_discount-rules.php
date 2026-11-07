<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The discounts Cenik used to hard-code, as data. A rule that holds nothing of a year is stored once with year -1,
// which applies in every year, as for roles and settings. Only the free dice and badge also get a row for the year
// this runs in, because they name that year's item; later years get theirs from the e-shop import.
// Literals on purpose: a reference to live code would make this replay start failing years later.

// A lower priority wins when two rules match one item, which keeps the old if/elseif order (free meal before
// discounted meal, bonus shirt before the plain entitlement, whole stay before a single night).

// Only this year's dice and badge are free; the exact product is named so that no other year's item matches.
// The generic row names none, so it matches nothing.
$freeProductSuffix = fn (string $productCode): string => (int) ROCNIK === 2026
    ? ',"productCode":"' . $productCode . '"'
    : '';

$rules = [
    [
        'code'           => 'kostka_zdarma',
        'name'           => 'Kostka zdarma',
        'description'    => 'Jedna kostka zdarma pro toho, kdo má právo na kostku zdarma. Druhá a další už za plnou cenu.',
        'required_right' => 1003, // Pravo::KOSTKA_ZDARMA
        'priority'       => 80,
        'parameters'     => '{"scope":"product_code","effect":"free","codeFragment":"kostka","maxQuantity":1}',
        'named_for_year' => '{"scope":"product_code","effect":"free","codeFragment":"kostka"' . $freeProductSuffix('kostka_2026_verne') . ',"maxQuantity":1}',
    ],
    [
        'code'           => 'placka_zdarma',
        'name'           => 'Placka zdarma',
        'description'    => 'Jedna placka zdarma. Nárok na kostku se tím nevyčerpá, jsou to dvě samostatná práva.',
        'required_right' => 1002, // Pravo::PLACKA_ZDARMA
        'priority'       => 81,
        'parameters'     => '{"scope":"product_code","effect":"free","codeFragment":"placka","maxQuantity":1}',
        'named_for_year' => '{"scope":"product_code","effect":"free","codeFragment":"placka"' . $freeProductSuffix('placka_2026_verne') . ',"maxQuantity":1}',
    ],
    [
        'code'           => 'tricko_za_bonus',
        'name'           => 'Tričko zdarma za bonus',
        'description'    => 'Kdo dosáhne bonusu za vedení aktivit, má zdarma libovolné tričko — vždy to nejlevnější v košíku, bez ohledu na barvu a typ.',
        'required_right' => 1012, // Pravo::MODRE_TRICKO_ZDARMA
        'priority'       => 30,
        'parameters'     => '{"scope":"tag_cheapest","effect":"free","tag":"tricko","maxQuantity":1,"thresholdSetting":"freeShirtBonusThreshold"}',
    ],
    [
        'code'           => 'jedno_tricko_zdarma',
        'name'           => 'Jedno tričko zdarma',
        'description'    => 'Jedno libovolné tričko zdarma, nezávisle na bonusu.',
        'required_right' => 1035, // Pravo::JAKEKOLIV_TRICKO_ZDARMA
        'priority'       => 50,
        'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}',
    ],
    [
        'code'           => 'dve_tricka_zdarma',
        'name'           => 'Dvě trička zdarma',
        'description'    => 'Dvě libovolná trička zdarma. Kdo má obě práva, dostane dvě.',
        'required_right' => 1020, // Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA
        'priority'       => 40,
        'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}',
    ],
    [
        'code'           => 'ubytovani_zdarma',
        'name'           => 'Ubytování zdarma',
        'description'    => 'Ubytování zdarma po celou dobu festivalu, všechny noci.',
        'required_right' => 1008, // Pravo::UBYTOVANI_ZDARMA
        'priority'       => 60,
        'parameters'     => '{"scope":"tag","effect":"free","tag":"ubytovani"}',
    ],
    [
        'code'           => 'jidlo_zdarma',
        'name'           => 'Jídlo zdarma',
        'description'    => 'Všechna jídla zdarma.',
        'required_right' => 1005, // Pravo::JIDLO_ZDARMA
        'priority'       => 10,
        'parameters'     => '{"scope":"tag","effect":"free","tag":"jidlo"}',
    ],
    [
        'code'           => 'jidlo_se_slevou',
        'name'           => 'Jídlo se slevou',
        'description'    => 'Sleva pevnou částkou na každé jídlo — jediná sleva, která není 100 %.',
        'required_right' => 1004, // Pravo::JIDLO_SE_SLEVOU
        'priority'       => 20,
        'parameters'     => '{"scope":"tag","effect":"fixed_amount","tag":"jidlo","amountSetting":"organizerMealDiscount"}',
    ],
    [
        'code'           => 'ubytovani_stredecni_noc_zdarma',
        'name'           => 'Ubytování středeční noc zdarma',
        'description'    => 'Zdarma středeční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1015, // Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA
        'priority'       => 70,
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":0}',
    ],
    [
        'code'           => 'ubytovani_ctvrtecni_noc_zdarma',
        'name'           => 'Ubytování čtvrteční noc zdarma',
        'description'    => 'Zdarma čtvrteční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1029, // Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA
        'priority'       => 71,
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":1}',
    ],
    [
        'code'           => 'ubytovani_patecni_noc_zdarma',
        'name'           => 'Ubytování páteční noc zdarma',
        'description'    => 'Zdarma páteční noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1030, // Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA
        'priority'       => 72,
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":2}',
    ],
    [
        'code'           => 'ubytovani_sobotni_noc_zdarma',
        'name'           => 'Ubytování sobotní noc zdarma',
        'description'    => 'Zdarma sobotní noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1031, // Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA
        'priority'       => 73,
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":3}',
    ],
    [
        'code'           => 'ubytovani_nedelni_noc_zdarma',
        'name'           => 'Ubytování nedělní noc zdarma',
        'description'    => 'Zdarma nedělní noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => 1018, // Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA
        'priority'       => 74,
        'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":4}',
    ],
];

$insertRule = $this->connection->prepare(<<<'SQL'
INSERT INTO discount_rule (code, name, description, required_right, priority, parameters, year, active)
VALUES (:code, :name, :description, :required_right, :priority, :parameters, :year, 1)
SQL);
foreach ($rules as $rule) {
    $namedForYear = $rule['named_for_year'] ?? null;
    unset($rule['named_for_year']);
    $insertRule->execute($rule + ['year' => -1]);
    if ($namedForYear !== null) {
        $insertRule->execute(['parameters' => $namedForYear, 'year' => ROCNIK] + $rule);
    }
}

// The counts live in the settings, so the existing settings page can edit them. What a rule is (which tag,
// free or a fixed amount) stays in discount_rule, because changing it would not tune the rule but turn free
// meals into free shirts under the old name.
$settings = [
    ['SLEVA_KOSTEK_ZDARMA_POCET', '1', 'Kolik kostek zdarma', 'Kolik kostek dostane zdarma ten, kdo má právo „kostka zdarma“. Další už za plnou cenu.', 40],
    ['SLEVA_PLACEK_ZDARMA_POCET', '1', 'Kolik placek zdarma', 'Kolik placek dostane zdarma ten, kdo má právo „placka zdarma“. Nárok na kostku se tím nevyčerpá.', 41],
    ['SLEVA_TRICEK_ZDARMA_POCET', '1', 'Kolik triček zdarma za právo', 'Kolik triček dostane zdarma ten, kdo má právo „jedno jakékoliv tričko zdarma“.', 42],
    ['SLEVA_DVOU_TRICEK_ZDARMA_POCET', '2', 'Kolik triček zdarma za rozšířené právo', 'Kolik triček dostane zdarma ten, kdo má právo „dvě jakákoli trička zdarma“.', 43],
    ['SLEVA_BONUSOVYCH_TRICEK_ZDARMA_POCET', '1', 'Kolik triček zdarma za bonus', 'Kolik triček dostane zdarma ten, kdo dosáhl bonusu za vedení aktivit. Sleva vždy padne na nejlevnější tričko v košíku.', 44],
];

$insertSetting = $this->connection->prepare(<<<'SQL'
INSERT INTO systemove_nastaveni (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi, pouze_pro_cteni, rocnik_nastaveni)
VALUES (:klic, :hodnota, 1, 'integer', :nazev, :popis, 'Slevy', :poradi, 0, -1)
SQL);
foreach ($settings as [$key, $value, $name, $description, $order]) {
    $insertSetting->execute([
        'klic'    => $key,
        'hodnota' => $value,
        'nazev'   => $name,
        'popis'   => $description,
        'poradi'  => $order,
    ]);
}

// An admin looking for the discount numbers finds both settings that already existed in the same group.
$this->q(<<<'SQL'
UPDATE systemove_nastaveni
SET skupina = 'Slevy',
    poradi  = 10
WHERE klic = 'SLEVA_ORGU_NA_JIDLO_CASTKA'
SQL);
