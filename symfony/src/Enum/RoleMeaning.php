<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Role meaning (vyznam_role) — semantic significance of a role.
 *
 * Maps to `role_seznam.vyznam_role` column.
 * Legacy constants: @see \Gamecon\Role\Role::VYZNAM_*
 */
enum RoleMeaning: string
{
    // Organizer tiers (permanent roles)
    case ORGANIZATOR_ZDARMA = 'ORGANIZATOR_ZDARMA';
    case PUL_ORG_UBYTKO = 'PUL_ORG_UBYTKO';
    case PUL_ORG_TRICKO = 'PUL_ORG_TRICKO';
    case MINI_ORG = 'MINI_ORG';
    case CESTNY_ORGANIZATOR = 'CESTNY_ORGANIZATOR';

    // Staff (permanent roles)
    case CFO = 'CFO';
    case ADMIN = 'ADMIN';
    case VYPRAVECSKA_SKUPINA = 'VYPRAVECSKA_SKUPINA';
    case CLEN_RADY = 'CLEN_RADY';
    case SEF_INFOPULTU = 'SEF_INFOPULTU';
    case SEF_PROGRAMU = 'SEF_PROGRAMU';
    case KOREKTOR = 'KOREKTOR';
    case SPRAVCE_PARTNERU = 'SPRAVCE_PARTNERU';
    case PAUZUJICI_FULL_ORG = 'PAUZUJICI_FULL_ORG';
    case DEV = 'DEV';

    // Year-specific roles
    case BRIGADNIK = 'BRIGADNIK';
    case HERMAN = 'HERMAN';
    case INFOPULT = 'INFOPULT';
    case NEODHLASOVAT = 'NEODHLASOVAT';
    case PARTNER = 'PARTNER';
    case STREDECNI_NOC_ZDARMA = 'STREDECNI_NOC_ZDARMA';
    case CTVRTECNI_NOC_ZDARMA = 'CTVRTECNI_NOC_ZDARMA';
    case PATECNI_NOC_ZDARMA = 'PATECNI_NOC_ZDARMA';
    case SOBOTNI_NOC_ZDARMA = 'SOBOTNI_NOC_ZDARMA';
    case NEDELNI_NOC_ZDARMA = 'NEDELNI_NOC_ZDARMA';
    case VYPRAVEC = 'VYPRAVEC';
    case ZAZEMI = 'ZAZEMI';
    case PREPINANI_NA_UZIVATELE = 'PREPINANI_NA_UZIVATELE';
    case JEDNA_AKTIVITA_ZDARMA = 'JEDNA_AKTIVITA_ZDARMA';

    // Verification
    case ZKONTROLOVANE_UDAJE = 'ZKONTROLOVANE_UDAJE';

    // Participation tracking
    case PRIHLASEN = 'PRIHLASEN';
    case PRITOMEN = 'PRITOMEN';
    case ODJEL = 'ODJEL';

    /**
     * Does this role grant access to organizer-reserved stock? That is its only
     * effect: ProductVariant::getAvailableQuantity() and CapacityManager::purchase()
     * subtract reserved_for_organizers for everyone else. Discounts do not go through
     * here — DiscountCalculator matches product_discount.role directly.
     */
    public function isOrganizer(): bool
    {
        return in_array($this, self::organizerMeanings(), true);
    }

    /**
     * All role meanings that grant access to organizer-reserved stock
     *
     * @return self[]
     */
    public static function organizerMeanings(): array
    {
        return [
            self::ORGANIZATOR_ZDARMA,
            self::PUL_ORG_UBYTKO,
            self::PUL_ORG_TRICKO,
            self::MINI_ORG,
            self::CESTNY_ORGANIZATOR,
            self::PAUZUJICI_FULL_ORG,
            self::CFO,
            self::ADMIN,
            self::VYPRAVECSKA_SKUPINA,
            self::CLEN_RADY,
            self::SEF_INFOPULTU,
            self::SEF_PROGRAMU,
            self::VYPRAVEC,
            self::ZAZEMI,
            self::BRIGADNIK,
        ];
    }

    /**
     * Check if any of the given meanings grants organizer access
     *
     * @param self[] $meanings
     */
    public static function anyIsOrganizer(array $meanings): bool
    {
        foreach ($meanings as $meaning) {
            if ($meaning->isOrganizer()) {
                return true;
            }
        }

        return false;
    }

    public function label(): string
    {
        return match ($this) {
            self::ORGANIZATOR_ZDARMA     => 'Organizátor (zdarma)',
            self::PUL_ORG_UBYTKO         => 'Půl-org (ubytko)',
            self::PUL_ORG_TRICKO         => 'Půl-org (tričko)',
            self::MINI_ORG               => 'Mini-org',
            self::CESTNY_ORGANIZATOR     => 'Čestný organizátor',
            self::CFO                    => 'CFO',
            self::ADMIN                  => 'Admin',
            self::VYPRAVECSKA_SKUPINA    => 'Vypravěčská skupina',
            self::CLEN_RADY              => 'Člen rady',
            self::SEF_INFOPULTU          => 'Šéf infopultu',
            self::SEF_PROGRAMU           => 'Šéf programu',
            self::KOREKTOR               => 'Korektor',
            self::SPRAVCE_PARTNERU       => 'Správce partnerů',
            self::PAUZUJICI_FULL_ORG     => 'Pauzující Full-org',
            self::DEV                    => 'Dev',
            self::BRIGADNIK              => 'Brigádník',
            self::HERMAN                 => 'Heřman',
            self::INFOPULT               => 'Infopult',
            self::NEODHLASOVAT           => 'Neodhlašovat',
            self::PARTNER                => 'Partner',
            self::STREDECNI_NOC_ZDARMA   => 'Středeční noc zdarma',
            self::CTVRTECNI_NOC_ZDARMA   => 'Čtvrteční noc zdarma',
            self::PATECNI_NOC_ZDARMA     => 'Páteční noc zdarma',
            self::SOBOTNI_NOC_ZDARMA     => 'Sobotní noc zdarma',
            self::NEDELNI_NOC_ZDARMA     => 'Nedělní noc zdarma',
            self::VYPRAVEC               => 'Vypravěč',
            self::ZAZEMI                 => 'Zázemí',
            self::PREPINANI_NA_UZIVATELE => 'Přepínání na uživatele',
            self::JEDNA_AKTIVITA_ZDARMA  => 'Jedna aktivita zdarma',
            self::ZKONTROLOVANE_UDAJE    => 'Zkontrolované údaje',
            self::PRIHLASEN              => 'Přihlášen',
            self::PRITOMEN               => 'Přítomen',
            self::ODJEL                  => 'Odjel',
        };
    }
}
