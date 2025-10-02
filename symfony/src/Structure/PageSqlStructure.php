<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\Page
 * SQL table `stranky`
 */
class PageSqlStructure
{
    public const ID_STRANKY = 'id_stranky';
    public const URL_STRANKY = 'url_stranky';
    public const OBSAH = 'obsah';
    public const PORADI = 'poradi';
}
