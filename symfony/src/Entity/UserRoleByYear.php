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
#[ORM\Index(columns: ['id_uzivatele'], name: 'FK_uzivatele_role_podle_rocniku_to_uzivatele_hodnoty')]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id'])]
class UserRoleByYear
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'id_role', type: Types::INTEGER, nullable: false)]
    private int $idRole;

    #[ORM\Column(name: 'od_kdy', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $odKdy;

    #[ORM\Column(name: 'rocnik', type: Types::INTEGER, nullable: false)]
    private int $rocnik;

    public function getId(): ?int
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
