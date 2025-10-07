<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Text content storage table
 * Used for storing text content referenced by other entities (news, activities, etc.)
 */
#[ORM\Entity(repositoryClass: TextRepository::class)]
#[ORM\Table(name: 'texty')]
class Text
{
    /**
     * Hash-based ID (not auto-increment)
     */
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::BIGINT, unique: true, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'text', type: Types::TEXT, nullable: false)]
    private string $text;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
