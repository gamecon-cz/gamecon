<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Only a public setting may go into an e-mail, otherwise readers outside the organisation
// could get, say, the suspicious-payment threshold. The default is non-public.
$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
    ADD COLUMN verejne TINYINT(1) NOT NULL DEFAULT 0 AFTER pouze_pro_cteni
SQL,
);

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni_log
    ADD COLUMN verejne TINYINT(1) DEFAULT NULL AFTER vlastni
SQL,
);

// These values are publicly visible on the web today. `zmena_kdy = zmena_kdy` because otherwise Settings
// would show 'last changed a moment ago' for them although the value did not change.
$this->q(<<<SQL
UPDATE systemove_nastaveni
SET verejne = 1,
    zmena_kdy = zmena_kdy
WHERE klic IN ('ROCNIK', 'GC_BEZI_OD', 'GC_BEZI_DO', 'REG_GC_OD', 'REG_GC_DO')
SQL,
);
