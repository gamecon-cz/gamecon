<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Upomínka už dluh nezaokrouhluje na celé koruny, takže log musí udržet i haléře, jinak by neříkal, co bylo
// v odeslaném e-mailu. Dosavadní řádky jsou celé koruny a vejdou se (12 míst pojme i celý rozsah int(11)).
$this->q(<<<'SQL'
ALTER TABLE upominka_dluznika_log
    MODIFY COLUMN dluh decimal(12, 2) NOT NULL
SQL);
