<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Výchozí znění e-mailů půjčovny kostýmů (Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog::pujcovnaKostymu()).
// INSERT IGNORE, aby opakovaný běh nepřepsal znění upravené v adminu.

$this->q(<<<'SQL'
INSERT IGNORE INTO emailova_sablona (kod, predmet, text)
VALUES ('kostym_rezervace_potvrzena', 'GameCon: kostým %kostym% na aktivitu %aktivita%', 'Ahoj,

na aktivitu %aktivita% (%cas%) máš rezervovaný kostým %kostym%.

V půjčovně kostýmů si ho můžeš vyzvednout nejdřív %vyzvednuti_od% a vrátit ho máš do %vratit_do%. Zrušit rezervaci nebo vyměnit kostým jde na webu do %uzaverka%, potom už jen v půjčovně.

Svoje rezervace kostýmů najdeš na %odkaz%

Tvůj,
Organizační tým GC'),
       ('kostym_rezervace_uvolnena', 'GameCon: rezervace kostýmu %kostym% je zrušená', 'Ahoj,

tvoje přihláška na aktivitu %aktivita% (%cas%) je zrušená, a s ní i rezervace kostýmu %kostym%.

Jestli už kostým máš u sebe, vrať ho prosím do půjčovny kostýmů.

Tvůj,
Organizační tým GC')
SQL);
