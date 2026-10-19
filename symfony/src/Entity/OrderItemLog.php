<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderItemChangeEnum;
use App\Repository\OrderItemLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One change of a purchase row (shop_nakupy). Rows are written straight through DBAL, so this
 * entity exists to read the history and to keep the table under schema validation.
 *
 * Plain ids instead of relations: the log must outlive the purchase and the customer.
 *
 * @see \App\EventListener\OrderItemAuditListener
 */
#[ORM\Entity(repositoryClass: OrderItemLogRepository::class, readOnly: true)]
#[ORM\Table(name: 'shop_order_item_log')]
#[ORM\Index(name: 'IDX_order_item_log_order_item', columns: ['order_item_id'])]
#[ORM\Index(name: 'IDX_order_item_log_customer_year', columns: ['customer_id', 'year'])]
#[ORM\Index(name: 'IDX_order_item_log_changed_by', columns: ['changed_by_id'])]
#[ORM\Index(name: 'IDX_order_item_log_origin', columns: ['origin'])]
class OrderItemLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'order_item_id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $orderItemId;

    /**
     * The customer the purchase belonged to after this change.
     */
    #[ORM\Column(name: 'customer_id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $customerId;

    #[ORM\Column(name: 'year', type: Types::SMALLINT)]
    private int $year;

    #[ORM\Column(name: 'action', type: Types::STRING, length: 16, enumType: OrderItemChangeEnum::class)]
    private OrderItemChangeEnum $action;

    /**
     * Column of shop_nakupy => [before, after]; a created purchase has no "before", a removed one no "after".
     *
     * @var array<string, array{0: mixed, 1: mixed}>
     */
    #[ORM\Column(name: 'changes', type: Types::JSON)]
    private array $changes = [];

    /**
     * NULL when nobody was signed in: a cron job or a command.
     */
    #[ORM\Column(name: 'changed_by_id', type: Types::BIGINT, nullable: true, options: [
        'unsigned' => true,
    ])]
    private ?int $changedById = null;

    /**
     * Route name, request path or console command the change came from.
     */
    #[ORM\Column(name: 'origin', type: Types::STRING, length: 255)]
    private string $origin;

    #[ORM\Column(name: 'changed_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderItemId(): int
    {
        return $this->orderItemId;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getAction(): OrderItemChangeEnum
    {
        return $this->action;
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getChangedById(): ?int
    {
        return $this->changedById;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
