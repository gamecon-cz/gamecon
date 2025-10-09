<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Novinka
 */
#[ORM\Entity(repositoryClass: NewsRepository::class)]
#[ORM\Table(name: 'novinky')]
#[ORM\UniqueConstraint(name: 'UNIQ_url', columns: ['url'])]
class News
{
    public const TYPE_NEWS = 1;

    public const TYPE_BLOG = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'typ', type: Types::SMALLINT, nullable: false, options: [
        'default' => 1,
        'comment' => '1-novinka 2-blog',
    ])]
    private int $typ = self::TYPE_NEWS;

    #[ORM\Column(name: 'vydat', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $vydat = null;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 100, nullable: false)]
    private string $url;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 200, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'autor', type: Types::STRING, length: 100, nullable: true)]
    private ?string $autor = null;

    #[ORM\ManyToOne(targetEntity: Text::class)]
    #[ORM\JoinColumn(name: 'text', nullable: false, onDelete: 'RESTRICT')]
    private Text $text;

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

    public function getVydat(): ?\DateTime
    {
        return $this->vydat;
    }

    public function setVydat(?\DateTime $vydat): self
    {
        $this->vydat = $vydat;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
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

    public function getAutor(): ?string
    {
        return $this->autor;
    }

    public function setAutor(?string $autor): self
    {
        $this->autor = $autor;

        return $this;
    }

    public function getText(): Text
    {
        return $this->text;
    }

    public function setText(Text $text): self
    {
        $this->text = $text;

        return $this;
    }
}
