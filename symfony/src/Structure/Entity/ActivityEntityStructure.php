<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Activity
 */
class ActivityEntityStructure
{
    /**
     * @see Activity::$id
     */
    public const id = 'id';

    /**
     * @see Activity::$nazevAkce
     */
    public const nazevAkce = 'nazevAkce';

    /**
     * @see Activity::$urlAkce
     */
    public const urlAkce = 'urlAkce';

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
    public const kapacitaF = 'kapacitaF';

    /**
     * @see Activity::$kapacitaM
     */
    public const kapacitaM = 'kapacitaM';

    /**
     * @see Activity::$cena
     */
    public const cena = 'cena';

    /**
     * @see Activity::$bezSlevy
     */
    public const bezSlevy = 'bezSlevy';

    /**
     * @see Activity::$nedavaBonus
     */
    public const nedavaBonus = 'nedavaBonus';

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
    public const teamMin = 'teamMin';

    /**
     * @see Activity::$teamMax
     */
    public const teamMax = 'teamMax';

    /**
     * @see Activity::$teamKapacita
     */
    public const teamKapacita = 'teamKapacita';

    /**
     * @see Activity::$teamNazev
     */
    public const teamNazev = 'teamNazev';

    /**
     * @see Activity::$forTeamLockedAt
     */
    public const forTeamLockedAt = 'forTeamLockedAt';

    /**
     * @see Activity::$description
     */
    public const description = 'description';

    /**
     * @see Activity::$shortDescription
     */
    public const shortDescription = 'shortDescription';

    /**
     * @see Activity::$vybaveni
     */
    public const vybaveni = 'vybaveni';

    /**
     * @see Activity::$teamLimit
     */
    public const teamLimit = 'teamLimit';

    /**
     * @see Activity::$probehlaKorekce
     */
    public const probehlaKorekce = 'probehlaKorekce';

    /**
     * @see Activity::$activityInstance
     */
    public const activityInstance = 'activityInstance';

    /**
     * @see Activity::$location
     */
    public const location = 'location';

    /**
     * @see Activity::$type
     */
    public const type = 'type';

    /**
     * @see Activity::$status
     */
    public const status = 'status';

    /**
     * @see Activity::$forTeamLockedBy
     */
    public const forTeamLockedBy = 'forTeamLockedBy';

    /**
     * @see Activity::$activityTags
     */
    public const activityTags = 'activityTags';
}
