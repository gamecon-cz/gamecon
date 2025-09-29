<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony;

use App\Entity\CategoryTag;
use App\Entity\Page;
use App\Entity\Tag;
use App\Entity\User;
use Gamecon\KategorieTagu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\PageFactory;
use Gamecon\Tests\Factory\TagFactory;
use Gamecon\Tests\Factory\UserFactory;
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
            'login' => 'test_user_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'jmeno' => 'TestJmeno',
            'prijmeni' => 'TestPrijmeni',
            'uliceACp' => 'Test Street 123',
            'mesto' => 'Test City',
            'stat' => 1,
            'psc' => '12345',
            'telefon' => '+420123456789',
            'datumNarozeni' => new \DateTime('1990-01-01'),
            'hesloMd5' => 'test_hash',
            'forumRazeni' => 's',
            'random' => 'test_random',
            'zustatek' => 100,
            'registrovan' => new \DateTime('2023-01-01 12:00:00'),
            'poznamka' => 'Test poznamka',
            'pomocTyp' => 'test_typ',
            'pomocVice' => 'Test pomoc vice',
            'op' => 'test_op',
            'infopultPoznamka' => 'Test infopult',
            'typDokladuTotoznosti' => 'OP',
            'statniObcanstvi' => 'CZ',
            'zRychloregistrace' => false,
            'mrtvyMail' => false,
        ])->_save()->_real();

        $symfonyUserId = $symfonyUser->getId();
        $this->assertNotNull($symfonyUserId);

        // Fetch the same entity using legacy Uzivatel
        $legacyUser = Uzivatel::zId($symfonyUserId);
        $this->assertNotNull($legacyUser, 'Legacy user should be found');

        // Compare values using getters
        $this->assertEquals($symfonyUser->getId(), $legacyUser->id());
        $this->assertEquals($symfonyUser->getLogin(), $legacyUser->login());
        $this->assertEquals($symfonyUser->getEmail(), $legacyUser->mail());

        // The legacy jmeno() method returns "jmeno prijmeni", while Symfony has separate getters
        $expectedFullName = $symfonyUser->getJmeno() . ' ' . $symfonyUser->getPrijmeni();
        $this->assertEquals($expectedFullName, $legacyUser->jmeno());

        // Test individual properties accessible through database record
        $this->assertEquals($symfonyUser->getJmeno(), $legacyUser->raw()['jmeno_uzivatele']);
        $this->assertEquals($symfonyUser->getPrijmeni(), $legacyUser->raw()['prijmeni_uzivatele']);
        $this->assertEquals($symfonyUser->getUliceACp(), $legacyUser->raw()['ulice_a_cp_uzivatele']);
        $this->assertEquals($symfonyUser->getMesto(), $legacyUser->raw()['mesto_uzivatele']);
        $this->assertEquals($symfonyUser->getStat(), $legacyUser->raw()['stat_uzivatele']);
        $this->assertEquals($symfonyUser->getPsc(), $legacyUser->raw()['psc_uzivatele']);
        $this->assertEquals($symfonyUser->getTelefon(), $legacyUser->raw()['telefon_uzivatele']);
        $this->assertEquals($symfonyUser->getHesloMd5(), $legacyUser->raw()['heslo_md5']);
        $this->assertEquals($symfonyUser->getForumRazeni(), $legacyUser->raw()['forum_razeni']);
        $this->assertEquals($symfonyUser->getRandom(), $legacyUser->raw()['random']);
        $this->assertEquals($symfonyUser->getZustatek(), $legacyUser->raw()['zustatek']);
        $this->assertEquals($symfonyUser->getPoznamka(), $legacyUser->raw()['poznamka']);
        $this->assertEquals($symfonyUser->getPomocTyp(), $legacyUser->raw()['pomoc_typ']);
        $this->assertEquals($symfonyUser->getPomocVice(), $legacyUser->raw()['pomoc_vice']);
        $this->assertEquals($symfonyUser->getOp(), $legacyUser->raw()['op']);
        $this->assertEquals($symfonyUser->getInfopultPoznamka(), $legacyUser->raw()['infopult_poznamka']);
        $this->assertEquals($symfonyUser->getTypDokladuTotoznosti(), $legacyUser->raw()['typ_dokladu_totoznosti']);
        $this->assertEquals($symfonyUser->getStatniObcanstvi(), $legacyUser->raw()['statni_obcanstvi']);
        $this->assertEquals($symfonyUser->isZRychloregistrace(), (bool)$legacyUser->raw()['z_rychloregistrace']);
        $this->assertEquals($symfonyUser->isMrtvyMail(), (bool)$legacyUser->raw()['mrtvy_mail']);

        // Test date fields
        $this->assertEquals(
            $symfonyUser->getDatumNarozeni()->format('Y-m-d'),
            $legacyUser->raw()['datum_narozeni']
        );
        $this->assertEquals(
            $symfonyUser->getRegistrovan()->format('Y-m-d H:i:s'),
            $legacyUser->raw()['registrovan']
        );

        // Test enum field
        $this->assertEquals($symfonyUser->getPohlavi()->value, $legacyUser->raw()['pohlavi']);
    }

    public function testPageEntityMatchesLegacyStranka(): void
    {
        // Create Symfony entity using factory
        /** @var Page $symfonyPage */
        $symfonyPage = PageFactory::createOne([
            'urlStranky' => 'test-page-' . uniqid(),
            'obsah' => 'Test page content',
            'poradi' => 42,
        ])->_real();

        $symfonyPageId = $symfonyPage->getIdStranky();
        $this->assertNotNull($symfonyPageId);

        // Fetch the same entity using legacy Stranka
        $legacyPage = Stranka::zId($symfonyPageId);
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
            'nazev' => 'Test Category ' . uniqid(),
            'poradi' => 1,
        ])->_real();

        // Create Symfony entity using factory
        /** @var Tag $symfonyTag */
        $symfonyTag = TagFactory::createOne([
            'nazev' => 'Test Tag ' . uniqid(),
            'poznamka' => 'Test tag note',
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
            'nazev' => 'Test Category Tag ' . uniqid(),
            'poradi' => 10,
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
            'nazev' => 'Parent Category ' . uniqid(),
            'poradi' => 1,
            'hlavniKategorie' => null,
        ])->_real();

        // Create child category
        /** @var CategoryTag $childCategory */
        $childCategory = CategoryTagFactory::createOne([
            'nazev' => 'Child Category ' . uniqid(),
            'poradi' => 2,
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
            'login' => 'test_optional_' . uniqid(),
            'email' => 'optional_' . uniqid() . '@example.com',
            'jmeno' => 'OptionalJmeno',
            'prijmeni' => 'OptionalPrijmeni',
            'nechceMaily' => new \DateTime('2023-06-01 10:00:00'),
            'ubytovanS' => 'Some Person',
            'potvrzeniZakonnehoZastupce' => new \DateTime('2023-05-01'),
            'potvrzeniProtiCovid19PridanoKdy' => new \DateTime('2023-07-01 14:00:00'),
            'potvrzeniProtiCovid19OverenoKdy' => new \DateTime('2023-07-02 15:00:00'),
            'statniObcanstvi' => 'SK',
            'potvrzeniZakonnehoZastupceSoubor' => new \DateTime('2023-05-01 10:00:00'),
        ])->_real();

        $symfonyUserId = $symfonyUser->getId();
        $this->assertNotNull($symfonyUserId);

        // Fetch the same entity using legacy Uzivatel
        $legacyUser = Uzivatel::zId($symfonyUserId);
        $this->assertNotNull($legacyUser, 'Legacy user with optional fields should be found');

        // Test optional date fields
        if ($symfonyUser->getNechceMaily()) {
            $this->assertEquals(
                $symfonyUser->getNechceMaily()->format('Y-m-d H:i:s'),
                $legacyUser->raw()['nechce_maily']
            );
        }

        $this->assertEquals($symfonyUser->getUbytovanS(), $legacyUser->raw()['ubytovan_s']);

        if ($symfonyUser->getPotvrzeniZakonnehoZastupce()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniZakonnehoZastupce()->format('Y-m-d'),
                $legacyUser->raw()['potvrzeni_zakonneho_zastupce']
            );
        }

        if ($symfonyUser->getPotvrzeniProtiCovid19PridanoKdy()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniProtiCovid19PridanoKdy()->format('Y-m-d H:i:s'),
                $legacyUser->raw()['potvrzeni_proti_covid19_pridano_kdy']
            );
        }

        if ($symfonyUser->getPotvrzeniProtiCovid19OverenoKdy()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniProtiCovid19OverenoKdy()->format('Y-m-d H:i:s'),
                $legacyUser->raw()['potvrzeni_proti_covid19_overeno_kdy']
            );
        }

        $this->assertEquals($symfonyUser->getStatniObcanstvi(), $legacyUser->raw()['statni_obcanstvi']);

        if ($symfonyUser->getPotvrzeniZakonnehoZastupceSoubor()) {
            $this->assertEquals(
                $symfonyUser->getPotvrzeniZakonnehoZastupceSoubor()->format('Y-m-d H:i:s'),
                $legacyUser->raw()['potvrzeni_zakonneho_zastupce_soubor']
            );
        }
    }
}
