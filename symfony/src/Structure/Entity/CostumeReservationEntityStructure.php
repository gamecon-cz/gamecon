<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\CostumeReservation
 */
class CostumeReservationEntityStructure
{
    /**
     * @see CostumeReservation::$id
     */
    public const id = 'id';

    /**
     * @see CostumeReservation::$idAkce
     */
    public const idAkce = 'idAkce';

    /**
     * @see CostumeReservation::$idUzivatele
     */
    public const idUzivatele = 'idUzivatele';

    /**
     * @see CostumeReservation::$rezervovano
     */
    public const rezervovano = 'rezervovano';

    /**
     * @see CostumeReservation::$costume
     */
    public const costume = 'costume';

    /**
     * @see CostumeReservation::$rezervoval
     */
    public const rezervoval = 'rezervoval';
}
