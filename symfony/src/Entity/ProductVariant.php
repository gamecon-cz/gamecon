<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoleMeaning;
use App\Repository\ProductVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductVariant - a specific variant of a Product (e.g. size M, Friday night)
 *
 * Price and reserved_for_organizers are nullable — null means "inherit from parent Product".
 * remaining_quantity is per-variant (each size has its own stock).
 */
#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\Table(name: 'product_variant')]
#[ORM\UniqueConstraint(name: 'UNIQ_variant_code', columns: ['code'])]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    #[Groups(['product:read', 'variant:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id_predmetu', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['variant:read'])]
    private Product $product;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Název varianty nesmí být prázdný')]
    #[Assert\Length(max: 255)]
    #[Groups(['product:read', 'variant:read', 'variant:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Kód varianty nesmí být prázdný')]
    #[Assert\Length(max: 255)]
    #[Groups(['product:read', 'variant:read', 'variant:write'])]
    private string $code;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Cena musí být kladné číslo nebo nula')]
    #[Groups(['product:read', 'variant:read', 'variant:write'])]
    private ?string $price = null;

    #[ORM\Column(name: 'remaining_quantity', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Zbývající množství musí být kladné číslo nebo nula')]
    #[Groups(['product:read', 'variant:read', 'variant:write'])]
    private ?int $remainingQuantity = null;

    #[ORM\Column(name: 'reserved_for_organizers', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Rezervace pro organizátory musí být kladné číslo nebo nula')]
    #[Groups(['variant:read', 'variant:write'])]
    private ?int $reservedForOrganizers = null;

    #[ORM\Column(name: 'accommodation_day', type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'Den ubytování musí být 0-4 (St-Ne)', min: 0, max: 4)]
    #[Groups(['product:read', 'variant:read', 'variant:write'])]
    private ?int $accommodationDay = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, options: [
        'default' => 0,
    ])]
    #[Groups(['variant:read', 'variant:write'])]
    private int $position = 0;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'variant')]
    private Collection $orderItems;

    /**
     * @var Collection<int, ProductBundle>
     */
    #[ORM\ManyToMany(targetEntity: ProductBundle::class, mappedBy: 'variants')]
    private Collection $bundles;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->bundles = new ArrayCollection();
    }

    // ==================== Getters and Setters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getRemainingQuantity(): ?int
    {
        return $this->remainingQuantity;
    }

    public function setRemainingQuantity(?int $remainingQuantity): self
    {
        $this->remainingQuantity = $remainingQuantity;

        return $this;
    }

    public function getReservedForOrganizers(): ?int
    {
        return $this->reservedForOrganizers;
    }

    public function setReservedForOrganizers(?int $reservedForOrganizers): self
    {
        $this->reservedForOrganizers = $reservedForOrganizers;

        return $this;
    }

    public function getAccommodationDay(): ?int
    {
        return $this->accommodationDay;
    }

    public function setAccommodationDay(?int $accommodationDay): self
    {
        $this->accommodationDay = $accommodationDay;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * @return Collection<int, ProductBundle>
     */
    public function getBundles(): Collection
    {
        return $this->bundles;
    }

    // ==================== Inherited/Effective Values ====================

    /**
     * Get effective price — own price or inherited from parent Product
     */
    public function getEffectivePrice(): string
    {
        return $this->price ?? $this->product->getCurrentPrice();
    }

    /**
     * Get effective reserved_for_organizers — own or inherited from parent Product
     */
    public function getEffectiveReservedForOrganizers(): ?int
    {
        return $this->reservedForOrganizers ?? $this->product->getReservedForOrganizers();
    }

    /**
     * Check if variant has limited capacity
     */
    public function hasLimitedCapacity(): bool
    {
        return $this->remainingQuantity !== null;
    }

    /**
     * Get available quantity for given role meanings
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function getAvailableQuantity(array $roleMeanings = []): ?int
    {
        if ($this->remainingQuantity === null) {
            return null; // unlimited
        }

        if (RoleMeaning::anyIsOrganizer($roleMeanings)) {
            return $this->remainingQuantity;
        }

        $reserved = $this->getEffectiveReservedForOrganizers() ?? 0;

        return max(0, $this->remainingQuantity - $reserved);
    }

    /**
     * Get full display name: "Product — Variant"
     */
    public function getFullName(): string
    {
        return $this->product->getName() . ' — ' . $this->name;
    }
}
