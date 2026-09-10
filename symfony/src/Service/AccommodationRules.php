<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class AccommodationRules
{
    /**
     * When rooms are short the festival can restrict accommodation to sleeping bags, and a
     * handful of roles keep a real bed anyway. Read by both the grid and the write path, so
     * a hand-made request cannot book what the grid hides.
     */
    public function jenSpacaky(\Uzivatel $legacyUzivatel): bool
    {
        return SystemoveNastaveni::zGlobals()->jeOmezeniUbytovaniPouzeNaSpacaky()
            && ! $this->maPravoNaPostel($legacyUzivatel);
    }

    private function maPravoNaPostel(\Uzivatel $legacyUzivatel): bool
    {
        return $legacyUzivatel->jeVypravec()
            || $legacyUzivatel->jeOrganizator()
            || $legacyUzivatel->jeHerman()
            || $legacyUzivatel->jePartner()
            || $legacyUzivatel->jeInfopultak()
            || $legacyUzivatel->jeZazemi();
    }
}
