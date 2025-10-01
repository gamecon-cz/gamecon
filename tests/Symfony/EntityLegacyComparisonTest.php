<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony;

use App\Entity\Accommodation;
use App\Entity\ActivityRegistrationState;
use App\Entity\ActivityState;
use App\Entity\ActivityType;
use App\Entity\CategoryTag;
use App\Entity\NewsletterSubscription;
use App\Entity\Page;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\Tag;
use App\Entity\User;
use Gamecon\Aktivita\AkcePrihlaseniStavy;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;
use Gamecon\Newsletter\NewsletterPrihlaseni;
use Gamecon\Pravo;
use Gamecon\Role\Role as LegacyRole;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\AccommodationFactory;
use Gamecon\Tests\Factory\ActivityRegistrationStateFactory;
use Gamecon\Tests\Factory\ActivityStateFactory;
use Gamecon\Tests\Factory\ActivityTypeFactory;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\NewsletterSubscriptionFactory;
use Gamecon\Tests\Factory\PageFactory;
use Gamecon\Tests\Factory\PermissionFactory;
use Gamecon\Tests\Factory\RoleFactory;
use Gamecon\Tests\Factory\TagFactory;
use Gamecon\Tests\Factory\UserFactory;
use Gamecon\Ubytovani\Ubytovani;
use Stranka;
use Uzivatel;
use Zenstruck\Foundry\Test\Factories;

class EntityLegacyComparisonTest extends AbstractTestDb
{
    use Factories;

    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    public function testUserEntityMatchesLegacyUzivatel(): void
    {
        // Create Symfony entity using factory
        /** @var User $symfonyUser */
        $symfonyUser = UserFactory::createOne([
            'login'                => 'test_user_' . uniqid(),
            'email'                => 'test_' . uniqid() . '@example.com',
            'jmeno'                => 'TestJmeno',
            'prijmeni'             => 'TestPrijmeni',
            'uliceACp'             => 'Test Street 123',
            'mesto'                => 'Test City',
            'stat'                 => 1,
            'psc'                  => '12345',
            'telefon'              => '+420123456789',
            'datumNarozeni'        => new \DateTime('1990-01-01'),
            'hesloMd5'             => 'test_hash',
            'forumRazeni'          => 's',
            'random'               => 'test_random',
            'zustatek'             => 100,
            'registrovan'          => new \DateTime('2023-01-01 12:00:00'),
            'poznamka'             => 'Test poznamka',
            'pomocTyp'             => 'test_typ',
            'pomocVice'            => 'Test pomoc vice',
            'op'                   => 'test_op',
            'infopultPoznamka'     => 'Test infopult',
            'typDokladuTotoznosti' => 'OP',
            'statniObcanstvi'      => 'CZ',
            'zRychloregistrace'    => false,
            'mrtvyMail'            => false,
        ])->_save()->_real();

        $symfonyUserId = $symfonyUser->getId();
        $this->assertNotNull($symfonyUserId);

        // Fetch the same entity using legacy Uzivatel
        $legacyUser = \Uzivatel::zId($symfonyUserId);
        $this->assertNotNull($legacyUser, 'Legacy user should be found');

        // Compare values using getters
        $this->assertEquals($symfonyUser->getId(), $legacyUser->id());
        $this->assertEquals($symfonyUser->getLogin(), $legacyUser->login());
        $this->assertEquals($symfonyUser->getEmail(), $legacyUser->mail());

        // The legacy jmeno() method returns "jmeno prijmeni", while Symfony has separate getters
        $expectedFullName = $symfonyUser->getJmeno() . ' ' . $symfonyUser->getPrijmeni();
        $this->assertEquals($expectedFullName, $legacyUser->jmeno());

        // Test individual properties accessible through database record
        $legacyData = $legacyUser->raw();
        $this->assertEquals($symfonyUser->getJmeno(), $legacyData['jmeno_uzivatele']);
        $this->assertEquals($symfonyUser->getPrijmeni(), $legacyData['prijmeni_uzivatele']);
        $this->assertEquals($symfonyUser->getUliceACp(), $legacyData['ulice_a_cp_uzivatele']);
        $this->assertEquals($symfonyUser->getMesto(), $legacyData['mesto_uzivatele']);
        $this->assertEquals($symfonyUser->getStat(), $legacyData['stat_uzivatele']);
        $this->assertEquals($symfonyUser->getPsc(), $legacyData['psc_uzivatele']);
        $this->assertEquals($symfonyUser->getTelefon(), $legacyData['telefon_uzivatele']);
        $this->assertEquals($symfonyUser->getHesloMd5(), $legacyData['heslo_md5']);
        $this->assertEquals($symfonyUser->getForumRazeni(), $legacyData['forum_razeni']);
        $this->assertEquals($symfonyUser->getRandom(), $legacyData['random']);
        $this->assertEquals($symfonyUser->getZustatek(), $legacyData['zustatek']);
        $this->assertEquals($symfonyUser->getPoznamka(), $legacyData['poznamka']);
        $this->assertEquals($symfonyUser->getPomocTyp(), $legacyData['pomoc_typ']);
        $this->assertEquals($symfonyUser->getPomocVice(), $legacyData['pomoc_vice']);
        $this->assertEquals($symfonyUser->getOp(), $legacyData['op']);
        $this->assertEquals($symfonyUser->getInfopultPoznamka(), $legacyData['infopult_poznamka']);
        $this->assertEquals($symfonyUser->getTypDokladuTotoznosti(), $legacyData['typ_dokladu_totoznosti']);
        $this->assertEquals($symfonyUser->getStatniObcanstvi(), $legacyData['statni_obcanstvi']);
        $this->assertEquals($symfonyUser->isZRychloregistrace(), (bool) $legacyData['z_rychloregistrace']);
        $this->assertEquals($symfonyUser->isMrtvyMail(), (bool) $legacyData['mrtvy_mail']);

        // Test date fields
        $this->assertEquals(
            $symfonyUser->getDatumNarozeni()->format('Y-m-d'),
            $legacyData['datum_narozeni'],
        );
        $this->assertEquals(
            $symfonyUser->getRegistrovan()->format('Y-m-d H:i:s'),
            $legacyData['registrovan'],
        );

        // Test enum field
        $this->assertEquals($symfonyUser->getPohlavi()->value, $legacyData['pohlavi']);
    }

