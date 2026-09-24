<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoleMeaning;
use App\Repository\ProductBundleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductBundle - forced bundling of product variants
 *
 * Use case: Weekend accommodation package
 * - Bundle contains: [Dvojlůžko Thu, Dvojlůžko Fri, Dvojlůžko Sat] (variants of one product)
 * - forced = true → users with applicable roles MUST buy all together
 * - Organizers can buy individual variants (not in applicableToRoles)
 *
 * Can also bundle variants from different products (e.g. accommodation + breakfast).
 */
#[ORM\Entity(repositoryClass: ProductBundleRepository::class)]
#[ORM\Table(name: 'product_bundle')]
#[ORM\HasLifecycleCallbacks]
class ProductBundle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Název balíčku nesmí být prázdný')]
    #[Assert\Length(max: 255, maxMessage: 'Název může mít maximálně {{ limit }} znaků')]
    private string $name;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: [
        'default' => false,
        'comment' => 'If true, variants cannot be purchased individually',
    ])]
    private bool $forced = false;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, nullable: false, options: [
        'comment' => 'Array of role meaning values for which bundle is mandatory',
    ])]
    #[Assert\Type('array')]
    private array $applicableToRoles = [];

    /**
     * @var Collection<int, ProductVariant>
     */
    #[ORM\ManyToMany(targetEntity: ProductVariant::class, inversedBy: 'bundles')]
    #[ORM\JoinTable(name: 'product_bundle_variant')]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'variant_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Assert\Count(min: 2, minMessage: 'Balíček musí obsahovat alespoň {{ limit }} varianty')]
    private Collection $variants;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function isForced(): bool
    {
        return $this->forced;
    }

    public function setForced(bool $forced): self
    {
        $this->forced = $forced;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getApplicableToRoles(): array
    {
        return $this->applicableToRoles;
    }

    /**
     * @param string[] $applicableToRoles
     */
    public function setApplicableToRoles(array $applicableToRoles): self
    {
        $this->applicableToRoles = $applicableToRoles;

        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): self
    {
        if (! $this->variants->contains($variant)) {
            $this->variants->add($variant);
        }

        return $this;
    }

    public function removeVariant(ProductVariant $variant): self
    {
        $this->variants->removeElement($variant);

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
     * Check if bundle applies to a specific role meaning
     */
    public function appliesToRole(RoleMeaning $role): bool
    {
        return in_array($role->value, $this->applicableToRoles, true);
    }

    /**
     * Check if bundle contains a specific variant
     */
    public function containsVariant(ProductVariant $variant): bool
    {
        return $this->variants->contains($variant);
    }

    /**
     * @return int[]
     */
    public function getVariantIds(): array
    {
        return $this->variants->map(fn (ProductVariant $v): ?int => $v->getId())->toArray();
    }

    /**
     * Get unique products represented by the bundled variants (for display)
     *
     * @return Product[]
     */
    public function getProducts(): array
    {
        $products = [];
        foreach ($this->variants as $variant) {
            $product = $variant->getProduct();
            $id = $product->getId();
            if ($id !== null && ! isset($products[$id])) {
                $products[$id] = $product;
            }
        }

        return array_values($products);
    }

    /**
     * Get total price of all variants in bundle
     */
    public function getTotalPrice(): string
    {
        $total = '0.00';
        foreach ($this->variants as $variant) {
            $total = bcadd($total, $variant->getEffectivePrice(), 2);
        }

        return $total;
    }

    /**
     * Count variants in bundle
     */
    public function getVariantCount(): int
    {
        return $this->variants->count();
    }

    /**
     * Check if user with given role meanings must purchase this bundle
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function isMandatoryForUser(array $roleMeanings): bool
    {
        if (! $this->forced) {
            return false;
        }

        foreach ($roleMeanings as $meaning) {
            if ($this->appliesToRole($meaning)) {
                return true;
            }
        }

        return false;
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
