<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopGridCellRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cell/button in shop grid navigation
 *
 * Legacy @see \Gamecon\Kfc\ObchodMrizkaBunka
 */
#[ORM\Entity(repositoryClass: ShopGridCellRepository::class)]
#[ORM\Table(name: 'obchod_bunky')]
class ShopGridCell
{
    public const TYPE_ITEM = 0;    // předmět
    public const TYPE_PAGE = 1;    // stránka
    public const TYPE_BACK = 2;    // zpět
    public const TYPE_SUMMARY = 3; // shrnutí

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'typ', type: Types::SMALLINT, nullable: false, options: [
        'comment' => '0-předmět, 1-stránka, 2-zpět, 3-shrnutí',
    ])]
    private int $typ;

    #[ORM\Column(name: 'text', type: Types::STRING, length: 255, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(name: 'barva', type: Types::STRING, length: 255, nullable: true)]
    private ?string $barva = null;

    #[ORM\Column(name: 'barva_text', type: Types::STRING, length: 255, nullable: true)]
    private ?string $barvaText = null;

    #[ORM\Column(name: 'cil_id', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'Id cílove mřížky nebo předmětu.',
    ])]
    private ?int $cilId = null;

    #[ORM\Column(name: 'mrizka_id', type: Types::INTEGER, nullable: true)]
    private ?int $mrizkaId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTyp(): int
    {
        return $this->typ;
    }

    public function setTyp(int $typ): self
    {
        $this->typ = $typ;

        return $this;
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

    public function getBarva(): ?string
    {
        return $this->barva;
    }

    public function setBarva(?string $barva): self
    {
        $this->barva = $barva;

        return $this;
    }

    public function getBarvaText(): ?string
    {
        return $this->barvaText;
    }

    public function setBarvaText(?string $barvaText): self
    {
        $this->barvaText = $barvaText;

        return $this;
    }

    public function getCilId(): ?int
    {
        return $this->cilId;
    }

    public function setCilId(?int $cilId): self
    {
        $this->cilId = $cilId;

        return $this;
    }

    public function getMrizkaId(): ?int
    {
        return $this->mrizkaId;
    }

    public function setMrizkaId(?int $mrizkaId): self
    {
        $this->mrizkaId = $mrizkaId;

        return $this;
    }
}
