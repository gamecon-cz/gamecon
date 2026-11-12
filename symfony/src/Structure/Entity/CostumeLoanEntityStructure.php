<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\CostumeLoan
 */
class CostumeLoanEntityStructure
{
    /**
     * @see CostumeLoan::$id
     */
    public const id = 'id';

    /**
     * @see CostumeLoan::$vydano
     */
    public const vydano = 'vydano';

    /**
     * @see CostumeLoan::$vraceno
     */
    public const vraceno = 'vraceno';

    /**
     * @see CostumeLoan::$stavPriVraceni
     */
    public const stavPriVraceni = 'stavPriVraceni';

    /**
     * @see CostumeLoan::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see CostumeLoan::$costume
     */
    public const costume = 'costume';

    /**
     * @see CostumeLoan::$reservation
     */
    public const reservation = 'reservation';

    /**
     * @see CostumeLoan::$activity
     */
    public const activity = 'activity';

    /**
     * @see CostumeLoan::$uzivatel
     */
    public const uzivatel = 'uzivatel';

    /**
     * @see CostumeLoan::$vydal
     */
    public const vydal = 'vydal';

    /**
     * @see CostumeLoan::$prijal
     */
    public const prijal = 'prijal';
}
