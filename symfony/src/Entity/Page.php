<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Stranka
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'stranky')]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_stranky', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'url_stranky', type: 'string', length: 64, unique: true)]
    private string $urlStranky;

    /**
     * 'markdown' in comment is important keyword, @see \DbFormGc::fieldFromDescription
     */
    #[ORM\Column(name: 'obsah', type: 'text', options: [
        'comment' => 'markdown',
    ])]
    private string $obsah;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, options: [
        'unsigned' => true,
        'default'  => 0,
    ])]
    private int $poradi;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrlStranky(): string
    {
        return $this->urlStranky;
    }

    public function setUrlStranky(string $urlStranky): self
    {
        $this->urlStranky = $urlStranky;

        return $this;
    }

    public function getObsah(): string
    {
        return $this->obsah;
    }

    public function setObsah(string $obsah): self
    {
        $this->obsah = $obsah;

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
