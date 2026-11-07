<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DumpEntitySqlStructureCommandTest extends KernelTestCase
{
    public function testEntitySqlStructureFilesAreUpToDate(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:dump-entity-sql-structure');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--check' => true,
        ]);

        self::assertSame(
            0,
            $commandTester->getStatusCode(),
            "Entity SQL structure files are outdated. Run 'bin/console app:dump-entity-sql-structure' to regenerate.\n\n" . $commandTester->getDisplay()
        );
    }
}
