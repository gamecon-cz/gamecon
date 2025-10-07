<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityStatusRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Aktivita\AkceStavy
 */
#[ORM\Entity(repositoryClass: ActivityStatusRepository::class)]
#[ORM\Table(name: 'akce_stav')]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev', columns: ['nazev'])]
class ActivityStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_stav', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', length: 128, nullable: false)]
    private string $nazev;

    public function getId(): ?int
    {
        return $this->id;
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
}
