<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Team
 */
class TeamEntityStructure
{
    /**
     * @see Team::$id
     */
    public const id = 'id';

    /**
     * @see Team::$kod
     */
    public const kod = 'kod';

    /**
     * @see Team::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see Team::$limit
     */
    public const limit = 'limit';

    /**
     * @see Team::$zalozen
     */
    public const zalozen = 'zalozen';

    /**
     * @see Team::$verejny
     */
    public const verejny = 'verejny';

    /**
     * @see Team::$zamceny
     */
    public const zamceny = 'zamceny';

    /**
     * @see Team::$expiruje
     */
    public const expiruje = 'expiruje';

    /**
     * @see Team::$kapitan
     */
    public const kapitan = 'kapitan';

    /**
     * @see Team::$aktivity
     */
    public const aktivity = 'aktivity';

    /**
     * @see Team::$clenove
     */
    public const clenove = 'clenove';
}
