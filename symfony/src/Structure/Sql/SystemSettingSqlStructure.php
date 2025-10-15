<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\SystemSetting
 */
class SystemSettingSqlStructure
{
    /**
     * @see SystemSetting
     */
    public const _table = 'systemove_nastaveni';

    /**
     * @see SystemSetting::$id
     */
    public const id_nastaveni = 'id_nastaveni';

    /**
     * @see SystemSetting::$klic
     */
    public const klic = 'klic';

    /**
     * @see SystemSetting::$hodnota
     */
    public const hodnota = 'hodnota';

    /**
     * @see SystemSetting::$vlastni
     */
    public const vlastni = 'vlastni';

    /**
     * @see SystemSetting::$datovyTyp
     */
    public const datovy_typ = 'datovy_typ';

    /**
     * @see SystemSetting::$nazev
     */
    public const nazev = 'nazev';

    /**
     * @see SystemSetting::$popis
     */
    public const popis = 'popis';

    /**
     * @see SystemSetting::$zmenaKdy
     */
    public const zmena_kdy = 'zmena_kdy';

    /**
     * @see SystemSetting::$skupina
     */
    public const skupina = 'skupina';

    /**
     * @see SystemSetting::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see SystemSetting::$pouzeProCteni
     */
    public const pouze_pro_cteni = 'pouze_pro_cteni';

    /**
     * @see SystemSetting::$rocnikNastaveni
     */
    public const rocnik_nastaveni = 'rocnik_nastaveni';
}
