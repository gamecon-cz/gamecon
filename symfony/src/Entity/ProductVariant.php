<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ProductStateEnum;
use App\Repository\ProductVariantRepository;
use App\Validator as AppAssert;
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
 * Capacity lives here; remaining stock is not stored, CapacityManager counts it from the purchases.
 *
 * Two write paths are exposed:
 * - Nested via Product: admins edit a product and all its variants as a
 *   single form, submitting PATCH /products/{id} with the whole nested body.
 *   Doctrine `cascade` + `orphanRemoval` handle create/update/delete.
 * - Direct CRUD: GET/POST/PUT/PATCH/DELETE on /product_variants, useful for
 *   future AJAX flows that edit one variant without round-tripping the whole parent product.
 */
#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\Table(name: 'product_variant')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_variant_code', columns: ['code'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            validationContext: [
                'groups' => [self::DELETE],
            ],
            validate: true,
        ),
    ],
    normalizationContext: [
        'groups' => [self::READ],
    ],
    denormalizationContext: [
        'groups' => [self::WRITE],
    ],
)]
#[AppAssert\SoldVariantIsKept(groups: [self::DELETE])]
#[AppAssert\DefaultVariantFollowsProduct]
class ProductVariant
{
    public const READ = 'variant:read';

    public const WRITE = 'variant:write';

    public const DELETE = 'variant:delete';

    /**
     * Night names by `accommodation_day`, Wednesday to Sunday.
     */
    public const NIGHT_NAMES = ['středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    #[Groups([Product::READ, self::READ])]
    private ?int $id = null;

    /**
     * Rules over a product's variants live on the product, so a variant saved on its own is checked there.
     * Only on save: an ungrouped Valid cascades into every group, and deleting would then re-check the siblings.
     */
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id_predmetu', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::READ, self::WRITE])]
    #[Assert\Valid(groups: ['Default'])]
    private Product $product;

    /**
     * Null for a product's only variant, which is shown as the product itself.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Kód varianty nesmí být prázdný')]
    #[Assert\Length(max: 255)]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private string $code;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Cena musí být kladné číslo nebo nula')]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private ?string $price = null;

    /**
     * Null = unlimited.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Kapacita musí být kladné číslo nebo nula')]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private ?int $capacity = null;

    #[ORM\Column(name: 'reserved_for_organizers', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Rezervace pro organizátory musí být kladné číslo nebo nula')]
    #[Groups([self::READ, self::WRITE])]
    private ?int $reservedForOrganizers = null;

    #[ORM\Column(name: 'accommodation_day', type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'Den ubytování musí být 0-4 (St-Ne)', min: 0, max: 4)]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private ?int $accommodationDay = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, options: [
        'default' => 0,
    ])]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private int $position = 0;

    /**
     * Whether this night or size is on offer. Its own, not the product's: a room type is not on
     * sale itself while its nights are. A default variant follows its product
     * (VariantStateMirror).
     */
    #[ORM\Column(name: 'state', type: Types::SMALLINT, nullable: false, enumType: ProductStateEnum::class)]
    #[Groups([Product::READ, Product::WRITE, self::READ, self::WRITE])]
    private ProductStateEnum $state;

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
        $product->addVariant($this);

        return $this;
    }

    /**
     * A new variant is offered as its product is, unless it was given a state of its own. On
     * persist, because an API client may send the variants before the product's state.
     */
    #[ORM\PrePersist]
    public function startOfferedAsProduct(): void
    {
        $this->state ??= $this->product->getState();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = trim((string) $name) === '' ? null : $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function hasCode(): bool
    {
        return isset($this->code);
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

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): self
    {
        $this->capacity = $capacity;

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

    public function getState(): ProductStateEnum
    {
        return $this->state;
    }

    public function hasOwnState(): bool
    {
        return isset($this->state);
    }

    public function setState(ProductStateEnum $state): self
    {
        $this->state = $state;

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
     * An imported night is its own product with a nameless variant; its day still names it.
     */
    public function getNightName(): ?string
    {
        return $this->name ?? ($this->accommodationDay === null ? null : self::NIGHT_NAMES[$this->accommodationDay] ?? null);
    }

    public function getFullName(): string
    {
        return $this->name === null
            ? $this->product->getName()
            : $this->product->getName() . ' — ' . $this->name;
    }
}
