<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Activity
 */
class ActivitySqlStructure
{
    /**
     * @see Activity
     */
    public const _table = 'akce_seznam';

    /**
     * @see Activity::$id
     */
    public const id_akce = 'id_akce';

    /**
     * @see Activity::$nazevAkce
     */
    public const nazev_akce = 'nazev_akce';

    /**
     * @see Activity::$urlAkce
     */
    public const url_akce = 'url_akce';

    /**
     * @see Activity::$zacatek
     */
    public const zacatek = 'zacatek';

    /**
     * @see Activity::$konec
     */
    public const konec = 'konec';

    /**
     * @see Activity::$kapacita
     */
    public const kapacita = 'kapacita';

    /**
     * @see Activity::$kapacitaF
     */
    public const kapacita_f = 'kapacita_f';

    /**
     * @see Activity::$kapacitaM
     */
    public const kapacita_m = 'kapacita_m';

    /**
     * @see Activity::$cena
     */
    public const cena = 'cena';

    /**
     * @see Activity::$bezSlevy
     */
    public const bez_slevy = 'bez_slevy';

    /**
     * @see Activity::$nedavaBonus
     */
    public const nedava_bonus = 'nedava_bonus';

    /**
     * @see Activity::$dite
     */
    public const dite = 'dite';

    /**
     * @see Activity::$rok
     */
    public const rok = 'rok';

    /**
     * @see Activity::$teamova
     */
    public const teamova = 'teamova';

    /**
     * @see Activity::$teamMin
     */
    public const team_min = 'team_min';

    /**
     * @see Activity::$teamMax
     */
    public const team_max = 'team_max';

    /**
     * @see Activity::$teamKapacita
     */
    public const team_kapacita = 'team_kapacita';

    /**
     * @see Activity::$teamNazev
     */
    public const team_nazev = 'team_nazev';

    /**
     * @see Activity::$forTeamLockedAt
     */
    public const zamcel_cas = 'zamcel_cas';

    /**
     * @see Activity::$shortDescription
     */
    public const popis_kratky = 'popis_kratky';

    /**
     * @see Activity::$vybaveni
     */
    public const vybaveni = 'vybaveni';

    /**
     * @see Activity::$teamLimit
     */
    public const team_limit = 'team_limit';

    /**
     * @see Activity::$probehlaKorekce
     */
    public const probehla_korekce = 'probehla_korekce';

    /**
     * @see Activity::$activityInstance
     */
    public const patri_pod = 'patri_pod';

    /**
     * @see Activity::$location
     */
    public const lokace = 'lokace';

    /**
     * @see Activity::$type
     */
    public const typ = 'typ';

    /**
     * @see Activity::$status
     */
    public const stav = 'stav';

    /**
     * @see Activity::$forTeamLockedBy
     */
    public const zamcel = 'zamcel';

    /**
     * @see Activity::$description
     */
    public const popis = 'popis';
}
