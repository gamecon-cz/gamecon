<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'sjednocene_tagy')]
#[ORM\UniqueConstraint(name: 'nazev', columns: ['nazev'])]
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
#[ORM\Index(columns: ['id_kategorie_tagu'], name: 'id_kategorie_tagu_idx')]
class Tag
{
    public const MALOVANI = 12445; // Malování
    public const UNIKOVKA = 12444; // Únikovka

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', length: 128, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: false)]
    private string $poznamka = '';

    #[ORM\Column(name: 'id_kategorie_tagu', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $idKategorieTagu;

    #[ORM\ManyToOne(targetEntity: KategorieTag::class, inversedBy: 'tagy')]
    #[ORM\JoinColumn(name: 'id_kategorie_tagu', referencedColumnName: 'id', nullable: false)]
    private ?KategorieTag $kategorieTag = null;

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

    public function getPoznamka(): string
    {
        return $this->poznamka;
    }

    public function setPoznamka(string $poznamka): static
    {
        $this->poznamka = $poznamka;
        return $this;
    }

    public function getIdKategorieTagu(): int
    {
        return $this->idKategorieTagu;
    }

    public function setIdKategorieTagu(int $idKategorieTagu): static
    {
        $this->idKategorieTagu = $idKategorieTagu;
        return $this;
    }

    public function getKategorieTag(): ?KategorieTag
    {
        return $this->kategorieTag;
    }

    public function setKategorieTag(?KategorieTag $kategorieTag): static
    {
        $this->kategorieTag = $kategorieTag;
        if ($kategorieTag !== null) {
            $this->idKategorieTagu = $kategorieTag->getId();
        }
        return $this;
    }
}