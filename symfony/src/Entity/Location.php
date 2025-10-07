<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Lokace
 */
#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'akce_lokace')]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev_rok', columns: ['nazev', 'rok'])]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_lokace', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'dvere', type: Types::STRING, length: 255, nullable: false)]
    private string $dvere;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: false)]
    private string $poznamka;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false)]
    private int $poradi;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: true, options: [
        'default' => 0,
    ])]
    private ?int $rok = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDvere(): string
    {
        return $this->dvere;
    }

    public function setDvere(string $dvere): self
    {
        $this->dvere = $dvere;

        return $this;
    }

    public function getPoznamka(): string
    {
        return $this->poznamka;
    }

    public function setPoznamka(string $poznamka): self
    {
        $this->poznamka = $poznamka;

        return $this;
    }

    public function getPoradi(): int
    {
        return $this->poradi;
    }

    public function setPoradi(int $poradi): self
    {
        $this->poradi = $poradi;

        return $this;
    }

    public function getRok(): ?int
    {
        return $this->rok;
    }

    public function setRok(?int $rok): self
    {
        $this->rok = $rok;

        return $this;
    }
}
