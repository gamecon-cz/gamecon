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

    // Verification
    case ZKONTROLOVANE_UDAJE = 'ZKONTROLOVANE_UDAJE';

    // Participation tracking
    case PRIHLASEN = 'PRIHLASEN';
    case PRITOMEN = 'PRITOMEN';
    case ODJEL = 'ODJEL';

    /**
     * Does this role grant access to organizer-reserved stock?
     */
    public function isOrganizer(): bool
    {
        return in_array($this, self::organizerMeanings(), true);
    }

    /**
     * All role meanings that grant organizer-level access (reserved stock, org discounts)
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
            self::ORGANIZATOR_ZDARMA   => 'Organizátor (zdarma)',
            self::PUL_ORG_UBYTKO       => 'Půl-org (ubytko)',
            self::PUL_ORG_TRICKO       => 'Půl-org (tričko)',
            self::MINI_ORG             => 'Mini-org',
            self::CESTNY_ORGANIZATOR   => 'Čestný organizátor',
            self::CFO                  => 'CFO',
            self::ADMIN                => 'Admin',
            self::VYPRAVECSKA_SKUPINA  => 'Vypravěčská skupina',
            self::CLEN_RADY            => 'Člen rady',
            self::SEF_INFOPULTU        => 'Šéf infopultu',
            self::SEF_PROGRAMU         => 'Šéf programu',
            self::KOREKTOR             => 'Korektor',
            self::SPRAVCE_PARTNERU     => 'Správce partnerů',
            self::BRIGADNIK            => 'Brigádník',
            self::HERMAN               => 'Heřman',
            self::INFOPULT             => 'Infopult',
            self::NEODHLASOVAT         => 'Neodhlašovat',
            self::PARTNER              => 'Partner',
            self::STREDECNI_NOC_ZDARMA => 'Středeční noc zdarma',
            self::CTVRTECNI_NOC_ZDARMA => 'Čtvrteční noc zdarma',
            self::PATECNI_NOC_ZDARMA   => 'Páteční noc zdarma',
            self::SOBOTNI_NOC_ZDARMA   => 'Sobotní noc zdarma',
            self::NEDELNI_NOC_ZDARMA   => 'Nedělní noc zdarma',
            self::VYPRAVEC             => 'Vypravěč',
            self::ZAZEMI               => 'Zázemí',
            self::ZKONTROLOVANE_UDAJE  => 'Zkontrolované údaje',
            self::PRIHLASEN            => 'Přihlášen',
            self::PRITOMEN             => 'Přítomen',
            self::ODJEL                => 'Odjel',
        };
    }
}
