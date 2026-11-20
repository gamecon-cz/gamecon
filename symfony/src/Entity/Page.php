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

    /**
     * Empty (NULL or blank) = the page is only in Czech, @see \Stranka::isPrelozena()
     */
    #[ORM\Column(name: 'obsah_en', type: 'text', nullable: true, options: [
        'comment' => 'markdown, Obsah anglicky (prázdné = stránka je jen česky)',
    ])]
    private ?string $obsahEn = null;

    #[ORM\Column(name: 'redirect', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $redirect = false;

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

    public function getObsahEn(): ?string
    {
        return $this->obsahEn;
    }

    public function setObsahEn(?string $obsahEn): self
    {
        $this->obsahEn = $obsahEn;

        return $this;
    }

    public function isRedirect(): bool
    {
        return $this->redirect;
    }

    public function setRedirect(bool $redirect): self
    {
        $this->redirect = $redirect;

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
