<?php

/*
 * This file is part of the zenstruck/foundry package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Foundry\Object;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\ForceValue;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 * @immutable
 *
 * @phpstan-import-type Parameters from Factory
 */
final class Hydrator
{
    private static PropertyAccessor $accessor;

    /** @var string[]|true */
    private array|bool $extraAttributes = [];

    /** @var string[]|true */
    private array|bool $forceProperties = [];

    /**
     * @template T of object
     *
     * @param T $object
     * @phpstan-param Parameters $parameters
     *
     * @return T
     */
    public function __invoke(object $object, array $parameters): object
    {
        foreach ($parameters as $parameter => $value) {
            if (\is_array($this->extraAttributes) && \in_array($parameter, $this->extraAttributes, true)) {
                continue;
            }

            if (true === $this->forceProperties || \in_array($parameter, $this->forceProperties, true) || $value instanceof ForceValue) {
                if ($value instanceof ForceValue) {
                    $value = $value->value;
                }

                try {
                    self::set($object, $parameter, $value);
                } catch (\InvalidArgumentException $e) {
                    if (true !== $this->extraAttributes) {
                        throw $e;
                    }
                }

                continue;
            }

            self::$accessor ??= new PropertyAccessor();

            try {
                self::$accessor->setValue($object, $parameter, $value);
            } catch (NoSuchPropertyException $e) {
                if (true !== $this->extraAttributes) {
                    throw new \InvalidArgumentException(\sprintf('Cannot set attribute "%s" for object "%s" (not public and no setter).', $parameter, $object::class), previous: $e);
                }
            }
        }

        return $object;
    }

    public function allowExtra(string ...$parameters): self
    {
        $clone = clone $this;
        $clone->extraAttributes = $parameters ?: true;

        return $clone;
    }

    public function alwaysForce(string ...$properties): self
    {
        $clone = clone $this;
        $clone->forceProperties = $properties ?: true;

        return $clone;
    }

    public static function set(object $object, string $property, mixed $value, bool $catchErrors = false): void
    {
        $value = ForceValue::unwrap($value);

        if (
            self::isDoctrineCollection($object, $property)
            && \is_array($value)
        ) {
            $value = new ArrayCollection($value);
        }

        try {
            self::accessibleProperty($object, $property)->setValue($object, $value);
        } catch (\Throwable $e) {
            if (!$catchErrors) {
                throw $e;
            }
        }
    }

    public static function add(object $object, string $property, mixed $value): void
    {
        $inverseValue = self::get($object, $property);

        $shouldAdd = match (true) {
            $inverseValue instanceof Collection => !$inverseValue->contains($value),
            \is_array($inverseValue) => !\in_array($value, $inverseValue, true),
            $inverseValue instanceof \Traversable => !\in_array($value, \iterator_to_array($inverseValue), true),
            default => false,
        };

        if (!$shouldAdd) {
            return;
        }

        $inverseValue[] = $value;
        self::set($object, $property, $inverseValue, catchErrors: true);
    }

    public static function get(object $object, string $property): mixed
    {
        return self::accessibleProperty($object, $property)->getValue($object);
    }

    /**
     * @template T of object
     *
     * @param T $object
     * @param T $other
     */
    public static function hydrateFromOtherObject(object $object, object $other): void
    {
        $classes = [$object::class, ...\array_values(\class_parents($object))];

        $properties = [];
        foreach ($classes as $class) {
            $reflector = new \ReflectionClass($class);
            foreach ($reflector->getProperties() as $property) {
                $properties[$property->getName()] = $property->getName();
            }
        }

        foreach ($properties as $property) {
            self::set($object, $property, self::get($other, $property), catchErrors: true);
        }
    }

    private static function accessibleProperty(object $object, string $name): \ReflectionProperty
    {
        $class = new \ReflectionClass($object);

        if (!$property = self::reflectionProperty($class, $name)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not have property "%s".', $class->getName(), $name));
        }

        return $property;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private static function reflectionProperty(\ReflectionClass $class, string $name): ?\ReflectionProperty
    {
        try {
            return $class->getProperty($name);
        } catch (\ReflectionException) {
            if ($class = $class->getParentClass()) {
                return self::reflectionProperty($class, $name);
            }
        }

        return null;
    }

    private static function isDoctrineCollection(object $object, string $property): bool
    {
        $type = self::reflectionProperty(new \ReflectionClass($object), $property)?->getType();

        if (null === $type) {
            return false;
        }

        if ($type instanceof \ReflectionNamedType) {
            return Collection::class === $type->getName();
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $type) {
                if ($type instanceof \ReflectionNamedType && Collection::class === $type->getName()) {
                    return true;
                }
            }
        }

        return false;
    }
}
