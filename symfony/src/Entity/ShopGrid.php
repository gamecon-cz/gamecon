<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopGridRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shop grid/menu navigation structure
 *
 * Legacy @see \Gamecon\Kfc\ObchodMrizka
 */
#[ORM\Entity(repositoryClass: ShopGridRepository::class)]
#[ORM\Table(name: 'obchod_mrizky')]
class ShopGrid
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'text', type: Types::STRING, length: 255, nullable: true)]
    private ?string $text = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
