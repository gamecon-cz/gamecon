<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProductStateEnum;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Admins and the import still change a night's or size's offer through its own legacy catalog row,
 * while the cart and the variant view read the variant; this keeps the variant following that row.
 * Goes away with those rows.
 */
class VariantStateMirror
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param int[]|null $productIds catalog rows that changed, null for all of them
     */
    public function mirror(?array $productIds = null): void
    {
        if ($productIds === []) {
            return;
        }

        // A row archived together with its product is a past year, not a night taken off the offer.
        $sql = <<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
    INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
SET product_variant.state = IF(
        vlastni_radek.archived_at IS NOT NULL AND produkt.archived_at IS NULL,
        :retired,
        vlastni_radek.stav
    )
SQL;
        $parameters = [
            'retired' => ProductStateEnum::RETIRED->value,
        ];
        $types = [];
        if ($productIds !== null) {
            $sql .= "\nWHERE vlastni_radek.id_predmetu IN (:ids) OR produkt.id_predmetu IN (:ids)";
            $parameters['ids'] = $productIds;
            $types['ids'] = ArrayParameterType::INTEGER;
        }

        $this->connection->executeStatement($sql, $parameters, $types);
    }
}
