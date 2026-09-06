<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Which rule wins when two of them match the same item has to be an explicit decision.
//
// Rules were being read ORDER BY code, so precedence fell out of the alphabet. That
// silently inverted two orderings the old if/elseif chain had on purpose:
//
//   jidlo_se_slevou < jidlo_zdarma      — a buyer holding both meal rights was charged
//                                          a 25 Kč discount instead of getting the meal
//                                          free, on every meal
//   jedno_tricko_zdarma < tricko_za_bonus — spent the wrong entitlement, so the counters
//                                          and the audit snapshot recorded the wrong rule
//
// Lower priority wins. The gaps leave room to slot a rule in without renumbering.

$columnExists = (bool) $this->q(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'discount_rule' AND column_name = 'priority'",
)->fetchColumn();

if (! $columnExists) {
    $this->q('ALTER TABLE discount_rule ADD COLUMN priority SMALLINT NOT NULL DEFAULT 100 AFTER required_right');
    $this->q('CREATE INDEX IDX_discount_rule_priority ON discount_rule (year, active, priority)');
}

// The full discount goes before the partial one, matching the old chain.
$priorities = [
    'jidlo_zdarma'    => 10,
    'jidlo_se_slevou' => 20,
    // The bonus was checked first, so it is spent before the plain entitlement.
    'tricko_za_bonus'     => 30,
    'dve_tricka_zdarma'   => 40,
    'jedno_tricko_zdarma' => 50,
    // Whole-stay before a single night: both are free, but the broader right should be
    // the one recorded on the purchase.
    'ubytovani_zdarma'               => 60,
    'ubytovani_stredecni_noc_zdarma' => 70,
    'ubytovani_ctvrtecni_noc_zdarma' => 71,
    'ubytovani_patecni_noc_zdarma'   => 72,
    'ubytovani_sobotni_noc_zdarma'   => 73,
    'ubytovani_nedelni_noc_zdarma'   => 74,
    'kostka_zdarma'                  => 80,
    'placka_zdarma'                  => 81,
];

foreach ($priorities as $code => $priority) {
    dbQuery(
        'UPDATE discount_rule SET priority = $0 WHERE code = $1',
        [
            0 => $priority,
            1 => $code,
        ],
    );
}
