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
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
#[ORM\Index(name: 'posadil', columns: ['posadil'])]
#[ORM\Index(name: 'FK_uzivatele_role_role_seznam', columns: ['id_role'])]
class UserRole
{
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $id; // @phpstan-ignore-line property.onlyRead

    #[ORM\Id]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER)]
    private int $idUzivatele;

    #[ORM\Id]
    #[ORM\Column(name: 'id_role', type: Types::INTEGER)]
    private int $idRole;

    #[ORM\Column(name: 'posazen', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $posazen;

    #[ORM\Column(name: 'posadil', type: Types::INTEGER, nullable: true)]
    private ?int $posadil = null;

    public function getId(): int
    {
        return $this->id;
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

    public function getIdRole(): int
    {
        return $this->idRole;
    }

    public function setIdRole(int $idRole): self
    {
        $this->idRole = $idRole;

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

    public function getPosadil(): ?int
    {
        return $this->posadil;
    }

    public function setPosadil(?int $posadil): self
    {
        $this->posadil = $posadil;

        return $this;
    }
}
