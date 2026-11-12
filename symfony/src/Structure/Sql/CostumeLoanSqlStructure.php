<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\CostumeLoan
 */
class CostumeLoanSqlStructure
{
    public const _table = 'kostym_vydej';

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
    public const stav_pri_vraceni = 'stav_pri_vraceni';

    /**
     * @see CostumeLoan::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see CostumeLoan::$costume
     */
    public const id_kostymu = 'id_kostymu';

    /**
     * @see CostumeLoan::$reservation
     */
    public const id_rezervace = 'id_rezervace';

    /**
     * @see CostumeLoan::$activity
     */
    public const id_akce = 'id_akce';

    /**
     * @see CostumeLoan::$uzivatel
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see CostumeLoan::$vydal
     */
    public const vydal = 'vydal';

    /**
     * @see CostumeLoan::$prijal
     */
    public const prijal = 'prijal';
}
