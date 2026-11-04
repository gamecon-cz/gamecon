<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Dluh v upomínce může mít haléře (250,60), a k nim se „korun“ nehodí, proto se za částku píše „Kč“. Přepisuje se
// výchozí znění (`emailova_sablona`) i vlastní znění každého ročníku, jen celé slovo „korun“ (ne „korunách“);
// `zmeneno_kdy = zmeneno_kdy` zařídí, aby to nevypadalo jako úprava od CFO. Kolace nerozlišuje velikost písmen,
// proto se `%dluh%` přenáší zachycenou skupinou, ne psaný znovu.
$this->q(<<<'SQL'
UPDATE emailova_sablona
SET text        = REGEXP_REPLACE(text, '(%dluh%)(\\s+)korun(?!\\p{L})', '\\1\\2Kč'),
    zmeneno_kdy = zmeneno_kdy
SQL);

$this->q(<<<'SQL'
UPDATE upominka_vlastni_zneni
SET text        = REGEXP_REPLACE(text, '(%dluh%)(\\s+)korun(?!\\p{L})', '\\1\\2Kč'),
    zmeneno_kdy = zmeneno_kdy
SQL);
