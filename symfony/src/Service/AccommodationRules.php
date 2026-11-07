<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\UserRoleRepository;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class AccommodationRules
{
    public const SUNDAY = 4;

    /**
     * The roles legacy Uzivatel::jeOrganizator() recognises. RoleMeaning::organizerMeanings() is
     * wider and the accommodation rules do not follow it, which has yet to be decided.
     *
     * @see \Gamecon\Role\Role::dejIdckaRoliSOrganizatory()
     */
    private const array LEGACY_ORGANIZER_MEANINGS = [
        RoleMeaning::ORGANIZATOR_ZDARMA,
        RoleMeaning::PUL_ORG_UBYTKO,
        RoleMeaning::PUL_ORG_TRICKO,
        RoleMeaning::MINI_ORG,
        RoleMeaning::PAUZUJICI_FULL_ORG,
    ];

    private const array ENTITLED_TO_BED_MEANINGS = [
        ...self::LEGACY_ORGANIZER_MEANINGS,
        RoleMeaning::VYPRAVEC,
        RoleMeaning::HERMAN,
        RoleMeaning::PARTNER,
        RoleMeaning::INFOPULT,
        RoleMeaning::ZAZEMI,
    ];

    public function __construct(
        private readonly UserPermissions $permissions,
        private readonly UserRoleRepository $userRoleRepository,
    ) {
    }

    /**
     * Sunday is offered to few, so its night sits under the counter and only these see its column.
     */
    public function maySundayNight(User $user, int $year): bool
    {
        return $this->permissions->hasAny($user, [Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET, Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA], $year)
            || $this->userRoleRepository->holdsAnyMeaning($user, self::LEGACY_ORGANIZER_MEANINGS, $year);
    }

    /**
     * When rooms are short the festival can restrict accommodation to sleeping bags, and a
     * handful of roles keep a real bed anyway. Read by both the grid and the write path, so
     * a hand-made request cannot book what the grid hides.
     */
    public function sleepingBagsOnly(User $user, int $year): bool
    {
        return SystemoveNastaveni::zGlobals()->jeOmezeniUbytovaniPouzeNaSpacaky()
            && ! $this->isEntitledToBed($user, $year);
    }

    private function isEntitledToBed(User $user, int $year): bool
    {
        return $this->userRoleRepository->holdsAnyMeaning($user, self::ENTITLED_TO_BED_MEANINGS, $year);
    }
}
