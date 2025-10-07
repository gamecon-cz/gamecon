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
#[ORM\Index(columns: ['google_sheet_id'], name: 'IDX_google_sheet_id')]
class ActivityImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_akce_import', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\Column(name: 'google_sheet_id', type: Types::STRING, length: 128, nullable: false)]
    private string $googleSheetId;

    #[ORM\Column(name: 'cas', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $cas;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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
