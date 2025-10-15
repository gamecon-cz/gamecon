<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\ActivityImport
 */
class ActivityImportEntityStructure
{
    /**
     * @see ActivityImport::$id
     */
    public const id = 'id';

    /**
     * @see ActivityImport::$googleSheetId
     */
    public const googleSheetId = 'googleSheetId';

    /**
     * @see ActivityImport::$cas
     */
    public const cas = 'cas';

    /**
     * @see ActivityImport::$user
     */
    public const user = 'user';
}
