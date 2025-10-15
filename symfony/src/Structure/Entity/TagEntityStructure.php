<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Tag
 */
class TagEntityStructure
{
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
    public const categoryTag = 'categoryTag';
}
