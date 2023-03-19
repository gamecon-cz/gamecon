<?php

namespace Gamecon\Objekt;

trait ObnoveniVychozichHodnotTrait
{
    private function obnovVychoziHodnotyObjektu() {
        $classReflection = new \ReflectionClass($this);
        foreach ($classReflection->getDefaultProperties() as $name => $defaultValue) {
            $propertyReflection = $classReflection->getProperty($name);
            if ($propertyReflection->isReadOnly()) {
                continue;
            }
            $propertyReflection->setAccessible(true);
            if ($propertyReflection->isStatic()) {
                $classReflection->setStaticPropertyValue($name, $defaultValue);
            } else {
                $propertyReflection->setValue($this, $defaultValue);
            }
        }
    }
}
