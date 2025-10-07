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
use Gamecon\Tests\Factory\BadgeFactory;
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
use Gamecon\Tests\Factory\TextFactory;
use Gamecon\Tests\Factory\UserFactory;
use Gamecon\Ubytovani\Ubytovani;
use Gamecon\Uzivatel\Medailonek;
use Gamecon\Uzivatel\Platba;
use Lokace;
use Novinka;
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
        $this->assertEquals($symfonyTag->getCategoryTag()->getId(), $legacyTag->raw()['id_kategorie_tagu']);
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
        $this->assertEquals($symfonyCategoryTag->getMainCategoryTag()?->getId(), $legacyCategoryTag->idHlavniKategorie());
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
        $this->assertEquals($childCategory->getMainCategoryTag()?->getId(), $legacyChildCategory->idHlavniKategorie());
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
            'strankaO'      => PageFactory::createOne()->getId(),
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
        $this->assertEquals($symfonyActivityType->getPageAbout(), $legacyData['stranka_o']);
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
        /** @var ActivityStatus $symfonyActivityState */
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

    public function testShopItemEntityMatchesLegacyPredmet(): void
    {
        // Create Symfony entity using factory
        /** @var ShopItem $symfonyShopItem */
        $symfonyShopItem = ShopItemFactory::createOne([
            'nazev'           => 'Test Předmět ' . uniqid(),
            'kodPredmetu'     => 'TEST_KOD_' . strtoupper(uniqid()),
            'modelRok'        => 2024,
            'cenaAktualni'    => '199.50',
            'stav'            => 1,
            'nabizetDo'       => new \DateTime('2024-12-31 23:59:59'),
            'kusuVyrobeno'    => 100,
            'typ'             => 1,
            'ubytovaniDen'    => null,
            'popis'           => 'Testovací popis předmětu',
            'jeLetosniHlavni' => true,
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
        // Create Symfony entity using factory
        /** @var Location $symfonyLocation */
        $symfonyLocation = LocationFactory::createOne([
            'nazev'    => 'Test místnost ' . uniqid(),
            'dvere'    => 'Budova C, dveře č. 123',
            'poznamka' => 'Testovací poznámka k místnosti',
            'poradi'   => 42,
            'rok'      => 2024,
        ])->_save()->_real();

        $symfonyLocationId = $symfonyLocation->getId();
        $this->assertNotNull($symfonyLocationId);

        // Fetch the same entity using legacy Lokace
        $legacyLokace = \Lokace::zId($symfonyLocationId);
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
        // First create a Text entity (FK requirement)
        $textContent = 'Test blog post content lorem ipsum...';
        $textEntity = TextFactory::createOne([
            'text' => $textContent,
        ])->_save()->_real();

        // Create Symfony entity using factory
        /** @var News $symfonyNews */
        $symfonyNews = NewsFactory::createOne([
            'typ'   => News::TYPE_BLOG,
            'vydat' => new \DateTime('2024-06-15 14:30:00'),
            'url'   => 'test-blog-post-' . uniqid(),
            'nazev' => 'Test blog post ' . uniqid(),
            'autor' => 'Test "Autor" Testovič',
            'text'  => $textEntity->getId(),
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
        // Create Symfony entity using factory
        /** @var ShopGridCell $symfonyShopGridCell */
        $symfonyShopGridCell = ShopGridCellFactory::createOne([
            'typ'       => ShopGridCell::TYPE_ITEM,
            'text'      => 'Test buňka ' . uniqid(),
            'barva'     => '#FF5733',
            'barvaText' => '#FFFFFF',
            'cilId'     => 42,
            'mrizkaId'  => null, // No FK constraint issue
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
        // Create Symfony entity using factory
        /** @var ShopGrid $symfonyShopGrid */
        $symfonyShopGrid = ShopGridFactory::createOne([
            'text' => 'Test mřížka ' . uniqid(),
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
        // Create Symfony entity using factory
        /** @var Payment $symfonyPayment */
        $symfonyPayment = PaymentFactory::createOne([
            'idUzivatele'         => null, // Can be null
            'fioId'               => 123456789,
            'vs'                  => '2024001234',
            'castka'              => '1500.50',
            'rok'                 => 2024,
            'pripsanoNaUcetBanky' => new \DateTime('2024-06-15 10:30:00'),
            'provedeno'           => new \DateTime('2024-06-15 14:00:00'),
            'provedl'             => 1,
            'nazevProtiuctu'      => 'Test Company s.r.o.',
            'cisloProtiuctu'      => '1234567890/0100',
            'kodBankyProtiuctu'   => '0100',
            'nazevBankyProtiuctu' => 'Test Bank',
            'poznamka'            => 'Test poznámka k platbě',
            'skrytaPoznamka'      => 'Skrytá poznámka',
        ])->_save()->_real();

        $symfonyPaymentId = $symfonyPayment->getId();
        $this->assertNotNull($symfonyPaymentId);

        // Fetch the same entity using legacy Platba
        $legacyPlatba = Platba::zId($symfonyPaymentId);
        $this->assertNotNull($legacyPlatba, 'Legacy payment (platba) should be found');

        // Compare values using getters
        $this->assertEquals($symfonyPayment->getId(), $legacyPlatba->id());
        $this->assertEquals($symfonyPayment->getBeneficiary(), $legacyPlatba->idUzivatele());
        $this->assertEquals($symfonyPayment->getFioId(), (int) $legacyPlatba->fioId());
        $this->assertEquals($symfonyPayment->getVs(), $legacyPlatba->variabilniSymbol());
        $this->assertEquals($symfonyPayment->getCastka(), $legacyPlatba->castka());
        $this->assertEquals($symfonyPayment->getRok(), $legacyPlatba->rok());
        $this->assertEquals($symfonyPayment->getMadeBy(), $legacyPlatba->provedl());
        $this->assertEquals($symfonyPayment->getNazevProtiuctu(), $legacyPlatba->nazevProtiuctu());
        $this->assertEquals($symfonyPayment->getCisloProtiuctu(), $legacyPlatba->cisloProtiuctu());
        $this->assertEquals($symfonyPayment->getKodBankyProtiuctu(), $legacyPlatba->kodBankyProtiuctu());
        $this->assertEquals($symfonyPayment->getNazevBankyProtiuctu(), $legacyPlatba->nazevBankyProtiuctu());
        $this->assertEquals($symfonyPayment->getPoznamka(), $legacyPlatba->poznamka());
        $this->assertEquals($symfonyPayment->getSkrytaPoznamka(), $legacyPlatba->skrytaPoznamka());

        // Test raw database values
        $legacyData = $legacyPlatba->raw();
        $this->assertEquals($symfonyPayment->getBeneficiary(), $legacyData['id_uzivatele']);
        $this->assertEquals($symfonyPayment->getFioId(), $legacyData['fio_id']);
        $this->assertEquals($symfonyPayment->getVs(), $legacyData['vs']);
        $this->assertEquals($symfonyPayment->getCastka(), $legacyData['castka']);
        $this->assertEquals($symfonyPayment->getRok(), $legacyData['rok']);
        $this->assertEquals($symfonyPayment->getMadeBy(), $legacyData['provedl']);
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
        // Create a user first for the FK (id_uzivatele is primary key and FK)
        /** @var User $testUser */
        $testUser = UserFactory::createOne([
            'login' => 'badge_user_' . uniqid(),
            'email' => 'badge_' . uniqid() . '@example.com',
        ])->_real();

        // Create Symfony entity using factory
        /** @var UserBadge $symfonyBadge */
        $symfonyBadge = BadgeFactory::createOne([
            'idUzivatele' => $testUser->getId(),
            'oSobe'       => 'O sobě markdown text ' . uniqid(),
            'drd'         => 'DrD profil markdown ' . uniqid(),
        ])->_save()->_real();

        $symfonyBadgeId = $symfonyBadge->getUser();
        $this->assertNotNull($symfonyBadgeId);

        // Fetch the same entity using legacy Medailonek
        $legacyMedailonek = Medailonek::zId($symfonyBadgeId);
        $this->assertNotNull($legacyMedailonek, 'Legacy badge (medailonek) should be found');

        // Compare values using getters
        $this->assertEquals($symfonyBadge->getUser(), $legacyMedailonek->idUzivatele());

        // Note: legacy methods drd() and oSobe() apply markdown processing, so compare raw data
        $legacyData = $legacyMedailonek->raw();
        $this->assertEquals($symfonyBadge->getOSobe(), $legacyData['o_sobe']);
        $this->assertEquals($symfonyBadge->getDrd(), $legacyData['drd']);
    }
}
