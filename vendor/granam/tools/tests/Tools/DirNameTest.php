<?php declare(strict_types=1);

namespace Granam\Tests\Tools;

use Granam\Tools\DirName;
use PHPUnit\Framework\TestCase;

class DirNameTest extends TestCase
{

    /**
     * @test
     * @dataProvider providePath
     * @param string $pathToResolve
     * @param string $resolvedPath
     */
    public function I_can_get_path_with_resolved_parents(string $pathToResolve, string $resolvedPath)
    {
        self::assertSame($resolvedPath, DirName::getPathWithResolvedParents($pathToResolve));
    }

    public function providePath(): array
    {
        return [
            ['/foo/bar/baz/qux/../../..', '/foo'],
            ['/home/jaroslav/projects/docker/drdplus/www/rules.skeleton/DrdPlus/Tests/../..', '/home/jaroslav/projects/docker/drdplus/www/rules.skeleton'],
            ['/foo/bar/../baz/../..', '/'],
            ['/foo/bar/../baz/../.././qux.txt', '/qux.txt'],
        ];
    }
}
