<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityStatusRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity status (enum-like table for activity states)
 */
#[ORM\Entity(repositoryClass: ActivityStatusRepository::class)]
#[ORM\Table(name: 'akce_stav')]
#[ORM\UniqueConstraint(name: 'id_stav', columns: ['id_stav'])]
class ActivityStatus
{
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_stav', type: Types::INTEGER)]
    private int $idStav; // @phpstan-ignore-line property.onlyRead

    #[ORM\Id]
    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 128, nullable: false)]
    private string $nazev;

    public function getIdStav(): int
    {
        return $this->idStav;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function setNazev(string $nazev): self
    {
        $this->nazev = $nazev;

        return $this;
    }
}
