<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English content (stranky.obsah_en) of the photo galleries of the lines (`*/fotogalerie`), so that the button
// on the English line page (/en/rpg) and the gallery itself are in English. A gallery is a heading and Instagram
// embeds; its English content is a copy of the Czech one with the visible texts translated (the heading and
// the placeholder of a post shown until Instagram's script replaces it). It is made by REPLACE() from the current
// Czech content, so it works on any database whatever posts its galleries show.
// Only a gallery with the usual heading (BINARY: REPLACE() is case-sensitive, the collation is not): another one
// would get English content that is still Czech. A gallery that already has English content keeps it.
// Galleries are updated in Czech, whoever changes one has to change its English content too (docs/generated/jazyk-webu.md).
$this->q(<<<'SQL'
UPDATE stranky
SET obsah_en = REPLACE(REPLACE(REPLACE(obsah,
        '# Fotogalerie', '# Photo gallery'),
        'Zobrazit příspěvek na Instagramu', 'View this post on Instagram'),
        'Příspěvek sdílený GameCon', 'A post shared by GameCon')
WHERE url_stranky LIKE '%/fotogalerie'
    AND obsah LIKE BINARY '# Fotogalerie%'
    AND (obsah_en IS NULL OR TRIM(obsah_en) = '')
SQL,
);
