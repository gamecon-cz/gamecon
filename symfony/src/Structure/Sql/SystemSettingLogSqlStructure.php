<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\SystemSettingLog
 */
class SystemSettingLogSqlStructure
{
    /**
     * @see SystemSettingLog
     */
    public const _table = 'systemove_nastaveni_log';

    /**
     * @see SystemSettingLog::$id
     */
    public const id_nastaveni_log = 'id_nastaveni_log';

    /**
     * @see SystemSettingLog::$hodnota
     */
    public const hodnota = 'hodnota';

    /**
     * @see SystemSettingLog::$vlastni
     */
    public const vlastni = 'vlastni';

    /**
     * @see SystemSettingLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see SystemSettingLog::$changedBy
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see SystemSettingLog::$systemSetting
     */
    public const id_nastaveni = 'id_nastaveni';
}
