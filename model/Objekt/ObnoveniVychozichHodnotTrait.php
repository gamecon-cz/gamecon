<?php

namespace Gamecon\Objekt;

trait ObnoveniVychozichHodnotTrait
{
    private function obnovVychoziHodnotyObjektu(): void
    {
        $classReflection = new \ReflectionClass($this);
        foreach ($classReflection->getDefaultProperties() as $name => $defaultValue) {
            $propertyReflection = $classReflection->getProperty($name);
            if ($propertyReflection->isReadOnly()) {
                continue;
            }
            if ($propertyReflection->isStatic()) {
                $classReflection->setStaticPropertyValue($name, $defaultValue);
            } else {
                $propertyReflection->setValue($this, $defaultValue);
            }
        }
    }
}
