<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Report configuration
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'reporty')]
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
class Report
{
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private int $id; // @phpstan-ignore-line property.onlyRead

    #[ORM\Id]
    #[ORM\Column(name: 'skript', type: Types::STRING, length: 100, nullable: false)]
    private string $skript;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 200, nullable: true)]
    private ?string $nazev = null;

    #[ORM\Column(name: 'format_xlsx', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 1,
    ])]
    private ?bool $formatXlsx = true;

    #[ORM\Column(name: 'format_html', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 1,
    ])]
    private ?bool $formatHtml = true;

    #[ORM\Column(name: 'viditelny', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 1,
    ])]
    private ?bool $viditelny = true;

    public function getId(): int
    {
        return $this->id;
    }

    public function getSkript(): string
    {
        return $this->skript;
    }

    public function setSkript(string $skript): self
    {
        $this->skript = $skript;

        return $this;
    }

    public function getNazev(): ?string
    {
        return $this->nazev;
    }

    public function setNazev(?string $nazev): self
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getFormatXlsx(): ?bool
    {
        return $this->formatXlsx;
    }

    public function setFormatXlsx(?bool $formatXlsx): self
    {
        $this->formatXlsx = $formatXlsx;

        return $this;
    }

    public function getFormatHtml(): ?bool
    {
        return $this->formatHtml;
    }

    public function setFormatHtml(?bool $formatHtml): self
    {
        $this->formatHtml = $formatHtml;

        return $this;
    }

    public function getViditelny(): ?bool
    {
        return $this->viditelny;
    }

    public function setViditelny(?bool $viditelny): self
    {
        $this->viditelny = $viditelny;

        return $this;
    }
}
