<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRoleByYearRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User role by year (role assignments scoped to specific year)
 */
#[ORM\Entity(repositoryClass: UserRoleByYearRepository::class)]
#[ORM\Table(name: 'uzivatele_role_podle_rocniku')]
#[ORM\Index(fields: ['rocnik'], name: 'idx_uzivatele_role_podle_rocniku_rocnik')]
class UserRoleByYear
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

    #[ORM\Column(name: 'od_kdy', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $odKdy;

    #[ORM\Column(name: 'rocnik', type: Types::INTEGER, nullable: false)]
    private int $rocnik;

    public function getId(): ?int
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

    public function getOdKdy(): \DateTime
    {
        return $this->odKdy;
    }

    public function setOdKdy(\DateTime $odKdy): self
    {
        $this->odKdy = $odKdy;

        return $this;
    }

    public function getRocnik(): int
    {
        return $this->rocnik;
    }

    public function setRocnik(int $rocnik): self
    {
        $this->rocnik = $rocnik;

        return $this;
    }
}
