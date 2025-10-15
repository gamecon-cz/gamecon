<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\SystemSettingLog
 */
class SystemSettingLogEntityStructure
{
    /**
     * @see SystemSettingLog::$id
     */
    public const id = 'id';

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
    public const changedBy = 'changedBy';

    /**
     * @see SystemSettingLog::$systemSetting
     */
    public const systemSetting = 'systemSetting';
}
