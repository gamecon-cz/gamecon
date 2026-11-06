<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Util\ClassSource\Model;

/**
 * @author Benjamin Georgeault<git@wedgesama.fr>
 *
 * @internal
 */
final class MethodArgument
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $type = null,
        private readonly ?string $default = null,
        private readonly bool $isVariadic = false,
    ) {
    }

    public function getDeclaration(): string
    {
        return ($this->type ?? '').
            ($this->type ? ' ' : '').
            ($this->isVariadic ? '...' : '').
            $this->getVariable().
            ($this->isVariadic || !$this->default ? '' : ' = '.$this->default)
        ;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    public function getVariable(): string
    {
        return '$'.$this->name;
    }
}
