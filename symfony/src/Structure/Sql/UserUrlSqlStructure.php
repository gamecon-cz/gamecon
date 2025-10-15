<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserUrl
 */
class UserUrlSqlStructure
{
    /**
     * @see UserUrl
     */
    public const _table = 'uzivatele_url';

    /**
     * @see UserUrl::$id
     */
    public const id_url_uzivatele = 'id_url_uzivatele';

    /**
     * @see UserUrl::$url
     */
    public const url = 'url';

    /**
     * @see UserUrl::$user
     */
    public const id_uzivatele = 'id_uzivatele';
}
