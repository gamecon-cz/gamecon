<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductDiscountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductDiscount - role-based discounts (COULD requirement from CFO)
 *
 * Replaces hardcoded discounts from Pravo::* and Cenik class
 *
 * Examples:
 * - Organizers get dice for free (100% discount)
 * - Narrators get 50% off merchandise
 * - First t-shirt free for participants
 *
 * Use cases:
 * - Product: Kostka, Role: organizator, Discount: 100%, MaxQuantity: 1
 * - Product: Tričko M, Role: ucastnik, Discount: 100%, MaxQuantity: 1
 * - Product: Merch, Role: vypravec, Discount: 50%, MaxQuantity: null (unlimited)
 */
#[ORM\Entity(repositoryClass: ProductDiscountRepository::class)]
#[ORM\Table(name: 'product_discount')]
#[ORM\UniqueConstraint(name: 'UNIQ_product_role', columns: ['product_id', 'role'])]
class ProductDiscount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'discounts')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id_predmetu', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Produkt musí být vyplněn')]
    private ?Product $product = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false, options: [
        'comment' => 'Role name: organizator, vypravec, ucastnik',
    ])]
    #[Assert\NotBlank(message: 'Role nesmí být prázdná')]
    #[Assert\Choice(
        choices: ['organizator', 'vypravec', 'ucastnik', 'host'],
        message: 'Neplatná role'
    )]
    private string $role;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: false, options: [
        'comment' => 'Discount percent 0-100 (100 = free)',
    ])]
    #[Assert\NotBlank(message: 'Procento slevy musí být vyplněno')]
    #[Assert\Range(notInRangeMessage: 'Sleva musí být mezi {{ min }} a {{ max }} procenty', min: 0, max: 100)]
    private string $discountPercent;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: [
        'comment' => 'Max quantity with discount (null = unlimited)',
    ])]
    #[Assert\PositiveOrZero(message: 'Maximální množství musí být kladné číslo nebo nula')]
    private ?int $maxQuantity = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ==================== Getters and Setters ====================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getDiscountPercent(): string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(string $discountPercent): self
    {
        $this->discountPercent = $discountPercent;

        return $this;
    }

    public function getMaxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(?int $maxQuantity): self
    {
        $this->maxQuantity = $maxQuantity;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    // ==================== Helper Methods ====================

    /**
     * Check if discount is free (100%)
     */
    public function isFree(): bool
    {
        return bccomp($this->discountPercent, '100.00', 2) === 0;
    }

    /**
     * Check if discount has quantity limit
     */
    public function hasQuantityLimit(): bool
    {
        return $this->maxQuantity !== null;
    }

    /**
     * Calculate discount amount for given price
     */
    public function calculateDiscountAmount(string $price): string
    {
        return bcmul(
            $price,
            bcdiv($this->discountPercent, '100', 4),
            2
        );
    }

    /**
     * Calculate final price after discount
     */
    public function calculateFinalPrice(string $price): string
    {
        $discountAmount = $this->calculateDiscountAmount($price);

        return bcsub($price, $discountAmount, 2);
    }

    /**
     * Get human-readable discount description
     */
    public function getDescription(): string
    {
        $roleName = match ($this->role) {
            'organizator' => 'organizátoři',
            'vypravec'    => 'vypravěči',
            'ucastnik'    => 'účastníci',
            'host'        => 'hosté',
            default       => $this->role,
        };

        $discount = rtrim(rtrim($this->discountPercent, '0'), '.');

        $desc = sprintf('%s: %s%% sleva', $roleName, $discount);

        if ($this->hasQuantityLimit()) {
            $desc .= sprintf(' (max %s× na osobu)', $this->maxQuantity);
        }

        return $desc;
    }

    /**
     * Mark as updated (for timestampable behavior)
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
