<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\GoogleDriveDir
 */
class GoogleDriveDirSqlStructure
{
    /**
     * @see GoogleDriveDir
     */
    public const _table = 'google_drive_dirs';

    /**
     * @see GoogleDriveDir::$id
     */
    public const id = 'id';

    /**
     * @see GoogleDriveDir::$dirId
     */
    public const dir_id = 'dir_id';

    /**
     * @see GoogleDriveDir::$originalName
     */
    public const original_name = 'original_name';

    /**
     * @see GoogleDriveDir::$tag
     */
    public const tag = 'tag';

    /**
     * @see GoogleDriveDir::$owner
     */
    public const user_id = 'user_id';
}
