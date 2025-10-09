<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRoleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User role assignment (current roles assigned to users)
 */
#[ORM\Entity(repositoryClass: UserRoleRepository::class)]
#[ORM\Table(name: 'uzivatele_role')]
#[ORM\UniqueConstraint(name: 'UNIQ_id_uzivatele_id_role', columns: ['id_uzivatele', 'id_role'])]
class UserRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'id_role', referencedColumnName: 'id_role', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Role $role;

    #[ORM\Column(name: 'posazen', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $posazen;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'posadil', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $givenBy = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPosazen(): \DateTime
    {
        return $this->posazen;
    }

    public function setPosazen(\DateTime $posazen): self
    {
        $this->posazen = $posazen;

        return $this;
    }

    public function getGivenBy(): ?User
    {
        return $this->givenBy;
    }

    public function setGivenBy(?User $givenBy): self
    {
        $this->givenBy = $givenBy;

        return $this;
    }
}
