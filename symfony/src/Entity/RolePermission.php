<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Role permission mapping (many-to-many between roles and permissions)
 */
#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'prava_role')]
class RolePermission
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'id_role', referencedColumnName: 'id_role', nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(name: 'id_prava', referencedColumnName: 'id_prava', nullable: false, onDelete: 'CASCADE')]
    private Permission $permission;

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    public function setPermission(Permission $permission): self
    {
        $this->permission = $permission;

        return $this;
    }
}
