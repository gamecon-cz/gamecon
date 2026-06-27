<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\NastrojeDatabaze;
use PHPUnit\Framework\TestCase;

class NastrojeDatabazeTest extends TestCase
{
    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if ($this->tempFile !== null && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    public function testRemoveDatabaseSelectionOdstraniHlavickuSVyberemDatabaze(): void
    {
        $sql = <<<'SQL'
            CREATE DATABASE `d16779_gcostra` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
            USE `d16779_gcostra`;
            CREATE TABLE `uzivatele_hodnoty` (`id` INT);
            INSERT INTO `uzivatele_hodnoty` VALUES (1);
            SQL;

        $vysledek = $this->spustNadObsahem($sql);

        self::assertStringNotContainsString('CREATE DATABASE', $vysledek);
        self::assertStringNotContainsString('USE `d16779_gcostra`', $vysledek);
        // Vlastní obsah zálohy zůstává nedotčen.
        self::assertStringContainsString('CREATE TABLE `uzivatele_hodnoty`', $vysledek);
        self::assertStringContainsString('INSERT INTO `uzivatele_hodnoty`', $vysledek);
    }

    public function testRemoveDatabaseSelectionNechaPoVZkladuJmenoDatabazeVDatech(): void
    {
        // Jméno produkční DB jako součást textové hodnoty (ne příkazu) se nesmí
        // odstranit — pravidlo cílí jen na celé příkazy CREATE/USE/DROP DATABASE.
        $sql = "INSERT INTO `logy` VALUES ('USE d16779_gcostra zmíněno v textu');\n";

        $vysledek = $this->spustNadObsahem($sql);

        self::assertSame($sql, $vysledek);
    }

    public function testRemoveDatabaseSelectionOdstraniDropDatabase(): void
    {
        $sql = "DROP DATABASE IF EXISTS `d16779_gcostra`;\nSELECT 1;\n";

        $vysledek = $this->spustNadObsahem($sql);

        self::assertStringNotContainsString('DROP DATABASE', $vysledek);
        self::assertStringContainsString('SELECT 1;', $vysledek);
    }

    private function spustNadObsahem(string $sql): string
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'nastroje-databaze-test-');
        file_put_contents($this->tempFile, $sql);

        NastrojeDatabaze::removeDatabaseSelection($this->tempFile);

        return file_get_contents($this->tempFile);
    }
}
