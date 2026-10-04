<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class AccommodationRules
{
    public const SUNDAY = 4;

    /**
     * Sunday is offered to few, so its night sits under the counter and only these see its column.
     */
    public function maySundayNight(\Uzivatel $legacyUser): bool
    {
        return $legacyUser->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
            || $legacyUser->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
            || $legacyUser->jeOrganizator();
    }

    /**
     * When rooms are short the festival can restrict accommodation to sleeping bags, and a
     * handful of roles keep a real bed anyway. Read by both the grid and the write path, so
     * a hand-made request cannot book what the grid hides.
     */
    public function sleepingBagsOnly(\Uzivatel $legacyUser): bool
    {
        return SystemoveNastaveni::zGlobals()->jeOmezeniUbytovaniPouzeNaSpacaky()
            && ! $this->isEntitledToBed($legacyUser);
    }

    private function isEntitledToBed(\Uzivatel $legacyUser): bool
    {
        return $legacyUser->jeVypravec()
            || $legacyUser->jeOrganizator()
            || $legacyUser->jeHerman()
            || $legacyUser->jePartner()
            || $legacyUser->jeInfopultak()
            || $legacyUser->jeZazemi();
    }
}
