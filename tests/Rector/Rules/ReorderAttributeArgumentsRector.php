<?php

declare(strict_types=1);

namespace Gamecon\Tests\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReorderAttributeArgumentsRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Reorder attribute named arguments to match the constructor parameter order of the target class',
            [],
        );
    }

    public function getNodeTypes(): array
    {
        return [Attribute::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Attribute) {
            return null;
        }

        $attributeName = $this->getName($node->name);
        if ($attributeName === null) {
            return null;
        }

        // resolve fully qualified class name of attribute
        $fqcn = $this->nodeNameResolver->getName($node->name);
        if (!class_exists($fqcn)) {
            return null;
        }

        $reflection  = new \ReflectionClass($fqcn);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $paramOrder = [];
        foreach ($constructor->getParameters() as $param) {
            $paramOrder[] = $param->getName();
        }

        // skip if no named args
        if ($node->args === []) {
            return null;
        }

        $namedArgs      = [];
        $positionalArgs = [];

        foreach ($node->args as $arg) {
            if ($arg->name !== null) {
                $namedArgs[$arg->name->toString()] = $arg;
            } else {
                $positionalArgs[] = $arg;
            }
        }

        // only reorder named args
        if ($namedArgs === []) {
            return null;
        }

        $orderedArgs = [];

        // keep positional args first
        foreach ($positionalArgs as $positional) {
            $orderedArgs[] = $positional;
        }

        // then reorder named args based on constructor
        foreach ($paramOrder as $paramName) {
            if (isset($namedArgs[$paramName])) {
                $orderedArgs[] = $namedArgs[$paramName];
                unset($namedArgs[$paramName]);
            }
        }

        // add leftover named args (unknown ones)
        foreach ($namedArgs as $arg) {
            $orderedArgs[] = $arg;
        }

        // only replace if order changed
        if ($this->argsEqual($node->args, $orderedArgs)) {
            return null;
        }

        $node->args = $orderedArgs;

        return $node;
    }

    private function argsEqual(
        array $a,
        array $b,
    ): bool {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $i => $arg) {
            if ($arg != $b[$i]) {
                return false;
            }
        }

        return true;
    }
}