    public function testPageEntityMatchesLegacyStranka(): void
    {
        // Create Symfony entity using factory
        /** @var Page $symfonyPage */
        $symfonyPage = PageFactory::createOne([
            'urlStranky' => 'test-page-' . uniqid(),
            'obsah'      => 'Test page content',
            'poradi'     => 42,
        ])->_real();

        $symfonyPageId = $symfonyPage->getIdStranky();
        $this->assertNotNull($symfonyPageId);

        // Fetch the same entity using legacy Stranka
        $legacyPage = \Stranka::zId($symfonyPageId);
        $this->assertNotNull($legacyPage, 'Legacy page should be found');

        // Compare values using getters
        $this->assertEquals($symfonyPage->getIdStranky(), $legacyPage->id());
        $this->assertEquals($symfonyPage->getUrlStranky(), $legacyPage->url());
        $this->assertEquals($symfonyPage->getObsah(), $legacyPage->raw()['obsah']);
        $this->assertEquals($symfonyPage->getPoradi(), $legacyPage->poradi());
    }

    public function testTagEntityMatchesLegacyTag(): void
    {
        // First create a CategoryTag for the foreign key
        /** @var CategoryTag $categoryTag */
        $categoryTag = CategoryTagFactory::createOne([
            'nazev'  => 'Test Category ' . uniqid(),
            'poradi' => 1,
        ])->_real();

        // Create Symfony entity using factory
        /** @var Tag $symfonyTag */
        $symfonyTag = TagFactory::createOne([
            'nazev'        => 'Test Tag ' . uniqid(),
            'poznamka'     => 'Test tag note',
            'kategorieTag' => $categoryTag,
        ])->_real();

        $symfonyTagId = $symfonyTag->getId();
        $this->assertNotNull($symfonyTagId);

        // Fetch the same entity using legacy Tag (assuming it follows the same pattern)
        $legacyTag = \Tag::zId($symfonyTagId);
        $this->assertNotNull($legacyTag, 'Legacy tag should be found');

        // Compare values using getters
        $this->assertEquals($symfonyTag->getId(), $legacyTag->id());
        $this->assertEquals($symfonyTag->getNazev(), $legacyTag->nazev());
        $this->assertEquals($symfonyTag->getPoznamka(), $legacyTag->poznamka());
        $this->assertEquals($symfonyTag->getKategorieTag()->getId(), $legacyTag->raw()['id_kategorie_tagu']);
    }

