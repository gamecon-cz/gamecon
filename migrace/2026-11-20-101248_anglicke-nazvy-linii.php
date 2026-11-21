<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English names and short descriptions of the activity lines for the English website (/en/).
// NULL = not translated, the website shows the Czech text (see TypAktivity::getNazevVJazyce()).
// The URL (url_typu_mn) stays Czech, the English website keeps Czech addresses (/en/larpy).
$this->q(<<<'SQL'
ALTER TABLE akce_typy
    ADD COLUMN typ_1p_en VARCHAR(255) NULL DEFAULT NULL COMMENT 'anglický název v jednotném čísle, NULL = jen česky'
        AFTER typ_1p,
    ADD COLUMN typ_1pmn_en VARCHAR(255) NULL DEFAULT NULL COMMENT 'anglický název linie, NULL = jen česky'
        AFTER typ_1pmn,
    ADD COLUMN popis_kratky_en VARCHAR(255) NULL DEFAULT NULL COMMENT 'anglický krátký popis, NULL = jen česky'
        AFTER popis_kratky
SQL,
);

// Interní linie (bez typu, brigádnická) se návštěvníkům neukazují, ty se nepřekládají.
$preklady = [
    1   => ['board game tournament', 'board game tournaments', 'Your favourite board games or brand new ones! As in every proper tournament, you can win prizes here too – maybe even the very game you are playing!'],
    2   => ['larp', 'larps', 'Become a character in a dramatic or comic story and live every moment to the full, as if the world around you did not exist.'],
    3   => ['talk', 'talks', 'Playing is fun, sure, but knowledge is power! Come and listen to interesting speakers of all kinds.'],
    4   => ['RPG', 'RPG', 'All you need to travel to another world and live through a thrilling adventure are dice and your own imagination.'],
    5   => ['workshop', 'workshops', ''],
    6   => ['wargaming', 'wargaming', 'Armies of miniatures on the battlefield! Come and play a proper battle set in your favourite world!'],
    7   => ['bonus', 'action games and bonuses', 'Tired of sitting at a table all the time? Then we have active games and other fun programme just for you.'],
    8   => ['Legends of the Adventurers’ Club', 'Legends of the Adventurers’ Club', 'Steam, magic and fairies – that is the world of Tales of the Empire. Piece together a story with other parties that The Times will write about!'],
    9   => ['DrD Championship', 'DrD Championship', 'You probably know Dračí doupě, the Czech classic RPG. But do you know its championship? Gather a party and win this contest in three competitive rounds.'],
    10  => ['organisational help', 'organisational help', ''],
    11  => ['epic board game', 'epic board games', 'Fancy a big strategy game or an atmospheric one? You will surely find one here!'],
    12  => ['side programme', 'side programme', 'Still not enough? No problem – besides everything else we have a concert and a few parties too.'],
    13  => ['board game library', 'board game library', 'The board game library is completely free and open almost all day. You will also meet organisers and publishers here who will explain the games to you.'],
    14  => ['D&D tournament', 'D&D tournament', 'Test your detective and role-playing skills, have fun and create a story with your party that will lead you all to victory.'],
    103 => ['Celohra', 'Celohra', 'The only game that never gets old! Every year it is written for the current GameCon theme, with a new story and new game mechanics. Play while there is time!'],
];
$retezec = fn (string $text): string => $text === ''
    ? 'NULL'
    : "'" . $this->connection->real_escape_string($text) . "'";
foreach ($preklady as $idTypu => [$jednotneCislo, $nazev, $popisKratky]) {
    $this->q(<<<SQL
UPDATE akce_typy
SET typ_1p_en = {$retezec($jednotneCislo)},
    typ_1pmn_en = {$retezec($nazev)},
    popis_kratky_en = {$retezec($popisKratky)}
WHERE id_typu = {$idTypu}
SQL,
    );
}
