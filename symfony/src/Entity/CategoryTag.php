<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryTagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: CategoryTagRepository::class)]
#[ORM\Table(name: 'kategorie_sjednocenych_tagu')]
#[ORM\UniqueConstraint(name: 'nazev', columns: ['nazev'])]
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
#[ORM\Index(columns: ['id_hlavni_kategorie'], name: 'id_hlavni_kategorie_idx')]
class CategoryTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', length: 128, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $poradi = 0;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'id_hlavni_kategorie', referencedColumnName: 'id', nullable: true)]
    private ?self $hlavniKategorie = null;

    /** @var Collection<int, Tag> */
    #[ORM\OneToMany(mappedBy: 'kategorieTag', targetEntity: Tag::class)]
    private Collection $tagy;

    public function __construct()
    {
        $this->tagy = new ArrayCollection();
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

    public function getHlavniKategorie(): ?self
    {
        return $this->hlavniKategorie;
    }

    public function setHlavniKategorie(?self $hlavniKategorie): static
    {
        $this->hlavniKategorie = $hlavniKategorie;
        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTagy(): Collection
    {
        return $this->tagy;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tagy->contains($tag)) {
            $this->tagy->add($tag);
            $tag->setKategorieTag($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tagy->removeElement($tag)) {
            if ($tag->getKategorieTag() === $this) {
                $tag->setKategorieTag(null);
            }
        }

        return $this;
    }
}
