<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Partials\WithTimestampsInterface;
use App\Entity\Partials\WithTimestampsTrait;
use App\Repository\ProductTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductTag - master list of product tags
 *
 * Replaces the old fixed 'typ' field (1-7) with a flexible tag system.
 *
 * Common tags:
 * - 'predmet' (merchandise - dice, pins, notebooks)
 * - 'ubytovani' (accommodation)
 * - 'tricko' (t-shirts)
 * - 'jidlo' (food)
 * - 'vstupne' (entrance fee)
 * - 'parcon' (ParCon mini-event)
 * - 'proplaceni-bonusu' (bonus payout - internal)
 *
 * Product-specific tags (from old kod_predmetu detection):
 * - 'kostka' (dice)
 * - 'placka' (badge/pin)
 * - 'nicknack' (nicknack)
 * - 'blok' (notebook)
 * - 'ponozka' (socks)
 * - 'taska' (bag)
 * - 'snidane' (breakfast)
 * - 'obed' (lunch)
 * - 'vecere' (dinner)
 * - 'tilko' (tank top)
 *
 * Special tags:
 * - 'org-merch' (organizer merchandise - special pricing)
 */
#[ORM\Entity(repositoryClass: ProductTagRepository::class)]
#[ORM\Table(name: 'product_tag')]
#[ORM\UniqueConstraint(name: 'UNIQ_name', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Tag s tímto názvem již existuje')]
class ProductTag implements WithTimestampsInterface
{
    use WithTimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Assert\NotBlank(message: 'Kód tagu nesmí být prázdný')]
    #[Assert\Length(max: 50, maxMessage: 'Kód tagu může mít maximálně {{ limit }} znaků')]
    #[Assert\Regex(
        pattern: '/^[a-z0-9\-]+$/',
        message: 'Kód tagu může obsahovat pouze malá písmena, číslice a pomlčky'
    )]
    private string $code;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'tags')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        // Normalize tag code: lowercase, trim
        $this->code = strtolower(trim($code));

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name ?? ucfirst($this->getCode());
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (! $this->products->contains($product)) {
            $this->products->add($product);
            $product->addTag($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product)) {
            $product->removeTag($this);
        }

        return $this;
    }
}
