<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\UserMergeLog
 */
class UserMergeLogSqlStructure
{
    /**
     * @see UserMergeLog
     */
    public const _table = 'uzivatele_slucovani_log';

    /**
     * @see UserMergeLog::$id
     */
    public const id = 'id';

    /**
     * @see UserMergeLog::$idSmazanehoUzivatele
     */
    public const id_smazaneho_uzivatele = 'id_smazaneho_uzivatele';

    /**
     * @see UserMergeLog::$idNovehoUzivatele
     */
    public const id_noveho_uzivatele = 'id_noveho_uzivatele';

    /**
     * @see UserMergeLog::$zustatekSmazanehoPuvodne
     */
    public const zustatek_smazaneho_puvodne = 'zustatek_smazaneho_puvodne';

    /**
     * @see UserMergeLog::$zustatekNovehoPuvodne
     */
    public const zustatek_noveho_puvodne = 'zustatek_noveho_puvodne';

    /**
     * @see UserMergeLog::$emailSmazaneho
     */
    public const email_smazaneho = 'email_smazaneho';

    /**
     * @see UserMergeLog::$emailNovehoPuvodne
     */
    public const email_noveho_puvodne = 'email_noveho_puvodne';

    /**
     * @see UserMergeLog::$zustatekNovehoAktualne
     */
    public const zustatek_noveho_aktualne = 'zustatek_noveho_aktualne';

    /**
     * @see UserMergeLog::$emailNovehoAktualne
     */
    public const email_noveho_aktualne = 'email_noveho_aktualne';

    /**
     * @see UserMergeLog::$kdy
     */
    public const kdy = 'kdy';
}
