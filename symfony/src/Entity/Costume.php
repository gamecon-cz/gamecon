<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StrihKostymuEnum;
use Gamecon\Kostymy\VelikostKostymuEnum;

/**
 * Kostým v katalogu půjčovny, viz docs/generated/pujcovna-kostymu.md
 */
#[ORM\Entity]
#[ORM\Table(name: 'kostym')]
class Costume
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'popis', type: Types::TEXT, length: 65535, nullable: false)]
    private string $popis = '';

    #[ORM\Column(name: 'velikost', type: Types::STRING, length: 32, nullable: false, enumType: VelikostKostymuEnum::class)]
    private VelikostKostymuEnum $velikost;

    #[ORM\Column(name: 'miry', type: Types::STRING, length: 255, nullable: false)]
    private string $miry = '';

    #[ORM\Column(name: 'strih', type: Types::STRING, length: 32, nullable: false, enumType: StrihKostymuEnum::class)]
    private StrihKostymuEnum $strih;

    #[ORM\Column(name: 'kategorie', type: Types::STRING, length: 255, nullable: false)]
    private string $kategorie = '';

    #[ORM\Column(name: 'stav', type: Types::STRING, length: 32, nullable: false, enumType: StavKostymuEnum::class)]
    private StavKostymuEnum $stav = StavKostymuEnum::K_DISPOZICI;

    #[ORM\Column(name: 'interni_poznamka', type: Types::TEXT, length: 65535, nullable: false)]
    private string $interniPoznamka = '';

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false, options: [
        'default' => 0,
    ])]
    private int $poradi = 0;

    /**
     * @var Collection<int, CostumePhoto>
     */
    #[ORM\OneToMany(targetEntity: CostumePhoto::class, mappedBy: 'costume')]
    #[ORM\OrderBy([
        'poradi' => 'ASC',
    ])]
    private Collection $fotky;

    public function __construct()
    {
        $this->fotky = new ArrayCollection();
    }

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

    public function getPopis(): string
    {
        return $this->popis;
    }

    public function setPopis(string $popis): self
    {
        $this->popis = $popis;

        return $this;
    }

    public function getVelikost(): VelikostKostymuEnum
    {
        return $this->velikost;
    }

    public function setVelikost(VelikostKostymuEnum $velikost): self
    {
        $this->velikost = $velikost;

        return $this;
    }

    public function getMiry(): string
    {
        return $this->miry;
    }

    public function setMiry(string $miry): self
    {
        $this->miry = $miry;

        return $this;
    }

    public function getStrih(): StrihKostymuEnum
    {
        return $this->strih;
    }

    public function setStrih(StrihKostymuEnum $strih): self
    {
        $this->strih = $strih;

        return $this;
    }

    public function getKategorie(): string
    {
        return $this->kategorie;
    }

    public function setKategorie(string $kategorie): self
    {
        $this->kategorie = $kategorie;

        return $this;
    }

    public function getStav(): StavKostymuEnum
    {
        return $this->stav;
    }

    public function setStav(StavKostymuEnum $stav): self
    {
        $this->stav = $stav;

        return $this;
    }

    public function getInterniPoznamka(): string
    {
        return $this->interniPoznamka;
    }

    public function setInterniPoznamka(string $interniPoznamka): self
    {
        $this->interniPoznamka = $interniPoznamka;

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

    /**
     * @return Collection<int, CostumePhoto>
     */
    public function getFotky(): Collection
    {
        return $this->fotky;
    }
}
