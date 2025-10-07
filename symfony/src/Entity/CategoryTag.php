<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\KategorieTagu
 */
#[ORM\Entity(repositoryClass: CategoryTagRepository::class)]
#[ORM\Table(name: 'kategorie_sjednocenych_tagu')]
#[ORM\Index(columns: ['nazev'], name: 'IDX_nazev')]
class CategoryTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', length: 128, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $poradi = 0;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'id_hlavni_kategorie', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?self $mainCategoryTag = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\OneToMany(mappedBy: 'categoryTag', targetEntity: Tag::class)]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function setNazev(string $nazev): static
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getPoradi(): int
    {
        return $this->poradi;
    }

    public function setPoradi(int $poradi): static
    {
        $this->poradi = $poradi;

        return $this;
    }

    public function getMainCategoryTag(): ?self
    {
        return $this->mainCategoryTag;
    }

    public function setMainCategoryTag(?self $mainCategoryTag): static
    {
        $this->mainCategoryTag = $mainCategoryTag;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (! $this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->setCategoryTag($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag) && $tag->getCategoryTag() === $this) {
            $tag->setCategoryTag(null);
        }

        return $this;
    }
}
