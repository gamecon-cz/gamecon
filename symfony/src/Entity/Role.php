<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Role
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'role_seznam')]
#[ORM\UniqueConstraint(name: 'UNIQ_kod_role', columns: ['kod_role'])]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev_role', columns: ['nazev_role'])]
#[ORM\Index(columns: ['typ_role'], name: 'IDX_typ_role')]
#[ORM\Index(columns: ['vyznam_role'], name: 'IDX_vyznam_role')]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_role', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'kod_role', length: 36, nullable: false)]
    private string $kodRole;

    #[ORM\Column(name: 'nazev_role', length: 255, nullable: false)]
    private string $nazevRole;

    #[ORM\Column(name: 'popis_role', type: Types::TEXT, nullable: false)]
    private string $popisRole;

    #[ORM\Column(name: 'rocnik_role', type: Types::INTEGER, nullable: false)]
    private int $rocnikRole;

    #[ORM\Column(name: 'typ_role', length: 24, nullable: false)]
    private string $typRole;

    #[ORM\Column(name: 'vyznam_role', length: 48, nullable: false)]
    private string $vyznamRole;

    #[ORM\Column(name: 'skryta', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 0,
    ])]
    private bool $skryta = false;

    #[ORM\Column(name: 'kategorie_role', type: Types::SMALLINT, nullable: false, options: [
        'default'  => 0,
        'unsigned' => true,
    ])]
    private int $kategorieRole = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKodRole(): string
    {
        return $this->kodRole;
    }

    public function setKodRole(string $kodRole): static
    {
        $this->kodRole = $kodRole;

        return $this;
    }

    public function getNazevRole(): string
    {
        return $this->nazevRole;
    }

    public function setNazevRole(string $nazevRole): static
    {
        $this->nazevRole = $nazevRole;

        return $this;
    }

    public function getPopisRole(): string
    {
        return $this->popisRole;
    }

    public function setPopisRole(string $popisRole): static
    {
        $this->popisRole = $popisRole;

        return $this;
    }

    public function getRocnikRole(): int
    {
        return $this->rocnikRole;
    }

    public function setRocnikRole(int $rocnikRole): static
    {
        $this->rocnikRole = $rocnikRole;

        return $this;
    }

    public function getTypRole(): string
    {
        return $this->typRole;
    }

    public function setTypRole(string $typRole): static
    {
        $this->typRole = $typRole;

        return $this;
    }

    public function getVyznamRole(): string
    {
        return $this->vyznamRole;
    }

    public function setVyznamRole(string $vyznamRole): static
    {
        $this->vyznamRole = $vyznamRole;

        return $this;
    }

    public function isSkryta(): bool
    {
        return $this->skryta;
    }

    public function setSkryta(bool $skryta): static
    {
        $this->skryta = $skryta;

        return $this;
    }

    public function getKategorieRole(): int
    {
        return $this->kategorieRole;
    }

    public function setKategorieRole(int $kategorieRole): static
    {
        $this->kategorieRole = $kategorieRole;

        return $this;
    }
}
