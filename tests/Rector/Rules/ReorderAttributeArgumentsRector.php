<?php

declare(strict_types=1);

namespace Gamecon\Tests\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReorderAttributeArgumentsRector extends AbstractRector
{
    /**
     * @throws PoorDocumentationException
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Reorder attribute named arguments to match the constructor parameter order of the target class',
            [],
        );
    }

    /**
     * Attribute itself, not the declarations that carry it: attributes on
     * parameters and enum cases hang off nodes that a per-declaration list
     * would have to enumerate, and silently miss whichever it forgets.
     */
    public function getNodeTypes(): array
    {
        return [Attribute::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Attribute) {
            return null;
        }

        $attributeName = $this->getName($node->name);
        if ($attributeName === null) {
            return null;
        }

        $fqcn = $this->nodeNameResolver->getName($node->name);
        if (! class_exists($fqcn)) {
            return null;
        }

        $reflection = new \ReflectionClass($fqcn);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $paramOrder = [];
        foreach ($constructor->getParameters() as $param) {
            $paramOrder[] = $param->getName();
        }

        if ($node->args === []) {
            return null;
        }

        $namedArgs = [];
        $positionalArgs = [];

        foreach ($node->args as $arg) {
            if ($arg->name !== null) {
                $namedArgs[$arg->name->toString()] = $arg;
            } else {
                $positionalArgs[] = $arg;
            }
        }

        if ($namedArgs === []) {
            return null;
        }

        $orderedArgs = [];

        // Positional args must stay first; only the named tail is sorted.
        foreach ($positionalArgs as $positional) {
            $orderedArgs[] = $positional;
        }

        foreach ($paramOrder as $paramName) {
            if (isset($namedArgs[$paramName])) {
                $orderedArgs[] = $namedArgs[$paramName];
                unset($namedArgs[$paramName]);
            }
        }

        // Args naming a parameter the constructor does not have (renamed or
        // typo'd) keep their relative order rather than being dropped.
        foreach ($namedArgs as $arg) {
            $orderedArgs[] = $arg;
        }

        if ($this->argsEqual($node->args, $orderedArgs)) {
            return null;
        }

        $node->args = $orderedArgs;

        return $node;
    }

    /**
     * @param array<Arg> $a
     * @param array<Arg> $b
     */
    private function argsEqual(
        array $a,
        array $b,
    ): bool {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $i => $arg) {
            if ($arg !== $b[$i]) {
                return false;
            }
        }

        return true;
    }
}
