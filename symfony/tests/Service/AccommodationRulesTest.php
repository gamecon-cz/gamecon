<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Enum\RoleTypeEnum;
use App\Service\AccommodationRules;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Legacy reads roles through the platne_role_uzivatelu view and recognises five organizer
 * roles, fewer than RoleMeaning::organizerMeanings(). The new layer has to answer the same
 * for every kind of role, or the Sunday night and the bed would go to a different set of people.
 */
class AccommodationRulesTest extends AbstractDatabaseKernelTestCase
{
    private const int SUNDAY_NIGHT_OFFER_ROLE = -990001;
    private const int SUNDAY_NIGHT_FREE_ROLE = -990002;
    private const int LAST_YEAR_SUNDAY_NIGHT_ROLE = -990003;

    /**
     * @return array<string, array{roles: list<array{id: int, year: int, type: string, meaning: string, rights?: list<int>}>, entitledToBed: bool, maySundayNight: bool}>
     */
    public static function roleScenarios(): array
    {
        $yearly = static fn (int $base, int $year, string $meaning): array => [
            'id'      => Role::idRocnikoveRole($base, $year),
            'year'    => $year,
            'type'    => RoleTypeEnum::YEARLY->value,
            'meaning' => $meaning,
        ];
        $permanent = static fn (int $id, string $meaning): array => [
            'id'      => $id,
            'year'    => Role::JAKYKOLI_ROCNIK,
            'type'    => RoleTypeEnum::PERMANENT->value,
            'meaning' => $meaning,
        ];

        return [
            'no role' => [
                'roles'          => [],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
            'this year narrator' => [
                'roles'          => [$yearly(Role::ROLE_VYPRAVEC_ID_ZAKLAD, ROCNIK, 'VYPRAVEC')],
                'entitledToBed'  => true,
                'maySundayNight' => false,
            ],
            'last year narrator' => [
                'roles'          => [$yearly(Role::ROLE_VYPRAVEC_ID_ZAKLAD, ROCNIK - 1, 'VYPRAVEC')],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
            'this year backstage' => [
                'roles'          => [$yearly(Role::ROLE_ZAZEMI_ID_ZAKLAD, ROCNIK, 'ZAZEMI')],
                'entitledToBed'  => true,
                'maySundayNight' => false,
            ],
            'this year info desk' => [
                'roles'          => [$yearly(Role::ROLE_INFOPULT_ID_ZAKLAD, ROCNIK, 'INFOPULT')],
                'entitledToBed'  => true,
                'maySundayNight' => false,
            ],
            'this year partner' => [
                'roles'          => [$yearly(Role::ROLE_PARTNER_ID_ZAKLAD, ROCNIK, 'PARTNER')],
                'entitledToBed'  => true,
                'maySundayNight' => false,
            ],
            'this year herman' => [
                'roles'          => [$yearly(Role::ROLE_HERMAN_ID_ZAKLAD, ROCNIK, 'HERMAN')],
                'entitledToBed'  => true,
                'maySundayNight' => false,
            ],
            'this year volunteer' => [
                // In RoleMeaning::organizerMeanings(), yet legacy gives a volunteer neither bed nor Sunday.
                'roles'          => [$yearly(Role::ROLE_BRIGADNIK_ID_ZAKLAD, ROCNIK, 'BRIGADNIK')],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
            'organizer' => [
                'roles'          => [$permanent(Role::ORGANIZATOR, 'ORGANIZATOR_ZDARMA')],
                'entitledToBed'  => true,
                'maySundayNight' => true,
            ],
            'half organizer with free accommodation' => [
                'roles'          => [$permanent(Role::PUL_ORG_BONUS_UBYTKO, 'PUL_ORG_UBYTKO')],
                'entitledToBed'  => true,
                'maySundayNight' => true,
            ],
            'half organizer with free shirts' => [
                'roles'          => [$permanent(Role::PUL_ORG_BONUS_TRICKO, 'PUL_ORG_TRICKO')],
                'entitledToBed'  => true,
                'maySundayNight' => true,
            ],
            'mini organizer' => [
                'roles'          => [$permanent(Role::MINI_ORG, 'MINI_ORG')],
                'entitledToBed'  => true,
                'maySundayNight' => true,
            ],
            'paused full organizer' => [
                'roles'          => [$permanent(Role::PAUZUJICI_FULL_ORG, 'PAUZUJICI_FULL_ORG')],
                'entitledToBed'  => true,
                'maySundayNight' => true,
            ],
            'honorary organizer' => [
                // In RoleMeaning::organizerMeanings(), outside the five legacy recognises.
                'roles'          => [$permanent(Role::CESTNY_ORGANIZATOR, 'CESTNY_ORGANIZATOR')],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
            'council member' => [
                'roles'          => [$permanent(Role::CLEN_RADY, 'CLEN_RADY')],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
            'sunday night offered by a role' => [
                'roles' => [
                    [
                        'id'      => self::SUNDAY_NIGHT_OFFER_ROLE,
                        'year'    => ROCNIK,
                        'type'    => RoleTypeEnum::YEARLY->value,
                        'meaning' => '',
                        'rights'  => [Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET],
                    ],
                ],
                'entitledToBed'  => false,
                'maySundayNight' => true,
            ],
            'sunday night free through a role' => [
                'roles' => [
                    [
                        'id'      => self::SUNDAY_NIGHT_FREE_ROLE,
                        'year'    => ROCNIK,
                        'type'    => RoleTypeEnum::YEARLY->value,
                        'meaning' => '',
                        'rights'  => [Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA],
                    ],
                ],
                'entitledToBed'  => false,
                'maySundayNight' => true,
            ],
            'sunday night right of last year' => [
                'roles' => [
                    [
                        'id'      => self::LAST_YEAR_SUNDAY_NIGHT_ROLE,
                        'year'    => ROCNIK - 1,
                        'type'    => RoleTypeEnum::YEARLY->value,
                        'meaning' => '',
                        'rights'  => [Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET],
                    ],
                ],
                'entitledToBed'  => false,
                'maySundayNight' => false,
            ],
        ];
    }

    /**
     * @param list<array{id: int, year: int, type: string, meaning: string, rights?: list<int>}> $roles
     */
    #[DataProvider('roleScenarios')]
    public function testAnswersMatchLegacy(array $roles, bool $entitledToBed, bool $maySundayNight): void
    {
        $userId = $this->userWithRoles($roles);
        $user = $this->entityManager()->find(User::class, $userId);
        self::assertNotNull($user);
        $legacy = \Uzivatel::zId($userId);
        self::assertNotNull($legacy);
        $rules = static::getContainer()->get(AccommodationRules::class);

        self::assertSame($entitledToBed, $this->legacyEntitledToBed($legacy), 'Legacy, bed');
        self::assertSame(! $entitledToBed, $this->sleepingBagsOnlyWithRestriction(true, $user), 'New layer, bed');
        self::assertSame($maySundayNight, $this->legacyMaySundayNight($legacy), 'Legacy, Sunday night');
        self::assertSame($maySundayNight, $rules->maySundayNight($user, ROCNIK), 'New layer, Sunday night');
    }

    /**
     * An organizer role granted while the request runs (the infopult changes roles, the cart
     * is repriced) must count at once, so nothing may be remembered from before.
     */
    public function testRoleGrantedWithinTheRequestCounts(): void
    {
        $userId = $this->userWithRoles([]);
        $user = $this->entityManager()->find(User::class, $userId);
        self::assertNotNull($user);
        $rules = static::getContainer()->get(AccommodationRules::class);
        self::assertFalse($rules->maySundayNight($user, ROCNIK));
        self::assertTrue($this->sleepingBagsOnlyWithRestriction(true, $user));

        $this->assignRole($userId, Role::ORGANIZATOR);

        self::assertTrue($rules->maySundayNight($user, ROCNIK));
        self::assertFalse($this->sleepingBagsOnlyWithRestriction(true, $user));
    }

    public function testNobodyIsLimitedToSleepingBagsWhileTheRestrictionIsOff(): void
    {
        $user = $this->entityManager()->find(User::class, $this->userWithRoles([]));
        self::assertNotNull($user);

        self::assertFalse($this->sleepingBagsOnlyWithRestriction(false, $user));
    }

    /**
     * The restriction is a constant, fixed for the whole process, so the settings are swapped for
     * the length of the call only; the legacy user in the same test must keep the real ones.
     */
    private function sleepingBagsOnlyWithRestriction(bool $restricted, User $user): bool
    {
        $originalSettings = $GLOBALS['systemoveNastaveni'] ?? null;
        $settings = $this->createMock(SystemoveNastaveni::class);
        $settings->method('rocnik')->willReturn(ROCNIK);
        $settings->method('jeOmezeniUbytovaniPouzeNaSpacaky')->willReturn($restricted);
        $GLOBALS['systemoveNastaveni'] = $settings;

        try {
            return static::getContainer()->get(AccommodationRules::class)->sleepingBagsOnly($user, ROCNIK);
        } finally {
            $GLOBALS['systemoveNastaveni'] = $originalSettings;
        }
    }

    /**
     * @param list<array{id: int, year: int, type: string, meaning: string, rights?: list<int>}> $roles
     */
    private function userWithRoles(array $roles): int
    {
        foreach ($roles as $role) {
            $this->connection()->executeStatement(
                "INSERT IGNORE INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
                 VALUES (:role, :code, :code, '', :year, :type, :meaning)",
                [
                    'role'    => $role['id'],
                    'code'    => 'TEST_UBYT_' . abs($role['id']),
                    'year'    => $role['year'],
                    'type'    => $role['type'],
                    'meaning' => $role['meaning'],
                ],
            );
            foreach ($role['rights'] ?? [] as $right) {
                $this->connection()->executeStatement(
                    'INSERT IGNORE INTO prava_role (id_role, id_prava) VALUES (:role, :right)',
                    [
                        'role'  => $role['id'],
                        'right' => $right,
                    ],
                );
            }
        }
        $userId = $this->ucastnikVSql('ubyt_');
        foreach ($roles as $role) {
            $this->assignRole($userId, $role['id']);
        }

        return $userId;
    }

    private function assignRole(int $userId, int $roleId): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:user, :role)',
            [
                'user' => $userId,
                'role' => $roleId,
            ],
        );
    }

    /**
     * The legacy formula for who keeps a real bed, which AccommodationRules::sleepingBagsOnly() has to reproduce.
     */
    private function legacyEntitledToBed(\Uzivatel $legacy): bool
    {
        return $legacy->jeVypravec()
            || $legacy->jeOrganizator()
            || $legacy->jeHerman()
            || $legacy->jePartner()
            || $legacy->jeInfopultak()
            || $legacy->jeZazemi();
    }

    /**
     * The legacy formula the new AccommodationRules::maySundayNight() has to reproduce.
     */
    private function legacyMaySundayNight(\Uzivatel $legacy): bool
    {
        return $legacy->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
            || $legacy->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
            || $legacy->jeOrganizator();
    }
}
