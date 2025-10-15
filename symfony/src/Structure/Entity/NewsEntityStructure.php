<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\News
 */
class NewsEntityStructure
{
    /**
     * @see News::$id
     */
    public const id = 'id';

    /**
     * @see News::$typ
     */
    public const typ = 'typ';

    /**
     * @see News::$vydat
     */
    public const vydat = 'vydat';

    /**
     * @see News::$url
     */
    public const url = 'url';

    /**
     * @see News::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see News::$autor
     */
    public const autor = 'autor';

    /**
     * @see News::$text
     */
    public const text = 'text';
}
