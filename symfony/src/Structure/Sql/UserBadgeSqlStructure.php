<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserBadge
 */
class UserBadgeSqlStructure
{
    /**
     * @see UserBadge
     */
    public const _table = 'medailonky';

    /**
     * @see UserBadge::$oSobe
     */
    public const o_sobe = 'o_sobe';

    /**
     * @see UserBadge::$drd
     */
    public const drd = 'drd';

    /**
     * @see UserBadge::$user
     */
    public const id_uzivatele = 'id_uzivatele';
}
