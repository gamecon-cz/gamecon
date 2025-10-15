<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\ActivityImport
 */
class ActivityImportSqlStructure
{
    /**
     * @see ActivityImport
     */
    public const _table = 'akce_import';

    /**
     * @see ActivityImport::$id
     */
    public const id_akce_import = 'id_akce_import';

    /**
     * @see ActivityImport::$googleSheetId
     */
    public const google_sheet_id = 'google_sheet_id';

    /**
     * @see ActivityImport::$cas
     */
    public const cas = 'cas';

    /**
     * @see ActivityImport::$user
     */
    public const id_uzivatele = 'id_uzivatele';
}