    public function testCategoryTagEntityMatchesLegacyKategorieTagu(): void
    {
        // Create Symfony entity using factory
        /** @var CategoryTag $symfonyCategoryTag */
        $symfonyCategoryTag = CategoryTagFactory::createOne([
            'nazev'           => 'Test Category Tag ' . uniqid(),
            'poradi'          => 10,
            'hlavniKategorie' => null,
        ])->_real();

        $symfonyCategoryTagId = $symfonyCategoryTag->getId();
        $this->assertNotNull($symfonyCategoryTagId);

        // Fetch the same entity using legacy KategorieTagu
        $legacyCategoryTag = KategorieTagu::zId($symfonyCategoryTagId);
        $this->assertNotNull($legacyCategoryTag, 'Legacy category tag should be found');

        // Compare values using getters
        $this->assertEquals($symfonyCategoryTag->getId(), $legacyCategoryTag->id());
        $this->assertEquals($symfonyCategoryTag->getNazev(), $legacyCategoryTag->nazev());
        $this->assertEquals($symfonyCategoryTag->getPoradi(), $legacyCategoryTag->poradi());
        $this->assertEquals($symfonyCategoryTag->getHlavniKategorie()?->getId(), $legacyCategoryTag->idHlavniKategorie());
    }

    public function testCategoryTagWithParentEntityMatchesLegacy(): void
    {
        // Create parent category
        /** @var CategoryTag $parentCategory */
        $parentCategory = CategoryTagFactory::createOne([
            'nazev'           => 'Parent Category ' . uniqid(),
            'poradi'          => 1,
            'hlavniKategorie' => null,
        ])->_real();

        // Create child category
        /** @var CategoryTag $childCategory */
        $childCategory = CategoryTagFactory::createOne([
            'nazev'           => 'Child Category ' . uniqid(),
            'poradi'          => 2,
            'hlavniKategorie' => $parentCategory,
        ])->_real();

        $childCategoryId = $childCategory->getId();
        $this->assertNotNull($childCategoryId);

        // Fetch the same entity using legacy KategorieTagu
        $legacyChildCategory = KategorieTagu::zId($childCategoryId);
        $this->assertNotNull($legacyChildCategory, 'Legacy child category should be found');

        // Compare values including parent relationship
        $this->assertEquals($childCategory->getId(), $legacyChildCategory->id());
        $this->assertEquals($childCategory->getNazev(), $legacyChildCategory->nazev());
        $this->assertEquals($childCategory->getPoradi(), $legacyChildCategory->poradi());
        $this->assertEquals($childCategory->getHlavniKategorie()?->getId(), $legacyChildCategory->idHlavniKategorie());
        $this->assertEquals($parentCategory->getId(), $legacyChildCategory->idHlavniKategorie());
    }

    public function testUserWithOptionalFieldsEntityMatchesLegacy(): void
    {
        // Create user with all optional fields set
        /** @var User $symfonyUser */
        $symfonyUser = UserFactory::createOne([
            'login'                            => 'test_optional_' . uniqid(),
            'email'                            => 'optional_' . uniqid() . '@example.com',
            'jmeno'                            => 'OptionalJmeno',
            'prijmeni'                         => 'OptionalPrijmeni',
            'nechceMaily'                      => new \DateTime('2023-06-01 10:00:00'),
            'ubytovanS'                        => 'Some Person',
            'potvrzeniZakonnehoZastupce'       => new \DateTime('2023-05-01'),
            'potvrzeniProtiCovid19PridanoKdy'  => new \DateTime('2023-07-01 14:00:00'),
            'potvrzeniProtiCovid19OverenoKdy'  => new \DateTime('2023-07-02 15:00:00'),
            'statniObcanstvi'                  => 'SK',
            'potvrzeniZakonnehoZastupceSoubor' => new \DateTime('2023-05-01 10:00:00'),
        ])->_real();

        $symfonyUserId = $symfonyUser->getId();
        $this->assertNotNull($symfonyUserId);

        // Fetch the same entity using legacy Uzivatel
        $legacyUser = \Uzivatel::zId($symfonyUserId);
        $this->assertNotNull($legacyUser, 'Legacy user with optional fields should be found');

        // Test optional date fields
        $legacyData = $legacyUser->raw();
        if ($symfonyUser->getNechceMaily()) {
            $this->assertEquals(
                $symfonyUser->getNechceMaily()->format('Y-m-d H:i:s'),
                $legacyData['nechce_maily'],
            );
        }

        $this->assertEquals($symfonyUser->getUbytovanS(), $legacyData['ubytovan_s']);

        if ($symfonyUser->getPotvrzeniZakonnehoZastupce()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniZakonnehoZastupce()->format('Y-m-d'),
                $legacyData['potvrzeni_zakonneho_zastupce'],
            );
        }

