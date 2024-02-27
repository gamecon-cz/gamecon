<?php declare(strict_types=1);

namespace Granam\Tests\Strict\Object;

trait StrictObjectTestTrait
{
    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function callingUndefinedMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $this->expectExceptionMessageMatches('~does not exist~');
        /** @noinspection PhpUndefinedMethodInspection */
        $object->notExistingMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function callingProtectedMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $this->expectExceptionMessageMatches('~is protected~');
        $object->aProtectedMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function callingPrivateMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $this->expectExceptionMessageMatches('~is private~');
        $object->aPrivateMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.callstatic
     */
    public function callingOfUndefinedStaticMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidStaticMethodCall::class);
        $this->expectExceptionMessageMatches('~.+::notExistingMethod\(\).+does not exist~');
        // TODO no message?
        /** @noinspection PhpUndefinedMethodInspection */
        $object::notExistingMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function callingProtectedStaticMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $this->expectExceptionMessageMatches('~is protected~');
        $object::aProtectedStaticMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function callingPrivateStaticMethodThrowsException()
    {
        $object = $this->createObjectInstance();

        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $this->expectExceptionMessageMatches('~is private~');
        $object::aPrivateStaticMethod();
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.magic.php#object.invoke
     */
    public function callingOfObjectItselfAsAMethodThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidMethodCall::class);
        $object = $this->createObjectInstance();
        $object();
    }

    /**
     * @test
     * @expectedExceptionRegExp ~does not exists~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.get
     */
    public function readingOfAnUndefinedPropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyRead::class);
        $object = $this->createObjectInstance();
        $object->notExistingProperty;
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is protected~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.get
     */
    public function readingOfAnProtectedPropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyRead::class);
        $object = $this->createObjectInstance();
        $object->aProtectedProperty;
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is private~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.get
     */
    public function readingOfAnPrivatePropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyRead::class);
        $object = $this->createObjectInstance();
        $object->aPrivateProperty;
    }

    /**
     * @test
     * @expectedExceptionRegExp ~does not exists~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.set
     */
    public function writeToUndefinedPropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyWrite::class);
        $object = $this->createObjectInstance();
        $object->notExistingProperty = 'bar';
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is protected~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.set
     */
    public function writeToProtectedPropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyWrite::class);
        $object = $this->createObjectInstance();
        $object->aProtectedProperty = 'bar';
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is private~
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.set
     */
    public function writeToPrivatePropertyThrowsException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\InvalidPropertyWrite::class);
        $object = $this->createObjectInstance();
        $object->aPrivateProperty = 'bar';
    }

    /**
     * @test
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.isset
     */
    public function askingIfIsSetUndefinedPropertyAlwaysReturnsFalse()
    {
        $objectWithPublicProperty = new AnObject();
        self::assertTrue(
            isset($objectWithPublicProperty->aPublicProperty),
            'Magic __isset should not affects existing public properties'
        );
        /** @noinspection PhpUnitTestsInspection */
        self::assertFalse(
            empty($objectWithPublicProperty->aPublicProperty),
            'Magic __isset should not affects existing public properties'
        );

        $object = $this->createObjectInstance();
        self::assertFalse(isset($object->notExistingProperty));
        /** @noinspection PhpUnitTestsInspection */
        self::assertTrue(empty($object->notExistingProperty));
    }

    /**
     * @test
     * @expectedExceptionRegExp ~does not exists~
     */
    public function unsetOfUndefinedPropertyThrowsWritingAccessException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\WritingAccess::class);
        $object = $this->createObjectInstance();
        unset($object->notExistingProperty);
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is protected~
     */
    public function unsetOfProtectedPropertyThrowsWritingAccessException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\WritingAccess::class);
        $object = $this->createObjectInstance();
        unset($object->aProtectedProperty);
    }

    /**
     * @test
     * @expectedExceptionRegExp ~is private~
     */
    public function unsetOfPrivatePropertyThrowsWritingAccessException()
    {
        $this->expectException(\Granam\Strict\Object\Exceptions\WritingAccess::class);
        $object = $this->createObjectInstance();
        unset($object->aPrivateProperty);
    }

    /** @return AnObject */
    abstract protected function createObjectInstance();

}