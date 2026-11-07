<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * A product's default variant (the one sharing its code) is the product as the cart and the
 * variant view see it, so it follows the product's state. Other variants carry their own.
 */
class VariantStateMirror
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param int[]|null $productIds products that changed, null for all of them
     */
    public function mirror(?array $productIds = null): void
    {
        if ($productIds === []) {
            return;
        }

        $sql = <<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS produkt
        ON produkt.id_predmetu = product_variant.product_id AND produkt.kod_predmetu = product_variant.code
SET product_variant.state = produkt.stav
SQL;
        $parameters = [];
        $types = [];
        if ($productIds !== null) {
            $sql .= "\nWHERE produkt.id_predmetu IN (:ids)";
            $parameters['ids'] = $productIds;
            $types['ids'] = ArrayParameterType::INTEGER;
        }

        $this->connection->executeStatement($sql, $parameters, $types);
    }
}
