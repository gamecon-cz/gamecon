<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Text
 */
class TextSqlStructure
{
    /**
     * @see Text
     */
    public const _table = 'texty';

    /**
     * @see Text::$id
     */
    public const id = 'id';

    /**
     * @see Text::$text
     */
    public const text = 'text';
}