        if ($symfonyUser->getPotvrzeniProtiCovid19PridanoKdy()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniProtiCovid19PridanoKdy()->format('Y-m-d H:i:s'),
                $legacyData['potvrzeni_proti_covid19_pridano_kdy'],
            );
        }

        if ($symfonyUser->getPotvrzeniProtiCovid19OverenoKdy()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniProtiCovid19OverenoKdy()->format('Y-m-d H:i:s'),
                $legacyData['potvrzeni_proti_covid19_overeno_kdy'],
            );
        }

        $this->assertEquals($symfonyUser->getStatniObcanstvi(), $legacyData['statni_obcanstvi']);

        if ($symfonyUser->getPotvrzeniZakonnehoZastupceSoubor()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniZakonnehoZastupceSoubor()->format('Y-m-d H:i:s'),
                $legacyData['potvrzeni_zakonneho_zastupce_soubor'],
            );
        }
    }

    public function testActivityTypeEntityMatchesLegacyTypAktivity(): void
    {
        // Create Symfony entity using factory
        /** @var ActivityType $symfonyActivityType */
        $symfonyActivityType = ActivityTypeFactory::createOne([
            'id'            => 99,
            'typ1p'         => 'Test Type Singular',
            'typ1pmn'       => 'Test Type Plural',
            'urlTypuMn'     => 'test-type-url',
            'strankaO'      => PageFactory::createOne()->getIdStranky(),
            'poradi'        => 5,
            'mailNeucast'   => true,
            'popisKratky'   => 'Short description',
            'aktivni'       => true,
            'zobrazitVMenu' => true,
            'kodTypu'       => 'TEST',
        ])->_save()->_real();

        $symfonyActivityTypeId = $symfonyActivityType->getId();
        $this->assertNotNull($symfonyActivityTypeId);

        // Fetch the same entity using legacy TypAktivity
        $legacyActivityType = TypAktivity::zId($symfonyActivityTypeId);
        $this->assertNotNull($legacyActivityType, 'Legacy activity type should be found');

        // Compare values using getters
        $this->assertEquals($symfonyActivityType->getId(), $legacyActivityType->id());
        $this->assertEquals($symfonyActivityType->getTyp1pmn(), $legacyActivityType->nazev());
        $this->assertEquals($symfonyActivityType->getTyp1p(), $legacyActivityType->nazevJednotnehoCisla());
        $this->assertEquals($symfonyActivityType->getUrlTypuMn(), $legacyActivityType->url());
        $this->assertEquals($symfonyActivityType->getPopisKratky(), $legacyActivityType->popisKratky());
        $this->assertEquals($symfonyActivityType->getPoradi(), $legacyActivityType->poradi());
        $this->assertEquals($symfonyActivityType->isMailNeucast(), $legacyActivityType->posilatMailyNedorazivsim());

        // Test raw database values
        $legacyData = $legacyActivityType->raw();
        $this->assertEquals($symfonyActivityType->getTyp1p(), $legacyData['typ_1p']);
        $this->assertEquals($symfonyActivityType->getTyp1pmn(), $legacyData['typ_1pmn']);
        $this->assertEquals($symfonyActivityType->getUrlTypuMn(), $legacyData['url_typu_mn']);
        $this->assertEquals($symfonyActivityType->getStrankaO(), $legacyData['stranka_o']);
        $this->assertEquals($symfonyActivityType->getPoradi(), $legacyData['poradi']);
        $this->assertEquals($symfonyActivityType->isMailNeucast(), (bool) $legacyData['mail_neucast']);
        $this->assertEquals($symfonyActivityType->getPopisKratky(), $legacyData['popis_kratky']);
        $this->assertEquals($symfonyActivityType->isAktivni(), (bool) $legacyData['aktivni']);
        $this->assertEquals($symfonyActivityType->isZobrazitVMenu(), (bool) $legacyData['zobrazit_v_menu']);
        $this->assertEquals($symfonyActivityType->getKodTypu(), $legacyData['kod_typu']);
    }

    public function testActivityStateEntityMatchesLegacyStavAktivity(): void
    {
        // Create Symfony entity using factory
        /** @var ActivityState $symfonyActivityState */
        $symfonyActivityState = ActivityStateFactory::createOne([
            'nazev' => 'Test Activity State ' . uniqid(),
        ])->_save()->_real();

        $symfonyActivityStateId = $symfonyActivityState->getId();
        $this->assertNotNull($symfonyActivityStateId);

        // Fetch the same entity using legacy StavAktivity
        $legacyActivityState = StavAktivity::zId($symfonyActivityStateId);
        $this->assertNotNull($legacyActivityState, 'Legacy activity state should be found');

        // Compare values using getters
        $this->assertEquals($symfonyActivityState->getId(), $legacyActivityState->id());
        $this->assertEquals($symfonyActivityState->getNazev(), $legacyActivityState->nazev());

        // Test raw database values
        $legacyData = $legacyActivityState->raw();
        $this->assertEquals($symfonyActivityState->getNazev(), $legacyData['nazev']);
    }

    public function testActivityRegistrationStateEntityMatchesLegacyAkcePrihlaseniStavy(): void
    {
        // Create Symfony entity using factory
        /** @var ActivityRegistrationState $symfonyActivityRegistrationState */
        $symfonyActivityRegistrationState = ActivityRegistrationStateFactory::createOne([
            'id'            => 50,
            'nazev'         => 'Test Registration State ' . uniqid(),
            'platbaProcent' => 75,
        ])->_save()->_real();

        $symfonyActivityRegistrationStateId = $symfonyActivityRegistrationState->getId();
        $this->assertNotNull($symfonyActivityRegistrationStateId);

        // Fetch the same entity using legacy AkcePrihlaseniStavy
        $legacyActivityRegistrationState = AkcePrihlaseniStavy::zId($symfonyActivityRegistrationStateId);
        $this->assertNotNull($legacyActivityRegistrationState, 'Legacy activity registration state should be found');

        // Compare values using getters
        $this->assertEquals($symfonyActivityRegistrationState->getId(), $legacyActivityRegistrationState->id());
        $this->assertEquals($symfonyActivityRegistrationState->getNazev(), $legacyActivityRegistrationState->nazev());
        $this->assertEquals($symfonyActivityRegistrationState->getPlatbaProcent(), $legacyActivityRegistrationState->platbaProcent());

        // Test raw database values
        $legacyData = $legacyActivityRegistrationState->raw();
        $this->assertEquals($symfonyActivityRegistrationState->getNazev(), $legacyData['nazev']);
        $this->assertEquals($symfonyActivityRegistrationState->getPlatbaProcent(), $legacyData['platba_procent']);
    }

    public function testNewsletterSubscriptionEntityMatchesLegacyNewsletterPrihlaseni(): void
    {
        // Create Symfony entity using factory
        /** @var NewsletterSubscription $symfonyNewsletterSubscription */
        $symfonyNewsletterSubscription = NewsletterSubscriptionFactory::createOne([
            'email' => 'newsletter_' . uniqid() . '@example.com',
            'kdy'   => new \DateTime('2023-08-01 10:00:00'),
        ])->_save()->_real();

        $symfonyNewsletterSubscriptionId = $symfonyNewsletterSubscription->getId();
        $this->assertNotNull($symfonyNewsletterSubscriptionId);

        // Fetch the same entity using legacy NewsletterPrihlaseni
        $legacyNewsletterSubscription = NewsletterPrihlaseni::zId($symfonyNewsletterSubscriptionId);
        $this->assertNotNull($legacyNewsletterSubscription, 'Legacy newsletter subscription should be found');

        // Compare values using getters
        $this->assertEquals($symfonyNewsletterSubscription->getId(), $legacyNewsletterSubscription->id());

        // Test raw database values
        $legacyData = $legacyNewsletterSubscription->raw();
        $this->assertEquals($symfonyNewsletterSubscription->getEmail(), $legacyData['email']);
        $this->assertEquals(
            $symfonyNewsletterSubscription->getKdy()->format('Y-m-d H:i:s'),
            $legacyData['kdy'],
        );
    }

    public function testRoleEntityMatchesLegacyRole(): void
    {
        // Create Symfony entity using factory
        /** @var Role $symfonyRole */
        $symfonyRole = RoleFactory::createOne([
            'kodRole'       => 'TEST_ROLE_' . uniqid(),
            'nazevRole'     => 'Test Role ' . uniqid(),
            'popisRole'     => 'Test role description',
            'rocnikRole'    => 2024,
            'typRole'       => 'trvala',
            'vyznamRole'    => 'TEST_VYZNAM',
            'skryta'        => false,
            'kategorieRole' => 1,
        ])->_save()->_real();

        $symfonyRoleId = $symfonyRole->getId();
        $this->assertNotNull($symfonyRoleId);

        // Fetch the same entity using legacy Role
        $legacyRole = LegacyRole::zId($symfonyRoleId);
        $this->assertNotNull($legacyRole, 'Legacy role should be found');

        // Compare values using getters
        $this->assertEquals($symfonyRole->getId(), $legacyRole->id());
        $this->assertEquals($symfonyRole->getNazevRole(), $legacyRole->nazevRole());
        $this->assertEquals($symfonyRole->getKategorieRole(), $legacyRole->kategorieRole());

        // Test raw database values
        $legacyData = $legacyRole->raw();
        $this->assertEquals($symfonyRole->getKodRole(), $legacyData['kod_role']);
        $this->assertEquals($symfonyRole->getNazevRole(), $legacyData['nazev_role']);
        $this->assertEquals($symfonyRole->getPopisRole(), $legacyData['popis_role']);
        $this->assertEquals($symfonyRole->getRocnikRole(), $legacyData['rocnik_role']);
        $this->assertEquals($symfonyRole->getTypRole(), $legacyData['typ_role']);
        $this->assertEquals($symfonyRole->getVyznamRole(), $legacyData['vyznam_role']);
        $this->assertEquals($symfonyRole->isSkryta(), (bool) $legacyData['skryta']);
        $this->assertEquals($symfonyRole->getKategorieRole(), $legacyData['kategorie_role']);
    }

    public function testPermissionEntityMatchesLegacyPravo(): void
    {
        // Create Symfony entity using factory
        /** @var Permission $symfonyPermission */
        $symfonyPermission = PermissionFactory::createOne([
            'jmenoPrava' => 'Test Permission ' . uniqid(),
            'popisPrava' => 'Test permission description',
        ])->_save()->_real();

        $symfonyPermissionId = $symfonyPermission->getId();
        $this->assertNotNull($symfonyPermissionId);

        // Fetch the same entity using legacy Pravo
        $legacyPermission = Pravo::zId($symfonyPermissionId);
        $this->assertNotNull($legacyPermission, 'Legacy permission should be found');

        // Compare values using getters
        $this->assertEquals($symfonyPermission->getId(), $legacyPermission->id());

        // Test raw database values
        $legacyData = $legacyPermission->raw();
        $this->assertEquals($symfonyPermission->getJmenoPrava(), $legacyData['jmeno_prava']);
        $this->assertEquals($symfonyPermission->getPopisPrava(), $legacyData['popis_prava']);
    }

    public function testAccommodationEntityMatchesLegacyUbytovani(): void
    {
        // First create a user for the foreign key
        /** @var User $testUser */
        $testUser = UserFactory::createOne([
            'login' => 'accomm_user_' . uniqid(),
            'email' => 'accomm_' . uniqid() . '@example.com',
        ])->_real();

        // Create Symfony entity using factory
        /** @var Accommodation $symfonyAccommodation */
        $symfonyAccommodation = AccommodationFactory::createOne([
            'uzivatel' => $testUser,
            'den'      => 3,
            'rok'      => 2024,
            'pokoj'    => 'Room 123',
        ])->_save()->_real();

        // The legacy table has composite primary key (id_uzivatele, den, rok)
        // Fetch using the primary key (which is just id_uzivatele for this DbObject)
        $legacyAccommodation = Ubytovani::zId($testUser->getId());
        $this->assertNotNull($legacyAccommodation, 'Legacy accommodation should be found');

        // Test raw database values
        $legacyData = $legacyAccommodation->raw();
        $this->assertEquals($testUser->getId(), $legacyData['id_uzivatele']);
        $this->assertEquals($symfonyAccommodation->getDen(), $legacyData['den']);
        $this->assertEquals($symfonyAccommodation->getRok(), $legacyData['rok']);
        $this->assertEquals($symfonyAccommodation->getPokoj(), $legacyData['pokoj']);
    }
}
