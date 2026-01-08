<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony;

use App\Entity\Accommodation;
use App\Entity\ActivityRegistrationState;
use App\Entity\ActivityStatus;
use App\Entity\ActivityType;
use App\Entity\CategoryTag;
use App\Entity\Location;
use App\Entity\News;
use App\Entity\NewsletterSubscription;
use App\Entity\Page;
use App\Entity\Payment;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\ShopGrid;
use App\Entity\ShopGridCell;
use App\Entity\ShopItem;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Structure\Entity\AccommodationEntityStructure;
use App\Structure\Entity\ActivityRegistrationStateEntityStructure;
use App\Structure\Entity\ActivityStatusEntityStructure;
use App\Structure\Entity\ActivityTypeEntityStructure;
use App\Structure\Entity\CategoryTagEntityStructure;
use App\Structure\Entity\LocationEntityStructure;
use App\Structure\Entity\NewsEntityStructure;
use App\Structure\Entity\NewsletterSubscriptionEntityStructure;
use App\Structure\Entity\PageEntityStructure;
use App\Structure\Entity\PaymentEntityStructure;
use App\Structure\Entity\PermissionEntityStructure;
use App\Structure\Entity\RoleEntityStructure;
use App\Structure\Entity\ShopGridCellEntityStructure;
use App\Structure\Entity\ShopGridEntityStructure;
use App\Structure\Entity\ShopItemEntityStructure;
use App\Structure\Entity\TagEntityStructure;
use App\Structure\Entity\UserBadgeEntityStructure;
use App\Structure\Entity\UserEntityStructure;
use Gamecon\Aktivita\AkcePrihlaseniStavy;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;
use Gamecon\Kfc\ObchodMrizka;
use Gamecon\Kfc\ObchodMrizkaBunka;
use Gamecon\Newsletter\NewsletterPrihlaseni;
use Gamecon\Pravo;
use Gamecon\Role\Role as LegacyRole;
use Gamecon\Shop\Predmet;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\AccommodationFactory;
use Gamecon\Tests\Factory\ActivityRegistrationStateFactory;
use Gamecon\Tests\Factory\ActivityStateFactory;
use Gamecon\Tests\Factory\ActivityTypeFactory;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\LocationFactory;
use Gamecon\Tests\Factory\NewsFactory;
use Gamecon\Tests\Factory\NewsletterSubscriptionFactory;
use Gamecon\Tests\Factory\PageFactory;
use Gamecon\Tests\Factory\PaymentFactory;
use Gamecon\Tests\Factory\PermissionFactory;
use Gamecon\Tests\Factory\RoleFactory;
use Gamecon\Tests\Factory\ShopGridCellFactory;
use Gamecon\Tests\Factory\ShopGridFactory;
use Gamecon\Tests\Factory\ShopItemFactory;
use Gamecon\Tests\Factory\TagFactory;
use Gamecon\Tests\Factory\UserBadgeFactory;
use Gamecon\Tests\Factory\UserFactory;
use Gamecon\Ubytovani\Ubytovani;
use Gamecon\Uzivatel\Medailonek;
use Gamecon\Uzivatel\Platba;
use Gamecon\Aktivita\Lokace;
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
        // Create a Symfony entity using factory
        /** @var User $symfonyUser */
        $symfonyUser = UserFactory::createOne([
            UserEntityStructure::login                => 'test_user_' . uniqid(),
            UserEntityStructure::email                => 'test_' . uniqid() . '@example.com',
            UserEntityStructure::jmeno                => 'TestJmeno',
            UserEntityStructure::prijmeni             => 'TestPrijmeni',
            UserEntityStructure::uliceACp             => 'Test Street 123',
            UserEntityStructure::mesto                => 'Test City',
            UserEntityStructure::stat                 => 1,
            UserEntityStructure::psc                  => '12345',
            UserEntityStructure::telefon              => '+420123456789',
            UserEntityStructure::datumNarozeni        => new \DateTime('1990-01-01'),
            UserEntityStructure::hesloMd5             => 'test_hash',
            UserEntityStructure::forumRazeni          => 's',
            UserEntityStructure::random               => 'test_random',
            UserEntityStructure::zustatek             => 100,
            UserEntityStructure::registrovan          => new \DateTime('2023-01-01 12:00:00'),
            UserEntityStructure::poznamka             => 'Test poznamka',
            UserEntityStructure::pomocTyp             => 'test_typ',
            UserEntityStructure::pomocVice            => 'Test pomoc vice',
            UserEntityStructure::op                   => 'test_op',
            UserEntityStructure::infopultPoznamka     => 'Test infopult',
            UserEntityStructure::typDokladuTotoznosti => 'OP',
            UserEntityStructure::statniObcanstvi      => 'CZ',
            UserEntityStructure::zRychloregistrace    => false,
            UserEntityStructure::mrtvyMail            => false,
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
        // Create a Symfony entity using factory
        /** @var Page $symfonyPage */
        $symfonyPage = PageFactory::createOne([
            PageEntityStructure::urlStranky => 'test-page-' . uniqid(),
            PageEntityStructure::obsah      => 'Test page content',
            PageEntityStructure::poradi     => 42,
        ])->_real();

        $symfonyPageId = $symfonyPage->getId();
        $this->assertNotNull($symfonyPageId);

        // Fetch the same entity using legacy Stranka
        $legacyPage = \Stranka::zId($symfonyPageId);
        $this->assertNotNull($legacyPage, 'Legacy page should be found');

        // Compare values using getters
        $this->assertEquals($symfonyPage->getId(), $legacyPage->id());
        $this->assertEquals($symfonyPage->getUrlStranky(), $legacyPage->url());
        $this->assertEquals($symfonyPage->getObsah(), $legacyPage->raw()['obsah']);
        $this->assertEquals($symfonyPage->getPoradi(), $legacyPage->poradi());
    }

    public function testTagEntityMatchesLegacyTag(): void
    {
        // First create a CategoryTag for the foreign key
        /** @var CategoryTag $categoryTag */
        $categoryTag = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev  => 'Test Category ' . uniqid(),
            CategoryTagEntityStructure::poradi => 1,
        ])->_real();

        // Create a Symfony entity using factory
        /** @var Tag $symfonyTag */
        $symfonyTag = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Test Tag ' . uniqid(),
            TagEntityStructure::poznamka    => 'Test tag note',
            TagEntityStructure::categoryTag => $categoryTag,
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
        $this->assertEquals($symfonyTag->getCategoryTag()->getId(), $legacyTag->raw()['id_kategorie_tagu']);
    }

    public function testCategoryTagEntityMatchesLegacyKategorieTagu(): void
    {
        // Create a Symfony entity using factory
        /** @var CategoryTag $symfonyCategoryTag */
        $symfonyCategoryTag = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev           => 'Test Category Tag ' . uniqid(),
            CategoryTagEntityStructure::poradi          => 10,
            CategoryTagEntityStructure::mainCategoryTag => null,
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
        $this->assertEquals($symfonyCategoryTag->getMainCategoryTag()?->getId(), $legacyCategoryTag->idHlavniKategorie());
    }

    public function testCategoryTagWithParentEntityMatchesLegacy(): void
    {
        // Create parent category
        /** @var CategoryTag $parentCategory */
        $parentCategory = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev           => 'Parent Category ' . uniqid(),
            CategoryTagEntityStructure::poradi          => 1,
            CategoryTagEntityStructure::mainCategoryTag => null,
        ])->_real();

        // Create child category
        /** @var CategoryTag $childCategory */
        $childCategory = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev           => 'Child Category ' . uniqid(),
            CategoryTagEntityStructure::poradi          => 2,
            CategoryTagEntityStructure::mainCategoryTag => $parentCategory,
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
        $this->assertEquals($childCategory->getMainCategoryTag()?->getId(), $legacyChildCategory->idHlavniKategorie());
        $this->assertEquals($parentCategory->getId(), $legacyChildCategory->idHlavniKategorie());
    }

    public function testUserWithOptionalFieldsEntityMatchesLegacy(): void
    {
        // Create user with all optional fields set
        /** @var User $symfonyUser */
        $symfonyUser = UserFactory::createOne([
            UserEntityStructure::login                            => 'test_optional_' . uniqid(),
            UserEntityStructure::email                            => 'optional_' . uniqid() . '@example.com',
            UserEntityStructure::jmeno                            => 'OptionalJmeno',
            UserEntityStructure::prijmeni                         => 'OptionalPrijmeni',
            UserEntityStructure::nechceMaily                      => new \DateTime('2023-06-01 10:00:00'),
            UserEntityStructure::ubytovanS                        => 'Some Person',
            UserEntityStructure::potvrzeniZakonnehoZastupce       => new \DateTime('2023-05-01'),
            UserEntityStructure::potvrzeniProtiCovid19PridanoKdy  => new \DateTime('2023-07-01 14:00:00'),
            UserEntityStructure::potvrzeniProtiCovid19OverenoKdy  => new \DateTime('2023-07-02 15:00:00'),
            UserEntityStructure::statniObcanstvi                  => 'SK',
            UserEntityStructure::potvrzeniZakonnehoZastupceSoubor => new \DateTime('2023-05-01 10:00:00'),
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
        // Create a Symfony entity using factory
        /** @var ActivityType $symfonyActivityType */
        $symfonyActivityType = ActivityTypeFactory::createOne([
            ActivityTypeEntityStructure::id            => 99,
            ActivityTypeEntityStructure::typ1p         => 'Test Type Singular',
            ActivityTypeEntityStructure::typ1pmn       => 'Test Type Plural',
            ActivityTypeEntityStructure::urlTypuMn     => 'test-type-url',
            ActivityTypeEntityStructure::pageAbout     => PageFactory::createOne(),
            ActivityTypeEntityStructure::poradi        => 5,
            ActivityTypeEntityStructure::mailNeucast   => true,
            ActivityTypeEntityStructure::popisKratky   => 'Short description',
            ActivityTypeEntityStructure::aktivni       => true,
            ActivityTypeEntityStructure::zobrazitVMenu => true,
            ActivityTypeEntityStructure::kodTypu       => 'TEST',
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
        $this->assertEquals($symfonyActivityType->getPageAbout()->getId(), $legacyData['stranka_o']);
        $this->assertEquals($symfonyActivityType->getPoradi(), $legacyData['poradi']);
        $this->assertEquals($symfonyActivityType->isMailNeucast(), (bool) $legacyData['mail_neucast']);
        $this->assertEquals($symfonyActivityType->getPopisKratky(), $legacyData['popis_kratky']);
        $this->assertEquals($symfonyActivityType->isAktivni(), (bool) $legacyData['aktivni']);
        $this->assertEquals($symfonyActivityType->isZobrazitVMenu(), (bool) $legacyData['zobrazit_v_menu']);
        $this->assertEquals($symfonyActivityType->getKodTypu(), $legacyData['kod_typu']);
    }

    public function testActivityStateEntityMatchesLegacyStavAktivity(): void
    {
        // Create a Symfony entity using factory
        /** @var ActivityStatus $symfonyActivityState */
        $symfonyActivityState = ActivityStateFactory::createOne([
            ActivityStatusEntityStructure::nazev => 'Test Activity State ' . uniqid(),
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
        // Create a Symfony entity using factory
        /** @var ActivityRegistrationState $symfonyActivityRegistrationState */
        $symfonyActivityRegistrationState = ActivityRegistrationStateFactory::createOne([
            ActivityRegistrationStateEntityStructure::id            => 50,
            ActivityRegistrationStateEntityStructure::nazev         => 'Test Registration State ' . uniqid(),
            ActivityRegistrationStateEntityStructure::platbaProcent => 75,
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
        // Create a Symfony entity using factory
        /** @var NewsletterSubscription $symfonyNewsletterSubscription */
        $symfonyNewsletterSubscription = NewsletterSubscriptionFactory::createOne([
            NewsletterSubscriptionEntityStructure::email => 'newsletter_' . uniqid() . '@example.com',
            NewsletterSubscriptionEntityStructure::kdy   => new \DateTime('2023-08-01 10:00:00'),
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
        // Create a Symfony entity using factory
        /** @var Role $symfonyRole */
        $symfonyRole = RoleFactory::createOne([
            RoleEntityStructure::kodRole       => 'TEST_ROLE_' . uniqid(),
            RoleEntityStructure::nazevRole     => 'Test Role ' . uniqid(),
            RoleEntityStructure::popisRole     => 'Test role description',
            RoleEntityStructure::rocnikRole    => 2024,
            RoleEntityStructure::typRole       => 'trvala',
            RoleEntityStructure::vyznamRole    => 'TEST_VYZNAM',
            RoleEntityStructure::skryta        => false,
            RoleEntityStructure::kategorieRole => 1,
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
        // Create a Symfony entity using factory
        /** @var Permission $symfonyPermission */
        $symfonyPermission = PermissionFactory::createOne([
            PermissionEntityStructure::jmenoPrava => 'Test Permission ' . uniqid(),
            PermissionEntityStructure::popisPrava => 'Test permission description',
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
            UserEntityStructure::login => 'accomm_user_' . uniqid(),
            UserEntityStructure::email => 'accomm_' . uniqid() . '@example.com',
        ])->_real();

        // Create a Symfony entity using factory
        /** @var Accommodation $symfonyAccommodation */
        $symfonyAccommodation = AccommodationFactory::createOne([
            AccommodationEntityStructure::uzivatel => $testUser,
            AccommodationEntityStructure::den      => 3,
            AccommodationEntityStructure::rok      => 2024,
            AccommodationEntityStructure::pokoj    => 'Room 123',
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

    public function testShopItemEntityMatchesLegacyPredmet(): void
    {
        // Create a Symfony entity using factory
        /** @var ShopItem $symfonyShopItem */
        $symfonyShopItem = ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev           => 'Test Předmět ' . uniqid(),
            ShopItemEntityStructure::kodPredmetu     => 'TEST_KOD_' . strtoupper(uniqid()),
            ShopItemEntityStructure::modelRok        => 2024,
            ShopItemEntityStructure::cenaAktualni    => '199.50',
            ShopItemEntityStructure::stav            => 1,
            ShopItemEntityStructure::nabizetDo       => new \DateTime('2024-12-31 23:59:59'),
            ShopItemEntityStructure::kusuVyrobeno    => 100,
            ShopItemEntityStructure::typ             => 1,
            ShopItemEntityStructure::ubytovaniDen    => null,
            ShopItemEntityStructure::popis           => 'Testovací popis předmětu',
            ShopItemEntityStructure::jeLetosniHlavni => true,
        ])->_save()->_real();

        $symfonyShopItemId = $symfonyShopItem->getId();
        $this->assertNotNull($symfonyShopItemId);

        // Fetch the same entity using legacy Predmet
        $legacyPredmet = Predmet::zId($symfonyShopItemId);
        $this->assertNotNull($legacyPredmet, 'Legacy shop item (predmet) should be found');

        // Compare values using getters and raw data
        $this->assertEquals($symfonyShopItem->getId(), $legacyPredmet->id());

        // Test raw database values
        $legacyData = $legacyPredmet->raw();
        $this->assertEquals($symfonyShopItem->getNazev(), $legacyData['nazev']);
        $this->assertEquals($symfonyShopItem->getKodPredmetu(), $legacyData['kod_predmetu']);
        $this->assertEquals($symfonyShopItem->getModelRok(), $legacyData['model_rok']);
        $this->assertEquals($symfonyShopItem->getCenaAktualni(), $legacyData['cena_aktualni']);
        $this->assertEquals($symfonyShopItem->getStav(), $legacyData['stav']);
        $this->assertEquals($symfonyShopItem->getKusuVyrobeno(), $legacyData['kusu_vyrobeno']);
        $this->assertEquals($symfonyShopItem->getTyp(), $legacyData['typ']);
        $this->assertEquals($symfonyShopItem->getUbytovaniDen(), $legacyData['ubytovani_den']);
        $this->assertEquals($symfonyShopItem->getPopis(), $legacyData['popis']);
        $this->assertEquals($symfonyShopItem->isJeLetosniHlavni(), (bool) $legacyData['je_letosni_hlavni']);

        // Test date field
        if ($symfonyShopItem->getNabizetDo()) {
            $this->assertEquals(
                $symfonyShopItem->getNabizetDo()->format('Y-m-d H:i:s'),
                $legacyData['nabizet_do'],
            );
        }
    }

    public function testLocationEntityMatchesLegacyLokace(): void
    {
        // Create a Symfony entity using factory
        /** @var Location $symfonyLocation */
        $symfonyLocation = LocationFactory::createOne([
            LocationEntityStructure::nazev    => 'Test místnost ' . uniqid(),
            LocationEntityStructure::dvere    => 'Budova C, dveře č. 123',
            LocationEntityStructure::poznamka => 'Testovací poznámka k místnosti',
            LocationEntityStructure::poradi   => 42,
            LocationEntityStructure::rok      => 2024,
        ])->_save()->_real();

        $symfonyLocationId = $symfonyLocation->getId();
        $this->assertNotNull($symfonyLocationId);

        // Fetch the same entity using legacy Lokace
        $legacyLokace = Lokace::zId($symfonyLocationId);
        $this->assertNotNull($legacyLokace, 'Legacy location (lokace) should be found');

        // Compare values using getters and raw data
        $this->assertEquals($symfonyLocation->getId(), $legacyLokace->id());
        $this->assertEquals($symfonyLocation->getNazev(), $legacyLokace->nazev());
        $this->assertEquals($symfonyLocation->getDvere(), $legacyLokace->dvere());
        $this->assertEquals($symfonyLocation->getPoznamka(), $legacyLokace->poznamka());
        $this->assertEquals($symfonyLocation->getPoradi(), $legacyLokace->poradi());
        $this->assertEquals($symfonyLocation->getRok(), $legacyLokace->rok());

        // Test raw database values
        $legacyData = $legacyLokace->raw();
        $this->assertEquals($symfonyLocation->getNazev(), $legacyData['nazev']);
        $this->assertEquals($symfonyLocation->getDvere(), $legacyData['dvere']);
        $this->assertEquals($symfonyLocation->getPoznamka(), $legacyData['poznamka']);
        $this->assertEquals($symfonyLocation->getPoradi(), $legacyData['poradi']);
        $this->assertEquals($symfonyLocation->getRok(), $legacyData['rok']);
    }

    public function testNewsEntityMatchesLegacyNovinka(): void
    {
        // Text is now stored directly as a string (no longer FK to texty table)
        $textContent = 'Test blog post content lorem ipsum...';

        // Create a Symfony entity using factory
        /** @var News $symfonyNews */
        $symfonyNews = NewsFactory::createOne([
            NewsEntityStructure::typ   => News::TYPE_BLOG,
            NewsEntityStructure::vydat => new \DateTime('2024-06-15 14:30:00'),
            NewsEntityStructure::url   => 'test-blog-post-' . uniqid(),
            NewsEntityStructure::nazev => 'Test blog post ' . uniqid(),
            NewsEntityStructure::autor => 'Test "Autor" Testovič',
            NewsEntityStructure::text  => $textContent,
        ])->_save()->_real();

        $symfonyNewsId = $symfonyNews->getId();
        $this->assertNotNull($symfonyNewsId);

        // Fetch the same entity using legacy Novinka
        $legacyNovinka = \Novinka::zId($symfonyNewsId);
        $this->assertNotNull($legacyNovinka, 'Legacy news (novinka) should be found');

        // Compare values using getters and raw data
        $this->assertEquals($symfonyNews->getId(), $legacyNovinka->id());
        $this->assertEquals($symfonyNews->getTyp(), $legacyNovinka->typ());
        $this->assertEquals($symfonyNews->getUrl(), $legacyNovinka->url());
        $this->assertEquals($symfonyNews->getNazev(), $legacyNovinka->nazev());

        // Test raw database values
        $legacyData = $legacyNovinka->raw();
        $this->assertEquals($symfonyNews->getTyp(), $legacyData['typ']);
        $this->assertEquals($symfonyNews->getUrl(), $legacyData['url']);
        $this->assertEquals($symfonyNews->getNazev(), $legacyData['nazev']);
        $this->assertEquals($symfonyNews->getAutor(), $legacyData['autor']);
        $this->assertEquals($symfonyNews->getText(), $legacyData['text']);

        // Test date field
        if ($symfonyNews->getVydat()) {
            $this->assertEquals(
                $symfonyNews->getVydat()->format('Y-m-d H:i:s'),
                $legacyData['vydat'],
            );
        }
    }

    public function testShopGridCellEntityMatchesLegacyObchodMrizkaBunka(): void
    {
        // Create a Symfony entity using factory
        /** @var ShopGridCell $symfonyShopGridCell */
        $symfonyShopGridCell = ShopGridCellFactory::createOne([
            ShopGridCellEntityStructure::typ       => ShopGridCell::TYPE_ITEM,
            ShopGridCellEntityStructure::text      => 'Test buňka ' . uniqid(),
            ShopGridCellEntityStructure::barva     => '#FF5733',
            ShopGridCellEntityStructure::barvaText => '#FFFFFF',
            ShopGridCellEntityStructure::cilId     => 42,
            ShopGridCellEntityStructure::shopGrid  => null,
        ])->_save()->_real();

        $symfonyShopGridCellId = $symfonyShopGridCell->getId();
        $this->assertNotNull($symfonyShopGridCellId);

        // Fetch the same entity using legacy ObchodMrizkaBunka
        $legacyObchodMrizkaBunka = ObchodMrizkaBunka::zId($symfonyShopGridCellId);
        $this->assertNotNull($legacyObchodMrizkaBunka, 'Legacy shop grid cell (obchod mrizka bunka) should be found');

        // Compare values using getters and raw data
        $this->assertEquals($symfonyShopGridCell->getId(), $legacyObchodMrizkaBunka->id());
        $this->assertEquals($symfonyShopGridCell->getTyp(), $legacyObchodMrizkaBunka->typ());
        $this->assertEquals($symfonyShopGridCell->getText(), $legacyObchodMrizkaBunka->text());
        $this->assertEquals($symfonyShopGridCell->getBarva(), $legacyObchodMrizkaBunka->barva());
        $this->assertEquals($symfonyShopGridCell->getBarvaText(), $legacyObchodMrizkaBunka->barvaText());
        $this->assertEquals($symfonyShopGridCell->getCilId(), $legacyObchodMrizkaBunka->cilId());
        $this->assertEquals($symfonyShopGridCell->getShopGrid(), $legacyObchodMrizkaBunka->mrizkaId());

        // Test raw database values
        $legacyData = $legacyObchodMrizkaBunka->raw();
        $this->assertEquals($symfonyShopGridCell->getTyp(), $legacyData['typ']);
        $this->assertEquals($symfonyShopGridCell->getText(), $legacyData['text']);
        $this->assertEquals($symfonyShopGridCell->getBarva(), $legacyData['barva']);
        $this->assertEquals($symfonyShopGridCell->getBarvaText(), $legacyData['barva_text']);
        $this->assertEquals($symfonyShopGridCell->getCilId(), $legacyData['cil_id']);
        $this->assertEquals($symfonyShopGridCell->getShopGrid(), $legacyData['mrizka_id']);
    }

    public function testShopGridEntityMatchesLegacyObchodMrizka(): void
    {
        // Create a Symfony entity using factory
        /** @var ShopGrid $symfonyShopGrid */
        $symfonyShopGrid = ShopGridFactory::createOne([
            ShopGridEntityStructure::text => 'Test mřížka ' . uniqid(),
        ])->_save()->_real();

        $symfonyShopGridId = $symfonyShopGrid->getId();
        $this->assertNotNull($symfonyShopGridId);

        // Fetch the same entity using legacy ObchodMrizka
        $legacyObchodMrizka = ObchodMrizka::zId($symfonyShopGridId);
        $this->assertNotNull($legacyObchodMrizka, 'Legacy shop grid (obchod mrizka) should be found');

        // Compare values using getters and raw data
        $this->assertEquals($symfonyShopGrid->getId(), $legacyObchodMrizka->id());
        $this->assertEquals($symfonyShopGrid->getText(), $legacyObchodMrizka->text());

        // Test raw database values
        $legacyData = $legacyObchodMrizka->raw();
        $this->assertEquals($symfonyShopGrid->getText(), $legacyData['text']);
    }

    public function testPaymentEntityMatchesLegacyPlatba(): void
    {
        // Create a Symfony entity using factory
        /** @var Payment $symfonyPayment */
        $symfonyPayment = PaymentFactory::createOne([
            PaymentEntityStructure::fioId               => 123456789,
            PaymentEntityStructure::vs                  => '2024001234',
            PaymentEntityStructure::castka              => '1500.50',
            PaymentEntityStructure::rok                 => 2024,
            PaymentEntityStructure::pripsanoNaUcetBanky => new \DateTime('2024-06-15 10:30:00'),
            PaymentEntityStructure::provedeno           => new \DateTime('2024-06-15 14:00:00'),
            PaymentEntityStructure::madeBy              => UserFactory::first(),
            PaymentEntityStructure::nazevProtiuctu      => 'Test Company s.r.o.',
            PaymentEntityStructure::cisloProtiuctu      => '1234567890/0100',
            PaymentEntityStructure::kodBankyProtiuctu   => '0100',
            PaymentEntityStructure::nazevBankyProtiuctu => 'Test Bank',
            PaymentEntityStructure::poznamka            => 'Test poznámka k platbě',
            PaymentEntityStructure::skrytaPoznamka      => 'Skrytá poznámka',
        ])->_save()->_real();

        $symfonyPaymentId = $symfonyPayment->getId();
        $this->assertNotNull($symfonyPaymentId);

        // Fetch the same entity using legacy Platba
        $legacyPlatba = Platba::zId($symfonyPaymentId);
        $this->assertNotNull($legacyPlatba, 'Legacy payment (platba) should be found');

        // Compare values using getters
        $this->assertEquals($symfonyPayment->getId(), $legacyPlatba->id());
        $this->assertEquals($symfonyPayment->getBeneficiary()->getId(), $legacyPlatba->idUzivatele());
        $this->assertEquals($symfonyPayment->getFioId(), (int) $legacyPlatba->fioId());
        $this->assertEquals($symfonyPayment->getVs(), $legacyPlatba->variabilniSymbol());
        $this->assertEquals($symfonyPayment->getCastka(), $legacyPlatba->castka());
        $this->assertEquals($symfonyPayment->getRok(), $legacyPlatba->rok());
        $this->assertEquals($symfonyPayment->getMadeBy()->getId(), $legacyPlatba->provedl());
        $this->assertEquals($symfonyPayment->getNazevProtiuctu(), $legacyPlatba->nazevProtiuctu());
        $this->assertEquals($symfonyPayment->getCisloProtiuctu(), $legacyPlatba->cisloProtiuctu());
        $this->assertEquals($symfonyPayment->getKodBankyProtiuctu(), $legacyPlatba->kodBankyProtiuctu());
        $this->assertEquals($symfonyPayment->getNazevBankyProtiuctu(), $legacyPlatba->nazevBankyProtiuctu());
        $this->assertEquals($symfonyPayment->getPoznamka(), $legacyPlatba->poznamka());
        $this->assertEquals($symfonyPayment->getSkrytaPoznamka(), $legacyPlatba->skrytaPoznamka());

        // Test raw database values
        $legacyData = $legacyPlatba->raw();
        $this->assertEquals($symfonyPayment->getBeneficiary()->getId(), $legacyData['id_uzivatele']);
        $this->assertEquals($symfonyPayment->getFioId(), $legacyData['fio_id']);
        $this->assertEquals($symfonyPayment->getVs(), $legacyData['vs']);
        $this->assertEquals($symfonyPayment->getCastka(), $legacyData['castka']);
        $this->assertEquals($symfonyPayment->getRok(), $legacyData['rok']);
        $this->assertEquals($symfonyPayment->getMadeBy()->getId(), $legacyData['provedl']);
        $this->assertEquals($symfonyPayment->getNazevProtiuctu(), $legacyData['nazev_protiuctu']);
        $this->assertEquals($symfonyPayment->getCisloProtiuctu(), $legacyData['cislo_protiuctu']);
        $this->assertEquals($symfonyPayment->getKodBankyProtiuctu(), $legacyData['kod_banky_protiuctu']);
        $this->assertEquals($symfonyPayment->getNazevBankyProtiuctu(), $legacyData['nazev_banky_protiuctu']);
        $this->assertEquals($symfonyPayment->getPoznamka(), $legacyData['poznamka']);
        $this->assertEquals($symfonyPayment->getSkrytaPoznamka(), $legacyData['skryta_poznamka']);

        // Test date fields
        if ($symfonyPayment->getPripsanoNaUcetBanky()) {
            $this->assertEquals(
                $symfonyPayment->getPripsanoNaUcetBanky()->format('Y-m-d H:i:s'),
                $legacyData['pripsano_na_ucet_banky'],
            );
        }
        // provedeno is NOT NULL, so always compare
        $this->assertEquals(
            $symfonyPayment->getProvedeno()->format('Y-m-d H:i:s'),
            $legacyData['provedeno'],
        );
    }

    public function testBadgeEntityMatchesLegacyMedailonek(): void
    {
        // Create a user first for the FK (id_uzivatele is the primary key and FK)
        /** @var User $testUser */
        $testUser = UserFactory::createOne([
            UserEntityStructure::login => 'badge_user_' . uniqid(),
            UserEntityStructure::email => 'badge_' . uniqid() . '@example.com',
        ])->_real();

        // Create a Symfony entity using factory
        /** @var UserBadge $symfonyUserBadge */
        $symfonyUserBadge = UserBadgeFactory::createOne([
            UserBadgeEntityStructure::user  => $testUser,
            UserBadgeEntityStructure::oSobe => 'O sobě markdown text ' . uniqid(),
            UserBadgeEntityStructure::drd   => 'DrD profil markdown ' . uniqid(),
        ])->_save()->_real();

        $symfonyBadgeUser = $symfonyUserBadge->getUser();
        $this->assertNotNull($symfonyBadgeUser);

        // Fetch the same entity using legacy Medailonek
        $legacyMedailonek = Medailonek::zId($symfonyBadgeUser->getId());
        $this->assertNotNull($legacyMedailonek, 'Legacy badge (medailonek) should be found');

        // Compare values using getters
        $this->assertEquals($symfonyUserBadge->getUser()->getId(), $legacyMedailonek->idUzivatele());

        // Note: legacy methods drd() and oSobe() apply markdown processing, so compare raw data
        $legacyData = $legacyMedailonek->raw();
        $this->assertEquals($symfonyUserBadge->getOSobe(), $legacyData['o_sobe']);
        $this->assertEquals($symfonyUserBadge->getDrd(), $legacyData['drd']);
    }
}
