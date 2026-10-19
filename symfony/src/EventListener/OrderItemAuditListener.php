<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\OrderItem;
use App\Enum\OrderItemChangeEnum;
use App\Service\AuditOriginProvider;
use App\Structure\Sql\OrderItemLogSqlStructure;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Clock\ClockInterface;

/**
 * Writes every change of a purchase row to shop_order_item_log, inside the transaction of the
 * change itself, so a failing insert fails the purchase too. It sees only writes through the
 * entity manager; a test fails on raw SQL that names shop_nakupy.
 *
 * @see \App\Tests\Audit\PurchaseWritesGoThroughDoctrineTest
 */
#[AsEntityListener(event: Events::postPersist, entity: OrderItem::class)]
#[AsEntityListener(event: Events::postUpdate, entity: OrderItem::class)]
#[AsEntityListener(event: Events::preRemove, entity: OrderItem::class)]
#[AsEntityListener(event: Events::postRemove, entity: OrderItem::class)]
class OrderItemAuditListener
{
    /**
     * What a created or removed purchase is recorded with. An update records whatever changed;
     * the snapshot texts are left out here only so they are not repeated in every row.
     * A test fails for a column that is neither here nor in the list of those left out.
     *
     * @see \App\Tests\Audit\OrderItemAuditListenerTest::LEFT_OUT_ON_PURPOSE
     */
    private const array SNAPSHOT_FIELDS = [
        'customer',
        'orderer',
        'order',
        'variant',
        'bundle',
        'year',
        'purchasePrice',
        'originalPrice',
        'discountAmount',
        'discountReason',
        'discountSnapshot',
        'overrideLog',
    ];

    /**
     * Taken in preRemove: by postRemove Doctrine has cleared the generated id of every entity
     * removed in the flush, so the purchase's own and those of its order or customer are gone.
     *
     * @var array<int, array{orderItemId: int, customerId: int, year: int, changes: array<string, array{0: mixed, 1: mixed}>}> by object id
     */
    private array $removed = [];

    public function __construct(
        private readonly AuditOriginProvider $origin,
        private readonly ClockInterface $clock,
    ) {
    }

    public function postPersist(OrderItem $item, PostPersistEventArgs $args): void
    {
        $manager = $args->getObjectManager();
        $changes = [];
        foreach ($this->currentValues($item, $manager) as $column => $value) {
            $changes[$column] = [null, $value];
        }

        $this->record(OrderItemChangeEnum::CREATED, $this->idOf($item), $this->customerIdOf($item), $item->getYear(), $changes, $manager);
    }

    public function postUpdate(OrderItem $item, PostUpdateEventArgs $args): void
    {
        $manager = $args->getObjectManager();
        /** @var array<string, array{0: mixed, 1: mixed}> $changeSet */
        $changeSet = $manager->getUnitOfWork()->getEntityChangeSet($item);
        $changes = [];
        foreach ($changeSet as $field => [$before, $after]) {
            $changes[$this->columnOf($field, $manager)] = [
                $this->loggable($before, $manager),
                $this->loggable($after, $manager),
            ];
        }

        if ($changes !== []) {
            $this->record(OrderItemChangeEnum::UPDATED, $this->idOf($item), $this->customerIdOf($item), $item->getYear(), $changes, $manager);
        }
    }

    public function preRemove(OrderItem $item, PreRemoveEventArgs $args): void
    {
        // Removing a purchase that was never inserted deletes nothing, so there is nothing to log.
        if ($item->getId() === null) {
            return;
        }

        $manager = $args->getObjectManager();
        $changes = [];
        foreach ($this->currentValues($item, $manager) as $column => $value) {
            $changes[$column] = [$value, null];
        }
        $this->removed[spl_object_id($item)] = [
            'orderItemId' => $this->idOf($item),
            'customerId'  => $this->customerIdOf($item),
            'year'        => $item->getYear(),
            'changes'     => $changes,
        ];
    }

    public function postRemove(OrderItem $item, PostRemoveEventArgs $args): void
    {
        $removed = $this->removed[spl_object_id($item)] ?? throw new \LogicException('A removed purchase was never seen by preRemove.');
        unset($this->removed[spl_object_id($item)]);

        $this->record(OrderItemChangeEnum::REMOVED, $removed['orderItemId'], $removed['customerId'], $removed['year'], $removed['changes'], $args->getObjectManager());
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changes
     */
    private function record(
        OrderItemChangeEnum $action,
        int $orderItemId,
        int $customerId,
        int $year,
        array $changes,
        EntityManagerInterface $manager,
    ): void {
        $manager->getConnection()->insert(OrderItemLogSqlStructure::_table, [
            OrderItemLogSqlStructure::order_item_id => $orderItemId,
            OrderItemLogSqlStructure::customer_id   => $customerId,
            OrderItemLogSqlStructure::year          => $year,
            OrderItemLogSqlStructure::action        => $action,
            OrderItemLogSqlStructure::changes       => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            OrderItemLogSqlStructure::changed_by_id => $this->origin->getActorId(),
            OrderItemLogSqlStructure::origin        => $this->origin->getOrigin(),
            OrderItemLogSqlStructure::changed_at    => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed> column => value, without the columns that are empty
     */
    private function currentValues(OrderItem $item, EntityManagerInterface $manager): array
    {
        $metadata = $manager->getClassMetadata(OrderItem::class);
        $values = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $value = $this->loggable($metadata->getFieldValue($item, $field), $manager);
            if ($value !== null) {
                $values[$this->columnOf($field, $manager)] = $value;
            }
        }

        return $values;
    }

    private function columnOf(string $field, EntityManagerInterface $manager): string
    {
        $metadata = $manager->getClassMetadata(OrderItem::class);

        return $metadata->isSingleValuedAssociation($field)
            ? $metadata->getSingleAssociationJoinColumnName($field)
            : $metadata->getColumnName($field);
    }

    /**
     * A related entity is logged as its id: the row has to stay readable after the entity is gone.
     */
    private function loggable(mixed $value, EntityManagerInterface $manager): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_object($value)) {
            // The identity map keeps generated ids as the strings the driver returned.
            $identifier = $manager->getUnitOfWork()->getSingleIdentifierValue($value);

            return is_numeric($identifier) ? (int) $identifier : $identifier;
        }

        return $value;
    }

    private function idOf(OrderItem $item): int
    {
        return $item->getId() ?? throw new \LogicException('The purchase has no id yet.');
    }

    private function customerIdOf(OrderItem $item): int
    {
        $customer = $item->getCustomer() ?? throw new \LogicException('A purchase always has a customer.');

        return $customer->getId() ?? throw new \LogicException('The customer of the purchase has no id yet.');
    }
}
