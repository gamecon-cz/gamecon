<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityType
 */
class ActivityTypeSqlStructure
{
    /**
     * @see ActivityType
     */
    public const _table = 'akce_typy';

    /**
     * @see ActivityType::$id
     */
    public const id_typu = 'id_typu';

    /**
     * @see ActivityType::$typ1p
     */
    public const typ_1p = 'typ_1p';

    /**
     * @see ActivityType::$typ1pmn
     */
    public const typ_1pmn = 'typ_1pmn';

    /**
     * @see ActivityType::$urlTypuMn
     */
    public const url_typu_mn = 'url_typu_mn';

    /**
     * @see ActivityType::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see ActivityType::$mailNeucast
     */
    public const mail_neucast = 'mail_neucast';

    /**
     * @see ActivityType::$popisKratky
     */
    public const popis_kratky = 'popis_kratky';

    /**
     * @see ActivityType::$aktivni
     */
    public const aktivni = 'aktivni';

    /**
     * @see ActivityType::$zobrazitVMenu
     */
    public const zobrazit_v_menu = 'zobrazit_v_menu';

    /**
     * @see ActivityType::$kodTypu
     */
    public const kod_typu = 'kod_typu';

    /**
     * @see ActivityType::$pageAbout
     */
    public const stranka_o = 'stranka_o';
}
