<?php

namespace Gamecon\Tests\Model\Report;

use App\Structure\Entity\RoleEntityStructure;
use App\Structure\Entity\UserEntityStructure;
use App\Structure\Entity\UserRoleEntityStructure;
use Gamecon\Report\BfgrReport;
use Gamecon\Role\Role as LegacyRole;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractDoctrineTestDb;
use Gamecon\Tests\Factory\RoleFactory;
use Gamecon\Tests\Factory\UserFactory;
use Gamecon\Tests\Factory\UserRoleFactory;

class BfgrReportTest extends AbstractDoctrineTestDb
{
    private const YEAR = 2025;

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            function () {
                $user = UserFactory::createOne([
                    UserEntityStructure::zustatek => -50.00,
                ]);
                UserRoleFactory::createOne([
                    UserRoleEntityStructure::user => $user,
                    UserRoleEntityStructure::role => RoleFactory::findOrCreate([
                        RoleEntityStructure::id => LegacyRole::prihlasenNaRocnik(self::YEAR),
                    ]),
                ]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::ORGANIZATOR]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::PUL_ORG_BONUS_UBYTKO]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::PUL_ORG_BONUS_TRICKO]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::MINI_ORG]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::LETOSNI_VYPRAVEC(self::YEAR)]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::LETOSNI_PARTNER(self::YEAR)]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::LETOSNI_BRIGADNIK(self::YEAR)]);
                RoleFactory::findOrCreate([RoleEntityStructure::id => LegacyRole::LETOSNI_HERMAN(self::YEAR)]);
            },
        ];
    }

    /**
     * @test
     */
    public function Bfgr_report_odpovida_ocekavani()
    {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('BFGR_test_', true);
        $bfgr = new BfgrReport(SystemoveNastaveni::zGlobals(rocnik: self::YEAR));
        $bfgr->exportuj(format: 'csv', vcetneStavuNeplatice: true, doSouboru: $tmpFile);
        self::assertFileExists($tmpFile, 'BFGR nebyl exportován do souboru');
        $data = file_get_contents($tmpFile);
        $data = str_getcsv($data);
        self::assertGreaterThan(1, count($data), 'BFGR je prázdný');
    }
}
