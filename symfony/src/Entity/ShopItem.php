<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Shop\Predmet
 */
#[ORM\Entity(repositoryClass: ShopItemRepository::class)]
#[ORM\Table(name: 'shop_predmety')]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev', columns: ['nazev'])]
#[ORM\UniqueConstraint(name: 'UNIQ_kod_predmetu', columns: ['kod_predmetu'])]
class ShopItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_predmetu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'kod_predmetu', type: Types::STRING, length: 255, nullable: false)]
    private string $kodPredmetu;

    #[ORM\Column(name: 'cena_aktualni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false)]
    private string $cenaAktualni;

    #[ORM\Column(name: 'stav', type: Types::SMALLINT, nullable: false)]
    private int $stav;

    #[ORM\Column(name: 'nabizet_do', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $nabizetDo = null;

    #[ORM\Column(name: 'kusu_vyrobeno', type: Types::SMALLINT, nullable: true)]
    private ?int $kusuVyrobeno = null;

    #[ORM\Column(name: 'ubytovani_den', type: Types::SMALLINT, nullable: true)]
    private ?int $ubytovaniDen = null;

    #[ORM\Column(name: 'popis', type: Types::STRING, length: 2000, nullable: false)]
    private string $popis;

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'reserved_for_organizers', type: Types::INTEGER, nullable: true)]
    private ?int $reservedForOrganizers = null;

    #[ORM\Column(name: 'vedlejsi', type: Types::BOOLEAN, nullable: false, options: [
        'default' => false,
    ])]
    private bool $vedlejsi = false;

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

    public function getKodPredmetu(): string
    {
        return $this->kodPredmetu;
    }

    public function setKodPredmetu(string $kodPredmetu): self
    {
        $this->kodPredmetu = $kodPredmetu;

        return $this;
    }

    public function getCenaAktualni(): string
    {
        return $this->cenaAktualni;
    }

    public function setCenaAktualni(string $cenaAktualni): self
    {
        $this->cenaAktualni = $cenaAktualni;

        return $this;
    }

    public function getStav(): int
    {
        return $this->stav;
    }

    public function setStav(int $stav): self
    {
        $this->stav = $stav;

        return $this;
    }

    public function getNabizetDo(): ?\DateTime
    {
        return $this->nabizetDo;
    }

    public function setNabizetDo(?\DateTime $nabizetDo): self
    {
        $this->nabizetDo = $nabizetDo;

        return $this;
    }

    public function getKusuVyrobeno(): ?int
    {
        return $this->kusuVyrobeno;
    }

    public function setKusuVyrobeno(?int $kusuVyrobeno): self
    {
        $this->kusuVyrobeno = $kusuVyrobeno;

        return $this;
    }

    public function getUbytovaniDen(): ?int
    {
        return $this->ubytovaniDen;
    }

    public function setUbytovaniDen(?int $ubytovaniDen): self
    {
        $this->ubytovaniDen = $ubytovaniDen;

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

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getReservedForOrganizers(): ?int
    {
        return $this->reservedForOrganizers;
    }

    public function setReservedForOrganizers(?int $reservedForOrganizers): self
    {
        $this->reservedForOrganizers = $reservedForOrganizers;

        return $this;
    }

    public function isVedlejsi(): bool
    {
        return $this->vedlejsi;
    }

    public function setVedlejsi(bool $vedlejsi): self
    {
        $this->vedlejsi = $vedlejsi;

        return $this;
    }
}
