<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The six discount rules Cenik implements, expressed as data.
//
// Two things are deliberately NOT copied into the rules:
//
// The meal discount amount and the bonus threshold stay in systemove_nastaveni rather
// than being duplicated here. Copying today's numbers would silently freeze them: an
// admin changing the setting would change the legacy price and not the rule. The rule
// refers to the setting through DiscountSetting, so the settings key lives in exactly
// one place the compiler can see, and the value is resolved when the discount is
// calculated — the resolved number then goes into the snapshot on the purchase, so
// history stays truthful even when the setting later changes.
//
// Rights, not roles: Cenik checks Pravo throughout, so a role gaining or losing a
// right keeps working with no change here.
//
// Seeded per year, for the current ROCNIK. Rules are copied forward when a new year
// starts; that is a separate concern and deliberately not automated here.

$rules = [
    [
        'code'           => 'kostka_zdarma',
        'name'           => 'Kostka zdarma',
        'description'    => 'Jedna kostka zdarma pro toho, kdo má právo na kostku zdarma. Druhá a další už za plnou cenu.',
        'required_right' => Gamecon\Pravo::KOSTKA_ZDARMA,
        'parameters'     => [
            'scope'        => 'code_contains',
            'effect'       => 'free',
            'codeFragment' => 'kostka',
            'maxQuantity'  => 1,
        ],
    ],
    [
        'code'           => 'placka_zdarma',
        'name'           => 'Placka zdarma',
        'description'    => 'Jedna placka zdarma. Nárok na kostku se tím nevyčerpá, jsou to dvě samostatná práva.',
        'required_right' => Gamecon\Pravo::PLACKA_ZDARMA,
        'parameters'     => [
            'scope'        => 'code_contains',
            'effect'       => 'free',
            'codeFragment' => 'placka',
            'maxQuantity'  => 1,
        ],
    ],
    [
        'code'           => 'tricko_za_bonus',
        'name'           => 'Tričko zdarma za bonus',
        'description'    => 'Kdo dosáhne bonusu za vedení aktivit, má zdarma libovolné tričko — vždy to nejlevnější v košíku, bez ohledu na barvu a typ. Práh je hodnota nastavení MODRE_TRICKO_ZDARMA_OD.',
        'required_right' => Gamecon\Pravo::MODRE_TRICKO_ZDARMA,
        'parameters'     => [
            'scope'            => 'tag_cheapest',
            'effect'           => 'free',
            'tag'              => 'tricko',
            'maxQuantity'      => 1,
            'thresholdSetting' => App\Discount\DiscountSetting::FreeShirtBonusThreshold->value,
        ],
    ],
    [
        'code'           => 'jedno_tricko_zdarma',
        'name'           => 'Jedno tričko zdarma',
        'description'    => 'Jedno libovolné tričko zdarma, nezávisle na bonusu.',
        'required_right' => Gamecon\Pravo::JAKEKOLIV_TRICKO_ZDARMA,
        'parameters'     => [
            'scope'       => 'tag',
            'effect'      => 'free',
            'tag'         => 'tricko',
            'maxQuantity' => 1,
        ],
    ],
    [
        'code'           => 'dve_tricka_zdarma',
        'name'           => 'Dvě trička zdarma',
        'description'    => 'Dvě libovolná trička zdarma. Nekumuluje se s jedním tričkem zdarma — kdo má obě práva, dostane dvě.',
        'required_right' => Gamecon\Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA,
        'parameters'     => [
            'scope'       => 'tag',
            'effect'      => 'free',
            'tag'         => 'tricko',
            'maxQuantity' => 2,
        ],
    ],
    [
        'code'           => 'ubytovani_zdarma',
        'name'           => 'Ubytování zdarma',
        'description'    => 'Ubytování zdarma po celou dobu festivalu, všechny noci.',
        'required_right' => Gamecon\Pravo::UBYTOVANI_ZDARMA,
        'parameters'     => [
            'scope'  => 'tag',
            'effect' => 'free',
            'tag'    => 'ubytovani',
        ],
    ],
    [
        'code'           => 'jidlo_zdarma',
        'name'           => 'Jídlo zdarma',
        'description'    => 'Všechna jídla zdarma.',
        'required_right' => Gamecon\Pravo::JIDLO_ZDARMA,
        'parameters'     => [
            'scope'  => 'tag',
            'effect' => 'free',
            'tag'    => 'jidlo',
        ],
    ],
    [
        'code'           => 'jidlo_se_slevou',
        'name'           => 'Jídlo se slevou',
        'description'    => 'Sleva pevnou částkou na každé jídlo. Částka je hodnota nastavení SLEVA_ORGU_NA_JIDLO_CASTKA — jediná sleva, která není 100 %.',
        'required_right' => Gamecon\Pravo::JIDLO_SE_SLEVOU,
        'parameters'     => [
            'scope'         => 'tag',
            'effect'        => 'fixed_amount',
            'tag'           => 'jidlo',
            'amountSetting' => App\Discount\DiscountSetting::OrganizerMealDiscount->value,
        ],
    ],
];

// Free-night rights, one rule per night. The entitlement is not consumed — it applies
// to every night bought for that day, unlike the dice.
$nightRights = [
    0 => [Gamecon\Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA, 'stredecni', 'středeční'],
    1 => [Gamecon\Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA, 'ctvrtecni', 'čtvrteční'],
    2 => [Gamecon\Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA, 'patecni', 'páteční'],
    3 => [Gamecon\Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA, 'sobotni', 'sobotní'],
    4 => [Gamecon\Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA, 'nedelni', 'nedělní'],
];
foreach ($nightRights as $day => [$right, $codeSuffix, $label]) {
    $rules[] = [
        'code'           => 'ubytovani_' . $codeSuffix . '_noc_zdarma',
        'name'           => 'Ubytování ' . $label . ' noc zdarma',
        'description'    => 'Zdarma ' . $label . ' noc. Nárok se nevyčerpá, platí na každou takovou noc.',
        'required_right' => $right,
        'parameters'     => [
            'scope'  => 'tag_and_day',
            'effect' => 'free',
            'tag'    => 'ubytovani',
            'day'    => $day,
        ],
    ];
}

foreach ($rules as $rule) {
    // Validated on the way in, so a malformed rule fails the migration rather than
    // sitting in the table until someone buys something.
    App\Discount\DiscountParameters::fromArray($rule['parameters']);

    \dbQuery(
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
            4 => json_encode($rule['parameters'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            5 => ROCNIK,
        ],
    );
}
