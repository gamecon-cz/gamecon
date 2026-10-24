<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MealDishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jídlo z jídelníčku — konkrétní jídlo, které si účastník vybere k objednané porci.
 *
 * Porce (Oběd pátek) je varianta produktu a kupuje se v e-shopu; jídlo se k ní jen vybírá
 * a na ceně nic nemění. Jídelníček se zveřejňuje až před GameConem, takže porce se objednává
 * dřív, než je známo, co se bude vařit.
 *
 * Produkty jídel přežívají ročníky, proto jídlo nese vlastní ročník: loňská svíčková nesmí
 * vyskočit u letošního obědu.
 *
 * Nikdy není ApiResource — ven jde jen přes DTO.
 */
#[ORM\Entity(repositoryClass: MealDishRepository::class)]
#[ORM\Table(name: 'meal_dish')]
#[ORM\Index(name: 'IDX_meal_dish_year_variant', columns: ['year', 'variant_id'])]
#[ORM\HasLifecycleCallbacks]
class MealDish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'variant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private int $year;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $name;

    /**
     * Alergeny, příloha, cokoli, co se do názvu nevejde.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: [
        'default' => false,
    ])]
    private bool $vegetarian = false;

    /**
     * Co dostane, kdo si do termínu nevybere. Ve slotu je výchozí právě jedno jídlo — hlídá
     * to MealMenuEditor, ne databáze.
     */
    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN, nullable: false, options: [
        'default' => false,
    ])]
    private bool $default = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, options: [
        'default' => 0,
    ])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(ProductVariant $variant, int $year, string $name)
    {
        $this->variant = $variant;
        $this->year = $year;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ProductVariant
    {
        return $this->variant;
    }

    public function getYear(): int
    {
        return $this->year;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isVegetarian(): bool
    {
        return $this->vegetarian;
    }

    public function setVegetarian(bool $vegetarian): self
    {
        $this->vegetarian = $vegetarian;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
