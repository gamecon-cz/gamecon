<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fotka kostýmu, soubor v web/soubory/systemove/kostymy
 */
#[ORM\Entity]
#[ORM\Table(name: 'kostym_foto')]
#[ORM\UniqueConstraint(name: 'UNIQ_soubor', columns: ['soubor'])]
#[ORM\Index(name: 'IDX_id_kostymu_poradi', columns: ['id_kostymu', 'poradi'])]
class CostumePhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Costume::class, inversedBy: 'fotky')]
    #[ORM\JoinColumn(name: 'id_kostymu', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private Costume $costume;

    #[ORM\Column(name: 'soubor', type: Types::STRING, length: 255, nullable: false)]
    private string $soubor;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false, options: [
        'default' => 0,
    ])]
    private int $poradi = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCostume(): Costume
    {
        return $this->costume;
    }

    public function setCostume(Costume $costume): self
    {
        $this->costume = $costume;

        return $this;
    }

    public function getSoubor(): string
    {
        return $this->soubor;
    }

    public function setSoubor(string $soubor): self
    {
        $this->soubor = $soubor;

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
}
