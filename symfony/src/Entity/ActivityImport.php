<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity import from Google Sheets
 */
#[ORM\Entity(repositoryClass: ActivityImportRepository::class)]
#[ORM\Table(name: 'akce_import')]
#[ORM\Index(columns: ['google_sheet_id'], name: 'google_sheet_id')]
#[ORM\Index(columns: ['id_uzivatele'], name: 'FK_akce_import_to_uzivatele_hodnoty')]
#[ORM\UniqueConstraint(name: 'id_akce_import', columns: ['id_akce_import'])]
class ActivityImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_akce_import', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idAkceImport = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'google_sheet_id', type: Types::STRING, length: 128, nullable: false)]
    private string $googleSheetId;

    #[ORM\Column(name: 'cas', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $cas;

    public function getIdAkceImport(): ?int
    {
        return $this->idAkceImport;
    }

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

        return $this;
    }

    public function getGoogleSheetId(): string
    {
        return $this->googleSheetId;
    }

    public function setGoogleSheetId(string $googleSheetId): self
    {
        $this->googleSheetId = $googleSheetId;

        return $this;
    }

    public function getCas(): \DateTime
    {
        return $this->cas;
    }

    public function setCas(\DateTime $cas): self
    {
        $this->cas = $cas;

        return $this;
    }
}
