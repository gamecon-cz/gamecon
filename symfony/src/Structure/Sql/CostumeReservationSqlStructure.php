<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\CostumeReservation
 */
class CostumeReservationSqlStructure
{
    public const _table = 'kostym_rezervace';

    /**
     * @see CostumeReservation::$id
     */
    public const id = 'id';

    /**
     * @see CostumeReservation::$idAkce
     */
    public const id_akce = 'id_akce';

    /**
     * @see CostumeReservation::$idUzivatele
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see CostumeReservation::$rezervovano
     */
    public const rezervovano = 'rezervovano';

    /**
     * @see CostumeReservation::$costume
     */
    public const id_kostymu = 'id_kostymu';

    /**
     * @see CostumeReservation::$rezervoval
     */
    public const rezervoval = 'rezervoval';
}
