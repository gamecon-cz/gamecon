<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccommodationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ubytování účastníků
 * Legacy @see \Gamecon\Ubytovani\Ubytovani
 */
#[ORM\Entity(repositoryClass: AccommodationRepository::class)]
#[ORM\Table(name: 'ubytovani')]
class Accommodation
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false)]
    private User $uzivatel;

    #[ORM\Id]
    #[ORM\Column(name: 'den', type: Types::SMALLINT, nullable: false)]
    private int $den;

    #[ORM\Id]
    #[ORM\Column(name: 'rok', type: Types::SMALLINT, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'pokoj', length: 255, nullable: false)]
    private string $pokoj;

    public function getUzivatel(): User
    {
        return $this->uzivatel;
    }

    public function setUzivatel(User $uzivatel): static
    {
        $this->uzivatel = $uzivatel;

        return $this;
    }

    public function getDen(): int
    {
        return $this->den;
    }

    public function setDen(int $den): static
    {
        $this->den = $den;

        return $this;
    }

    public function getRok(): int
    {
        return $this->rok;
    }

    public function setRok(int $rok): static
    {
        $this->rok = $rok;

        return $this;
    }

    public function getPokoj(): string
    {
        return $this->pokoj;
    }

    public function setPokoj(string $pokoj): static
    {
        $this->pokoj = $pokoj;

        return $this;
    }
}
