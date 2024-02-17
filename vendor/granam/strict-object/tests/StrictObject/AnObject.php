<?php declare(strict_types=1);

namespace Granam\Tests\Strict\Object;

use Granam\Strict\Object\StrictObject;

final class AnObject extends StrictObject
{
    public $aPublicProperty = 'foo';
    protected $aProtectedProperty = 'bar';
    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $aPrivateProperty = 'baz';

    public function aPublicMethod()
    {
    }

    protected function aProtectedMethod()
    {
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function aPrivateMethod()
    {
    }

    protected static function aProtectedStaticMethod()
    {
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private static function aPrivateStaticMethod()
    {
    }
}