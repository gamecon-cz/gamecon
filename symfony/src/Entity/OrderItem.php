<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OrderItem - item in an order with product snapshot (SHOULD requirement - zamrazení ceny)
 *
 * Snapshot ensures:
 * - Product name/code/description are preserved even if product is deleted
 * - Purchase price is frozen at time of purchase (not affected by price changes)
 * - Discount information is tracked (originalPrice, discountAmount, discountReason)
 *
 * Migration from shop_nakupy:
 * - Maps to same table (shop_nakupy)
 * - Adds snapshot fields: product_name, product_code, product_description
 * - Adds discount tracking: original_price, discount_amount, discount_reason
 * - product relation becomes nullable (product can be deleted)
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'shop_nakupy')]
#[ORM\Index(name: 'IDX_rok_id_uzivatele', columns: ['rok', 'id_uzivatele'])]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    #[Assert\NotNull(message: 'Zákazník musí být vyplněn')]
    private ?User $customer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_objednatele', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $orderer = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'id_predmetu', referencedColumnName: 'id_predmetu', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'variant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $variant = null;

    #[ORM\ManyToOne(targetEntity: ProductBundle::class)]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductBundle $bundle = null;

    #[ORM\Column(name: 'rok', type: Types::SMALLINT, nullable: false)]
    #[Assert\Positive(message: 'Rok musí být kladné číslo')]
    private int $year;

    #[ORM\Column(name: 'product_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $productName = null;

    #[ORM\Column(name: 'product_code', type: Types::STRING, length: 255, nullable: true)]
    private ?string $productCode = null;

    #[ORM\Column(name: 'product_description', type: Types::TEXT, nullable: true)]
    private ?string $productDescription = null;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'product_tags', type: Types::JSON, nullable: true, options: [
        'default' => '[]',
    ])]
    private array $productTags = [];

    #[ORM\Column(name: 'variant_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $variantName = null;

    #[ORM\Column(name: 'variant_code', type: Types::STRING, length: 255, nullable: true)]
    private ?string $variantCode = null;

    #[ORM\Column(name: 'cena_nakupni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false, options: [
        'comment' => 'Final purchase price (after discounts)',
    ])]
    #[Assert\NotBlank(message: 'Cena nákupu musí být vyplněna')]
    #[Assert\PositiveOrZero(message: 'Cena musí být kladné číslo nebo nula')]
    private string $purchasePrice;

    #[ORM\Column(name: 'original_price', type: Types::DECIMAL, precision: 6, scale: 2, nullable: true, options: [
        'comment' => 'Original price before discounts',
    ])]
    private ?string $originalPrice = null;

    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 6, scale: 2, nullable: true, options: [
        'comment' => 'Discount amount in CZK',
    ])]
    private ?string $discountAmount = null;

    #[ORM\Column(name: 'discount_reason', type: Types::STRING, length: 255, nullable: true, options: [
        'comment' => 'Reason for discount (e.g., "Organizátor - kostka zdarma")',
    ])]
    private ?string $discountReason = null;

    #[ORM\Column(name: 'datum', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $purchasedAt;

    public function __construct()
    {
        $this->purchasedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getOrderer(): ?User
    {
        return $this->orderer;
    }

    public function setOrderer(?User $orderer): self
    {
        $this->orderer = $orderer;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function getBundle(): ?ProductBundle
    {
        return $this->bundle;
    }

    public function setBundle(?ProductBundle $bundle): self
    {
        $this->bundle = $bundle;

        return $this;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function setVariantName(?string $variantName): self
    {
        $this->variantName = $variantName;

        return $this;
    }

    public function getVariantCode(): ?string
    {
        return $this->variantCode;
    }

    public function setVariantCode(?string $variantCode): self
    {
        $this->variantCode = $variantCode;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(?string $productName): self
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): self
    {
        $this->productCode = $productCode;

        return $this;
    }

    public function getProductDescription(): ?string
    {
        return $this->productDescription;
    }

    public function setProductDescription(?string $productDescription): self
    {
        $this->productDescription = $productDescription;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getProductTags(): array
    {
        return $this->productTags;
    }

    /**
     * @param array<string> $productTags
     */
    public function setProductTags(array $productTags): self
    {
        $this->productTags = $productTags;

        return $this;
    }

    public function getPurchasePrice(): string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getOriginalPrice(): ?string
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(?string $originalPrice): self
    {
        $this->originalPrice = $originalPrice;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): self
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getDiscountReason(): ?string
    {
        return $this->discountReason;
    }

    public function setDiscountReason(?string $discountReason): self
    {
        $this->discountReason = $discountReason;

        return $this;
    }

    public function getPurchasedAt(): \DateTimeInterface
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(\DateTimeInterface $purchasedAt): self
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    // ==================== Helper Methods ====================

    /**
     * Create snapshot from product (and optionally variant)
     */
    public function snapshotProduct(Product $product, ?ProductVariant $variant = null): self
    {
        $this->productName = $product->getName();
        $this->productCode = $product->getCode();
        $this->productDescription = $product->getDescription();

        if ($variant !== null) {
            $this->variantName = $variant->getName();
            $this->variantCode = $variant->getCode();
        }

        // If original price not set, use effective price (variant price or product price)
        if ($this->originalPrice === null) {
            $this->originalPrice = $variant !== null
                ? $variant->getEffectivePrice()
                : $product->getCurrentPrice();
        }

        return $this;
    }

    /**
     * Get display name (prefer snapshot, fallback to product). Includes variant name if present.
     */
    public function getDisplayName(): string
    {
        $name = $this->productName
            ?? ($this->product instanceof Product ? $this->product->getName() : null)
            ?? 'Neznámý produkt';

        $variantLabel = $this->variantName
            ?? ($this->variant instanceof ProductVariant ? $this->variant->getName() : null);

        if ($variantLabel !== null) {
            return $name . ' — ' . $variantLabel;
        }

        return $name;
    }

    /**
     * Get display code (prefer snapshot, fallback to product)
     */
    public function getDisplayCode(): string
    {
        if ($this->productCode !== null) {
            return $this->productCode;
        }

        if ($this->product instanceof Product) {
            return $this->product->getCode();
        }

        return 'N/A';
    }

    /**
     * Check if product was deleted
     */
    public function isProductDeleted(): bool
    {
        return ! $this->product instanceof Product && $this->productName !== null;
    }

    /**
     * Check if discount was applied
     */
    public function hasDiscount(): bool
    {
        return $this->discountAmount !== null && bccomp($this->discountAmount, '0.00', 2) > 0;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercent(): ?string
    {
        if (! $this->hasDiscount() || $this->originalPrice === null) {
            return null;
        }

        if (bccomp($this->originalPrice, '0.00', 2) === 0) {
            return '0.00';
        }

        return bcmul(
            bcdiv((string) $this->discountAmount, $this->originalPrice, 4),
            '100',
            2,
        );
    }

    /**
     * Check if item was free (100% discount or 0 price)
     */
    public function wasFree(): bool
    {
        return bccomp($this->purchasePrice, '0.00', 2) === 0;
    }

    /**
     * Calculate savings (original - purchase)
     */
    public function getSavings(): string
    {
        if ($this->originalPrice === null) {
            return '0.00';
        }

        return bcsub($this->originalPrice, $this->purchasePrice, 2);
    }
}
