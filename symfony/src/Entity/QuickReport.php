<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\QuickReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Quick report (user-defined SQL query reports)
 */
#[ORM\Entity(repositoryClass: QuickReportRepository::class)]
#[ORM\Table(name: 'reporty_quick')]
class QuickReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 100, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'dotaz', type: Types::TEXT, nullable: false)]
    private string $dotaz;

    #[ORM\Column(name: 'format_xlsx', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 1,
    ])]
    private ?bool $formatXlsx = true;

    #[ORM\Column(name: 'format_html', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 1,
    ])]
    private ?bool $formatHtml = true;

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

    public function getDotaz(): string
    {
        return $this->dotaz;
    }

    public function setDotaz(string $dotaz): self
    {
        $this->dotaz = $dotaz;

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
}
