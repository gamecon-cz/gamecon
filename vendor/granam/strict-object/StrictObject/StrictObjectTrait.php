<?php declare(strict_types=1);

namespace Granam\Strict\Object;

trait StrictObjectTrait
{

    /**
     * @param string $name
     * @throws \Granam\Strict\Object\Exceptions\InvalidPropertyRead
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.get
     */
    public function __get($name)
    {
        $reason = 'Does not exist';
        if (\property_exists($this, $name)) {
            $reason = 'Has restricted access';
            /** @noinspection PhpUnhandledExceptionInspection */
            if ((new \ReflectionProperty($this, $name))->isProtected()) {
                $reason .= ' (is protected)';
            } else {
                $reason .= ' (is private)';
            }
        }
        throw new Exceptions\InvalidPropertyRead(
            \sprintf('Reading of property [%s->%s] fails. %s.', \get_class($this), $name, $reason)
        );
    }

    /** @noinspection MagicMethodsValidityInspection */
    /**
     * @param string $name
     * @param $value
     * @throws \Granam\Strict\Object\Exceptions\InvalidPropertyWrite
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.set
     */
    public function __set($name, $value)
    {
        $reason = 'Does not exist';
        if (\property_exists($this, $name)) {
            $reason = 'Has restricted access';
            /** @noinspection PhpUnhandledExceptionInspection */
            if ((new \ReflectionProperty($this, $name))->isProtected()) {
                $reason .= ' (is protected)';
            } else {
                $reason .= ' (is private)';
            }
        }
        throw new Exceptions\InvalidPropertyWrite(
            \sprintf('Writing to property [%s->%s] fails. %s.', \get_class($this), $name, $reason)
        );
    }

    /**
     * @param string $name
     * @throws \Granam\Strict\Object\Exceptions\InvalidPropertyWrite
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.unset
     */
    public function __unset($name)
    {
        $reason = 'Does not exist';
        if (\property_exists($this, $name)) {
            $reason = 'has restricted access';
            /** @noinspection PhpUnhandledExceptionInspection */
            if ((new \ReflectionProperty($this, $name))->isProtected()) {
                $reason .= ' (is protected)';
            } else {
                $reason .= ' (is private)';
            }
        }
        throw new Exceptions\InvalidPropertyWrite(
            \sprintf('Unset of property [%s->%s] fails. %s.', \get_class($this), $name, $reason)
        );
    }

    /**
     * @param $name
     * @param array $arguments
     * @throws \Granam\Strict\Object\Exceptions\InvalidMethodCall
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     */
    public function __call($name, array $arguments)
    {
        $reason = 'does not exist';
        if (\method_exists($this, $name)) {
            $reason = 'has restricted access';
            /** @noinspection PhpUnhandledExceptionInspection */
            if ((new \ReflectionMethod($this, $name))->isProtected()) {
                $reason .= ' (is protected)';
            } else {
                $reason .= ' (is private)';
            }
        }
        throw new Exceptions\InvalidMethodCall(\sprintf('Method [%s->%s()] %s.', \get_class($this), $name, $reason));
    }

    /**
     * @param string $name
     * @param array $arguments
     * @throws \Granam\Strict\Object\Exceptions\InvalidStaticMethodCall
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.callstatic
     */
    public static function __callStatic($name, array $arguments)
    {
        $reason = 'does not exist';
        if (\method_exists(static::class, $name)) {
            $reason = 'has restricted access';
            /** @noinspection PhpUnhandledExceptionInspection */
            if ((new \ReflectionMethod(static::class, $name))->isProtected()) {
                $reason .= ' (is protected)';
            } else {
                $reason .= ' (is private)';
            }
        }
        throw new Exceptions\InvalidStaticMethodCall(
            \sprintf('Static method [%s::%s()] %s.', static::class, $name, $reason)
        );
    }

    /**
     * @throws \Granam\Strict\Object\Exceptions\InvalidMethodCall
     * @link http://php.net/manual/en/language.oop5.magic.php#object.invoke
     */
    public function __invoke()
    {
        throw new Exceptions\InvalidMethodCall(
            \sprintf(
                'Calling object of class [%s] as a function fails. It does not implement the __invoke() method.',
                static::class
            )
        );
    }
}