<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\SystemSetting
 */
class SystemSettingEntityStructure
{
    /**
     * @see SystemSetting::$id
     */
    public const id = 'id';

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
    public const datovyTyp = 'datovyTyp';

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
    public const zmenaKdy = 'zmenaKdy';

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
    public const pouzeProCteni = 'pouzeProCteni';

    /**
     * @see SystemSetting::$rocnikNastaveni
     */
    public const rocnikNastaveni = 'rocnikNastaveni';
}
