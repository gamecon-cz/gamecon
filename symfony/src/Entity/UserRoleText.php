<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRoleTextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User role text (custom role descriptions per user)
 */
#[ORM\Entity(repositoryClass: UserRoleTextRepository::class)]
#[ORM\Table(name: 'role_texty_podle_uzivatele')]
#[ORM\UniqueConstraint(name: 'UNIQ_id_uzivatele_vyznam_role', columns: ['id_uzivatele', 'vyznam_role'])]
class UserRoleText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'vyznam_role', type: Types::STRING, length: 48, nullable: false)]
    private string $vyznamRole;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\Column(name: 'popis_role', type: Types::TEXT, nullable: true)]
    private ?string $popisRole = null;

    public function getVyznamRole(): string
    {
        return $this->vyznamRole;
    }

    public function setVyznamRole(string $vyznamRole): self
    {
        $this->vyznamRole = $vyznamRole;

        return $this;
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

    public function getPopisRole(): ?string
    {
        return $this->popisRole;
    }

    public function setPopisRole(?string $popisRole): self
    {
        $this->popisRole = $popisRole;

        return $this;
    }
}
