<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Product entity for e-shop (new design without model_rok)
 *
 * Legacy: Previously known as ShopItem (@see \Gamecon\Shop\Predmet)
 *
 * Changes from legacy:
 * - Removed model_rok (products exist permanently, not recreated each year)
 * - Removed je_letosni_hlavni (no multi-year versioning)
 * - Removed typ (replaced with flexible tag system via ProductTag)
 * - Added archived_at (soft-delete instead of stav=MIMO)
 * - Added capacity_organizers/capacity_participants (separate capacities for accommodation)
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_predmety')]
#[ORM\UniqueConstraint(name: 'UNIQ_kod_predmetu', columns: ['kod_predmetu'])]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev', columns: ['nazev'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('PUBLIC_ACCESS')",
            normalizationContext: ['groups' => ['product:list']],
        ),
        new Get(
            security: "is_granted('PUBLIC_ACCESS')",
            normalizationContext: ['groups' => ['product:read']],
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['product:write']],
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['product:write']],
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['product:write']],
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact', 'name' => 'partial', 'state' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'currentPrice', 'state'])]
#[ApiFilter(RangeFilter::class, properties: ['currentPrice', 'producedQuantity'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_predmetu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    #[Groups(['product:list', 'product:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Název produktu nesmí být prázdný')]
    #[Assert\Length(max: 255, maxMessage: 'Název může mít maximálně {{ limit }} znaků')]
    #[Groups(['product:list', 'product:read', 'product:write'])]
    private string $name;

    #[ORM\Column(name: 'kod_predmetu', type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Kód produktu nesmí být prázdný')]
    #[Assert\Length(max: 255, maxMessage: 'Kód může mít maximálně {{ limit }} znaků')]
    #[Groups(['product:list', 'product:read', 'product:write'])]
    private string $code;

    #[ORM\Column(name: 'cena_aktualni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: 'Cena musí být vyplněna')]
    #[Assert\PositiveOrZero(message: 'Cena musí být kladné číslo nebo nula')]
    #[Groups(['product:list', 'product:read', 'product:write'])]
    private string $currentPrice;

    #[ORM\Column(name: 'stav', type: Types::SMALLINT, nullable: false)]
    #[Assert\Choice(
        choices: [0, 1, 2, 3],
        message: 'Neplatný stav (0=MIMO, 1=VEŘEJNÝ, 2=PODPULTOVÝ, 3=POZASTAVENÝ)'
    )]
    #[Groups(['product:list', 'product:read', 'product:write'])]
    private int $state;

    #[ORM\Column(name: 'nabizet_do', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?\DateTimeImmutable $availableUntil = null;

    #[ORM\Column(name: 'kusu_vyrobeno', type: Types::SMALLINT, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Počet vyrobených kusů musí být kladné číslo nebo nula')]
    #[Groups(['product:list', 'product:read', 'product:write'])]
    private ?int $producedQuantity = null;

    #[ORM\Column(name: 'ubytovani_den', type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'Den ubytování musí být 0-4 (St-Ne)', min: 0, max: 4)]
    #[Groups(['product:read', 'product:write'])]
    private ?int $accommodationDay = null;

    #[ORM\Column(name: 'popis', type: Types::STRING, length: 2000, nullable: false)]
    #[Assert\Length(max: 2000, maxMessage: 'Popis může mít maximálně {{ limit }} znaků')]
    #[Groups(['product:read', 'product:write'])]
    private string $description = '';

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['product:read'])]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'amount_organizers', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Množství pro organizátory musí být kladné číslo nebo nula')]
    #[Groups(['product:read', 'product:write'])]
    private ?int $amountOrganizers = null;

    #[ORM\Column(name: 'amount_participants', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Množství pro účastníky musí být kladné číslo nebo nula')]
    #[Groups(['product:read', 'product:write'])]
    private ?int $amountParticipants = null;

    /**
     * @var Collection<int, ProductTag>
     */
    #[ORM\ManyToMany(targetEntity: ProductTag::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_product_tag')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id_predmetu')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[Groups(['product:read', 'product:write'])]
    private Collection $tags;

    /**
     * @var Collection<int, ProductBundle>
     */
    #[ORM\ManyToMany(targetEntity: ProductBundle::class, mappedBy: 'products')]
    #[Groups(['product:read'])]
    private Collection $bundles;

    /**
     * @var Collection<int, ProductDiscount>
     */
    #[ORM\OneToMany(targetEntity: ProductDiscount::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $discounts;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private Collection $orderItems;

    /**
     * @var Collection<int, CancelledOrderItem>
     */
    #[ORM\OneToMany(targetEntity: CancelledOrderItem::class, mappedBy: 'product')]
    private Collection $cancelledOrderItems;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->bundles = new ArrayCollection();
        $this->discounts = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
        $this->cancelledOrderItems = new ArrayCollection();
    }

    // ==================== Getters and Setters ====================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCurrentPrice(): string
    {
        return $this->currentPrice;
    }

    public function setCurrentPrice(string $currentPrice): self
    {
        $this->currentPrice = $currentPrice;

        return $this;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function setState(int $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getAvailableUntil(): ?\DateTimeImmutable
    {
        return $this->availableUntil;
    }

    public function setAvailableUntil(?\DateTimeImmutable $availableUntil): self
    {
        $this->availableUntil = $availableUntil;

        return $this;
    }

    public function getProducedQuantity(): ?int
    {
        return $this->producedQuantity;
    }

    public function setProducedQuantity(?int $producedQuantity): self
    {
        $this->producedQuantity = $producedQuantity;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getAmountOrganizers(): ?int
    {
        return $this->amountOrganizers;
    }

    public function setAmountOrganizers(?int $amountOrganizers): self
    {
        $this->amountOrganizers = $amountOrganizers;

        return $this;
    }

    public function getAmountParticipants(): ?int
    {
        return $this->amountParticipants;
    }

    public function setAmountParticipants(?int $amountParticipants): self
    {
        $this->amountParticipants = $amountParticipants;

        return $this;
    }

    /**
     * @return Collection<int, ProductTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @return Collection<int, ProductBundle>
     */
    public function getBundles(): Collection
    {
        return $this->bundles;
    }

    /**
     * @return Collection<int, ProductDiscount>
     */
    public function getDiscounts(): Collection
    {
        return $this->discounts;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * @return Collection<int, CancelledOrderItem>
     */
    public function getCancelledOrderItems(): Collection
    {
        return $this->cancelledOrderItems;
    }

    // ==================== Helper Methods ====================

    /**
     * Check if product is archived (soft-deleted)
     */
    public function isArchived(): bool
    {
        return $this->archivedAt instanceof \DateTimeImmutable;
    }

    /**
     * Archive (soft-delete) this product
     */
    public function archive(): self
    {
        $this->archivedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Restore archived product
     */
    public function restore(): self
    {
        $this->archivedAt = null;

        return $this;
    }

    /**
     * Check if product has a specific tag by name
     */
    public function hasTag(string|ProductTag|\Stringable $searchedTag): bool
    {
        $searchedTagName = $searchedTag instanceof ProductTag
            ? $searchedTag->getCode()
            : (string) $searchedTag;
        foreach ($this->tags as $tag) {
            if ($tag->getCode() === $searchedTagName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a tag to this product (if not already present)
     */
    public function addTag(ProductTag $tag): self
    {
        if (! $this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    /**
     * Remove a tag from this product
     */
    public function removeTag(ProductTag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * Get all tag names as simple array
     *
     * @return string[]
     */
    /**
     * Get all tag names as simple array
     *
     * @return string[]
     */
    public function getTagNames(): array
    {
        return array_map(
            fn (
                ProductTag $tag,
            ): string => $tag->getCode(),
            $this->tags->toArray(),
        );
    }

    /**
     * Check if product is accommodation type (has 'ubytovani' tag)
     */
    public function isAccommodation(): bool
    {
        return $this->hasTag('ubytovani');
    }

    /**
     * Check if product has separate amount for organizers
     */
    public function hasSeparateOrganizerAmount(): bool
    {
        return $this->amountOrganizers !== null && $this->amountOrganizers > 0;
    }

    /**
     * Get total amount (organizers + participants)
     */
    public function getTotalAmount(): ?int
    {
        if ($this->amountOrganizers === null && $this->amountParticipants === null) {
            return $this->producedQuantity;
        }

        $orgAmount = $this->amountOrganizers ?? 0;
        $participantAmount = $this->amountParticipants ?? 0;

        return $orgAmount + $participantAmount;
    }

    /**
     * Check if product is available (not archived, state is public/private)
     */
    public function isAvailable(): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        // State: 0=MIMO, 1=VEŘEJNÝ, 2=PODPULTOVÝ, 3=POZASTAVENÝ
        if ($this->state === 0) {
            return false;
        }

        // Check time-based availability
        return ! ($this->availableUntil instanceof \DateTimeImmutable && $this->availableUntil < new \DateTime());
    }

    /**
     * Check if product is publicly available (state=1)
     */
    public function isPublic(): bool
    {
        return $this->isAvailable() && $this->state === 1;
    }

    /**
     * Get human-readable state name
     */
    public function getStateName(): string
    {
        return match ($this->state) {
            0       => 'Mimo',
            1       => 'Veřejný',
            2       => 'Podpultový',
            3       => 'Pozastavený',
            default => 'Neznámý',
        };
    }
}
