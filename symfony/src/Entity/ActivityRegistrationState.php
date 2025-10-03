<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see AkcePrihlaseniStavy
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationStateRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni_stavy')]
#[ORM\UniqueConstraint(name: 'id', columns: ['id_stavu_prihlaseni'])]
class ActivityRegistrationState
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_stavu_prihlaseni', type: Types::SMALLINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'platba_procent', type: Types::SMALLINT, nullable: false, options: [
        'default' => 100.0,
    ])]
    private int $platbaProcent = 10;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function setNazev(string $nazev): static
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getPlatbaProcent(): int
    {
        return $this->platbaProcent;
    }

    public function setPlatbaProcent(int $platbaProcent): static
    {
        $this->platbaProcent = $platbaProcent;

        return $this;
    }
}
