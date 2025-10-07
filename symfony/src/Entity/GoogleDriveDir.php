<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GoogleDriveDirRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Google Drive directory mapping
 */
#[ORM\Entity(repositoryClass: GoogleDriveDirRepository::class)]
#[ORM\Table(name: 'google_drive_dirs')]
#[ORM\UniqueConstraint(name: 'UNIQ_dir_id', columns: ['dir_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_user_and_name', columns: ['user_id', 'original_name'])]
#[ORM\Index(columns: ['tag'], name: 'IDX_tag')]
class GoogleDriveDir
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $owner;

    #[ORM\Column(name: 'dir_id', type: Types::STRING, length: 128, nullable: false)]
    private string $dirId;

    #[ORM\Column(name: 'original_name', type: Types::STRING, length: 64, nullable: false)]
    private string $originalName;

    #[ORM\Column(name: 'tag', type: Types::STRING, length: 128, nullable: false, options: [
        'default' => '',
    ])]
    private string $tag = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getDirId(): string
    {
        return $this->dirId;
    }

    public function setDirId(string $dirId): self
    {
        $this->dirId = $dirId;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }
}
