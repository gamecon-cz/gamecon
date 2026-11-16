<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * The code a migration names a right or a role by; it has to stay usable as the name of a constant.
 * The length is the column's, so it stays on the entity.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ConstantLikeCode extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(message: 'Kód musí být vyplněn'),
            new Assert\Regex(pattern: '/^[A-Z][A-Z0-9_]*\z/', message: 'Kód smí obsahovat jen velká písmena bez diakritiky, číslice a podtržítka a začíná písmenem'),
        ];
    }
}
