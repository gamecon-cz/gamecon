<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User badge/profile information (medailonek)
 *
 * Legacy @see \Gamecon\Uzivatel\Medailonek
 */
#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'medailonky')]
class UserBadge
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'badge', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'comment' => 'ON UPDATE CASCADE',
    ])]
    private User $user;

    /**
     * 'markdown' in comment is important keyword, @see \DbFormGc::fieldFromDescription
     */
    #[ORM\Column(name: 'o_sobe', type: Types::TEXT, nullable: false, options: [
        'comment' => 'markdown',
    ])]
    private string $oSobe;

    /**
     * 'markdown' in comment is important keyword, @see \DbFormGc::fieldFromDescription
     */
    #[ORM\Column(name: 'drd', type: Types::TEXT, nullable: false, options: [
        'comment' => 'markdown -- profil pro DrD',
    ])]
    private string $drd;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getOSobe(): string
    {
        return $this->oSobe;
    }

    public function setOSobe(string $oSobe): self
    {
        $this->oSobe = $oSobe;

        return $this;
    }

    public function getDrd(): string
    {
        return $this->drd;
    }

    public function setDrd(string $drd): self
    {
        $this->drd = $drd;

        return $this;
    }
}
