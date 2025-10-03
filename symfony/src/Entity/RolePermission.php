<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RolePermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Role permission mapping (many-to-many between roles and permissions)
 */
#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'prava_role')]
#[ORM\Index(columns: ['id_prava'], name: 'id_prava')]
class RolePermission
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_role', type: Types::INTEGER)]
    private int $idRole;

    #[ORM\Id]
    #[ORM\Column(name: 'id_prava', type: Types::INTEGER)]
    private int $idPrava;

    public function getIdRole(): int
    {
        return $this->idRole;
    }

    public function setIdRole(int $idRole): self
    {
        $this->idRole = $idRole;

        return $this;
    }

    public function getIdPrava(): int
    {
        return $this->idPrava;
    }

    public function setIdPrava(int $idPrava): self
    {
        $this->idPrava = $idPrava;

        return $this;
    }
}
