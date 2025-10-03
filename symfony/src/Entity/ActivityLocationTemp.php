<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLocationTempRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Temporary activity location for year-based configuration
 */
#[ORM\Entity(repositoryClass: ActivityLocationTempRepository::class)]
#[ORM\Table(name: 'akce_lokace_tmp')]
#[ORM\UniqueConstraint(name: 'nazev_rok', columns: ['nazev', 'rok'])]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id_lokace'])]
class ActivityLocationTemp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_lokace', type: Types::INTEGER)]
    private ?int $idLokace = null;

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

    public function getIdLokace(): ?int
    {
        return $this->idLokace;
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
