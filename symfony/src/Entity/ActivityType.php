<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Aktivita\AkceTypy
 */
#[ORM\Entity(repositoryClass: ActivityTypeRepository::class)]
#[ORM\Table(name: 'akce_typy')]
class ActivityType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_typu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'typ_1p', length: 32, nullable: false)]
    private string $typ1p;

    #[ORM\Column(name: 'typ_1pmn', length: 32, nullable: false)]
    private string $typ1pmn;

    #[ORM\Column(name: 'url_typu_mn', length: 32, nullable: false)]
    private string $urlTypuMn;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'stranka_o', referencedColumnName: 'id_stranky', nullable: false, onDelete: 'RESTRICT', options: [
        'ON UPDATE' => 'CASCADE',
    ])
    ]
    private Page $pageAbout;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false)]
    private int $poradi;

    #[ORM\Column(name: 'mail_neucast', type: Types::BOOLEAN, nullable: false, options: [
        'default' => false,
        'comment' => 'poslat mail účastníkovi, pokud nedorazí',
    ])]
    private bool $mailNeucast = false;

    #[ORM\Column(name: 'popis_kratky', length: 255, nullable: false)]
    private string $popisKratky;

    #[ORM\Column(name: 'aktivni', type: Types::BOOLEAN, nullable: true, options: [
        'default' => true,
    ])]
    private ?bool $aktivni = true;

    #[ORM\Column(name: 'zobrazit_v_menu', type: Types::BOOLEAN, nullable: true, options: [
        'default' => true,
    ])]
    private ?bool $zobrazitVMenu = true;

    #[ORM\Column(name: 'kod_typu', length: 20, nullable: true, options: [
        'comment' => 'kód pro identifikaci například v rozpočtovém reportu',
    ])]
    private ?string $kodTypu = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTyp1p(): string
    {
        return $this->typ1p;
    }

    public function setTyp1p(string $typ1p): static
    {
        $this->typ1p = $typ1p;

        return $this;
    }

    public function getTyp1pmn(): string
    {
        return $this->typ1pmn;
    }

    public function setTyp1pmn(string $typ1pmn): static
    {
        $this->typ1pmn = $typ1pmn;

        return $this;
    }

    public function getUrlTypuMn(): string
    {
        return $this->urlTypuMn;
    }

    public function setUrlTypuMn(string $urlTypuMn): static
    {
        $this->urlTypuMn = $urlTypuMn;

        return $this;
    }

    public function getPageAbout(): Page
    {
        return $this->pageAbout;
    }

    public function setPageAbout(Page $pageAbout): static
    {
        $this->pageAbout = $pageAbout;

        return $this;
    }

    public function getPoradi(): int
    {
        return $this->poradi;
    }

    public function setPoradi(int $poradi): static
    {
        $this->poradi = $poradi;

        return $this;
    }

    public function isMailNeucast(): bool
    {
        return $this->mailNeucast;
    }

    public function setMailNeucast(bool $mailNeucast): static
    {
        $this->mailNeucast = $mailNeucast;

        return $this;
    }

    public function getPopisKratky(): string
    {
        return $this->popisKratky;
    }

    public function setPopisKratky(string $popisKratky): static
    {
        $this->popisKratky = $popisKratky;

        return $this;
    }

    public function isAktivni(): ?bool
    {
        return $this->aktivni;
    }

    public function setAktivni(?bool $aktivni): static
    {
        $this->aktivni = $aktivni;

        return $this;
    }

    public function isZobrazitVMenu(): ?bool
    {
        return $this->zobrazitVMenu;
    }

    public function setZobrazitVMenu(?bool $zobrazitVMenu): static
    {
        $this->zobrazitVMenu = $zobrazitVMenu;

        return $this;
    }

    public function getKodTypu(): ?string
    {
        return $this->kodTypu;
    }

    public function setKodTypu(?string $kodTypu): static
    {
        $this->kodTypu = $kodTypu;

        return $this;
    }
}
