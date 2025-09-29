<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\StringNode;

/**
 * This class provides a fluent interface for defining a node.
 *
 * @author Raffaele Carelle <raffaele.carelle@gmail.com>
 */
class StringNodeDefinition extends ScalarNodeDefinition
{
    public function __construct(?string $name, ?NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->nullEquivalent = '';
    }

    protected function instantiateNode(): StringNode
    {
        return new StringNode($this->name, $this->parent, $this->pathSeparator);
    }
}
