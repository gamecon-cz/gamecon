<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// E-mail šéfovi sekce, když se na aktivitu pro starší (štítek „Věk: 15+“) sám přihlásí mladší účastník
// (Aktivita::napisSefoviSekceOMladsimUcastnikovi()). Formát HTML, nové řádky šablony se převedou na <br />.
// INSERT IGNORE, aby opakované spuštění nepřepsalo text upravený v adminu. V textu není apostrof ani zpětné lomítko,
// stačí ho tedy dát do uvozovek.
$this->q(<<<'SQL'
INSERT IGNORE INTO emailova_sablona(kod, predmet, text)
VALUES (
'mladsi_ucastnik_na_aktivite',
'Mladší účastník na aktivitě %aktivita% (od %minimalni_vek% let)',
'Ahoj,

na aktivitu %aktivita% (%cas%), která je od %minimalni_vek% let, se přihlásil účastník, kterému v den aktivity bude teprve %vek%.

Účastník: %ucastnik%, <a href="mailto:%email_ucastnika%">%email_ucastnika%</a>
Vypravěči: %vypraveci%

Přihlásil se sám a před přihlášením viděl upozornění, že se mu ozveš e-mailem a že ho můžeš z aktivity odhlásit. Jestli má výjimku domluvenou, nemusíš nic dělat. Jinak se mu prosím ozvi, případně se domluv s vypravěčem, a když výjimku nedostane, odhlas ho.

Přihlášení na aktivity linie, mladší jsou vyznačení: <a href="%odkaz%">%odkaz%</a>

GameCon'
)
SQL);

// Report přihlášených mladších, než je věk aktivity (MladsiUcastniciAktivit), pro šéfy sekcí.
$this->q(<<<'SQL'
INSERT IGNORE INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
VALUES ('zazemi-a-program-mladsi-nez-vek-aktivity', 'Zázemí & Program: Přihlášení mladší než věk aktivity', 1, 1, 1)
SQL);
