<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\CostumePhoto
 */
class CostumePhotoSqlStructure
{
    public const _table = 'kostym_foto';

    /**
     * @see CostumePhoto::$id
     */
    public const id = 'id';

    /**
     * @see CostumePhoto::$soubor
     */
    public const soubor = 'soubor';

    /**
     * @see CostumePhoto::$poradi
     */
    public const poradi = 'poradi';

    /**
     * @see CostumePhoto::$costume
     */
    public const id_kostymu = 'id_kostymu';
}
