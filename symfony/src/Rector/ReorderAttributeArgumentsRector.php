<?php

declare(strict_types=1);

namespace App\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
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

    public function getNodeTypes(): array
    {
        return [ClassLike::class, Property::class, ClassMethod::class, ClassConst::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof ClassLike && ! $node instanceof Property && ! $node instanceof ClassMethod && ! $node instanceof ClassConst) {
            return null;
        }

        $hasChanged = false;

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                // skip if no args
                if ($attribute->args === []) {
                    continue;
                }

                // resolve fully qualified class name of attribute
                $fqcn = $this->nodeNameResolver->getName($attribute->name);
                if ($fqcn === '') {
                    continue;
                }

                if (! class_exists($fqcn)) {
                    continue;
                }

                $reflection = new \ReflectionClass($fqcn);

                $constructor = $reflection->getConstructor();
                if ($constructor === null) {
                    continue;
                }

                $paramOrder = [];
                foreach ($constructor->getParameters() as $param) {
                    $paramOrder[] = $param->getName();
                }

                $namedArgs = [];
                $positionalArgs = [];

                foreach ($attribute->args as $arg) {
                    if ($arg->name !== null) {
                        $namedArgs[$arg->name->toString()] = $arg;
                    } else {
                        $positionalArgs[] = $arg;
                    }
                }

                // only reorder if we have named args
                if ($namedArgs === []) {
                    continue;
                }

                $orderedArgs = $positionalArgs;

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
                if (! $this->argsEqual($attribute->args, $orderedArgs)) {
                    $attribute->args = $orderedArgs;
                    $hasChanged = true;
                }
            }
        }

        return $hasChanged ? $node : null;
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
