<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Tag
 */
class TagSqlStructure
{
    /**
     * @see Tag
     */
    public const _table = 'sjednocene_tagy';

    /**
     * @see Tag::$id
     */
    public const id = 'id';

    /**
     * @see Tag::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Tag::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see Tag::$categoryTag
     */
    public const id_kategorie_tagu = 'id_kategorie_tagu';
}
