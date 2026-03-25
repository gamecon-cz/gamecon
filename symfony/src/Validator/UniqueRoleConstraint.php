<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that a user cannot have two roles with UNIKATNI_ROLE permission.
 *
 * Applied to the UserRole entity (class-level constraint).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueRoleConstraint extends Constraint
{
    public string $message = 'Uživatel už má jinou unikátní roli.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
