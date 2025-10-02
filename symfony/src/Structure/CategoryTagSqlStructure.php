<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\CategoryTag
 * SQL table `kategorie_sjednocenych_tagu`
 */
class CategoryTagSqlStructure
{
    public const ID = 'id';
    public const NAZEV = 'nazev';
    public const PORADI = 'poradi';
    public const HLAVNI_KATEGORIE = 'id_hlavni_kategorie';
}
