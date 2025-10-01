<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\Tag.
 */
class TagSqlStructure
{
    public const ID = 'id';
    public const NAZEV = 'nazev';
    public const POZNAMKA = 'poznamka';
    public const KATEGORIE_TAG = 'id_kategorie_tagu';
}
