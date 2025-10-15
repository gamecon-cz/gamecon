<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\UserMergeLog
 */
class UserMergeLogEntityStructure
{
    /**
     * @see UserMergeLog::$id
     */
    public const id = 'id';

    /**
     * @see UserMergeLog::$idSmazanehoUzivatele
     */
    public const idSmazanehoUzivatele = 'idSmazanehoUzivatele';

    /**
     * @see UserMergeLog::$idNovehoUzivatele
     */
    public const idNovehoUzivatele = 'idNovehoUzivatele';

    /**
     * @see UserMergeLog::$zustatekSmazanehoPuvodne
     */
    public const zustatekSmazanehoPuvodne = 'zustatekSmazanehoPuvodne';

    /**
     * @see UserMergeLog::$zustatekNovehoPuvodne
     */
    public const zustatekNovehoPuvodne = 'zustatekNovehoPuvodne';

    /**
     * @see UserMergeLog::$emailSmazaneho
     */
    public const emailSmazaneho = 'emailSmazaneho';

    /**
     * @see UserMergeLog::$emailNovehoPuvodne
     */
    public const emailNovehoPuvodne = 'emailNovehoPuvodne';

    /**
     * @see UserMergeLog::$zustatekNovehoAktualne
     */
    public const zustatekNovehoAktualne = 'zustatekNovehoAktualne';

    /**
     * @see UserMergeLog::$emailNovehoAktualne
     */
    public const emailNovehoAktualne = 'emailNovehoAktualne';

    /**
     * @see UserMergeLog::$kdy
     */
    public const kdy = 'kdy';
}
