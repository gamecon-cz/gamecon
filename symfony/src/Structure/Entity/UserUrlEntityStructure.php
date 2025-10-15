<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\UserUrl
 */
class UserUrlEntityStructure
{
    /**
     * @see UserUrl::$id
     */
    public const id = 'id';

    /**
     * @see UserUrl::$url
     */
    public const url = 'url';

    /**
     * @see UserUrl::$user
     */
    public const user = 'user';
}
