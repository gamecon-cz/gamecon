<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\GoogleDriveDir
 */
class GoogleDriveDirEntityStructure
{
    /**
     * @see GoogleDriveDir::$id
     */
    public const id = 'id';

    /**
     * @see GoogleDriveDir::$dirId
     */
    public const dirId = 'dirId';

    /**
     * @see GoogleDriveDir::$originalName
     */
    public const originalName = 'originalName';

    /**
     * @see GoogleDriveDir::$tag
     */
    public const tag = 'tag';

    /**
     * @see GoogleDriveDir::$owner
     */
    public const owner = 'owner';
}
