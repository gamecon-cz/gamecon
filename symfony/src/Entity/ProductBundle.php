<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductBundleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductBundle - forced bundling of products (MUST requirement from CFO)
 *
 * Use case: Weekend accommodation package
 * - Bundle contains: [Thursday, Friday, Saturday accommodation]
 * - forced = true → users with 'ucastnik' role MUST buy all together
 * - Organizers can buy individual days (not in applicableToRoles)
 *
 * Example:
 * - Name: "Víkendový balíček ubytování"
 * - Products: [Ubytování Čt, Ubytování Pá, Ubytování So]
 * - forced: true
 * - applicableToRoles: ['ucastnik']
 */
#[ORM\Entity(repositoryClass: ProductBundleRepository::class)]
#[ORM\Table(name: 'product_bundle')]
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
        'comment' => 'If true, products cannot be purchased individually',
    ])]
    private bool $forced = false;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, nullable: false, options: [
        'comment' => 'Array of role names for which bundle is mandatory (e.g., ["ucastnik"])',
    ])]
    #[Assert\Type('array')]
    private array $applicableToRoles = [];

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'bundles')]
    #[ORM\JoinTable(name: 'product_bundle_items')]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'product_id', referencedColumnName: 'id_predmetu', onDelete: 'CASCADE')]
    #[Assert\Count(min: 2, minMessage: 'Balíček musí obsahovat alespoň {{ limit }} produkty')]
    private Collection $products;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
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
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);

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
     * Check if bundle applies to a specific role
     */
    public function appliesToRole(string $role): bool
    {
        return in_array($role, $this->applicableToRoles, true);
    }

    /**
     * Check if bundle contains a specific product
     */
    public function containsProduct(Product $product): bool
    {
        return $this->products->contains($product);
    }

    /**
     * Get product IDs in this bundle
     *
     * @return int[]
     */
    public function getProductIds(): array
    {
        return $this->products->map(fn (Product $p): ?int => $p->getId())->toArray();
    }

    /**
     * Get total price of all products in bundle
     */
    public function getTotalPrice(): string
    {
        $total = '0.00';
        foreach ($this->products as $product) {
            $total = bcadd($total, $product->getCurrentPrice(), 2);
        }

        return $total;
    }

    /**
     * Count products in bundle
     */
    public function getProductCount(): int
    {
        return $this->products->count();
    }

    /**
     * Check if user with given roles must purchase this bundle
     *
     * @param string[] $userRoles
     */
    public function isMandatoryForUser(array $userRoles): bool
    {
        if (! $this->forced) {
            return false;
        }

        foreach ($userRoles as $userRole) {
            if ($this->appliesToRole($userRole)) {
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
