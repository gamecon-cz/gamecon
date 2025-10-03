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
#[ORM\Index(columns: ['id_uzivatele'], name: 'FK_role_texty_podle_uzivatele_to_uzivatele_hodnoty')]
class UserRoleText
{
    #[ORM\Id]
    #[ORM\Column(name: 'vyznam_role', type: Types::STRING, length: 48, nullable: false)]
    private string $vyznamRole;

    #[ORM\Id]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER)]
    private int $idUzivatele;

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

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

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
