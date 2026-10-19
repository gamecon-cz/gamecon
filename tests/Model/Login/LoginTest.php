<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Login;

use Gamecon\Login\Login;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\Info;

class LoginTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function prihlasovaniStrankaNacitaStylyProHintyProfilovaciListy()
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $login = new Login(new Info($systemoveNastaveni), $systemoveNastaveni);

        $html = $login->htmlLogin();

        // profilInfo() is printed below the login page too; without hint.css its query list
        // (a ".hint") stays permanently visible
        self::assertStringContainsString('href="files/design/hint.css', $html);
    }
}
