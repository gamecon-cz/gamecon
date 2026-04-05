<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\Service\CurrentYearProviderInterface;
use Doctrine\DBAL\Connection;

/**
 * Processes KFC point-of-sale purchases.
 * Inserts purchase rows into shop_nakupy (one per unit, matching legacy behavior).
 *
 * @implements ProcessorInterface<KfcSaleInputDto, KfcSaleOutputDto>
 */
readonly class KfcSaleProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
        private CurrentYearProviderInterface $yearProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): KfcSaleOutputDto
    {
        $year = $this->yearProvider->getCurrentYear();
        $soldItems = 0;
        $totalPrice = 0;

        foreach ($data->items as $saleItem) {
            $productId = $saleItem->productId;
            $quantity = $saleItem->quantity;

            // Fetch product price and stock
            $product = $this->connection->fetchAssociative(
                'SELECT cena_aktualni, kusu_vyrobeno, nazev FROM shop_predmety WHERE id_predmetu = :id',
                [
                    'id' => $productId,
                ],
            );

            if ($product === false) {
                throw new \RuntimeException(sprintf('Produkt s ID %d nebyl nalezen.', $productId));
            }

            $price = (int) round((float) $product['cena_aktualni']);

            // Check remaining stock if limited
            if ($product['kusu_vyrobeno'] !== null) {
                $sold = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = :id AND rok = :year',
                    [
                        'id'   => $productId,
                        'year' => $year,
                    ],
                );
                $remaining = (int) $product['kusu_vyrobeno'] - $sold;

                if ($remaining < $quantity) {
                    throw new \RuntimeException(sprintf('Nedostatek kusů produktu "%s". Zbývá: %d, požadováno: %d.', $product['nazev'], $remaining, $quantity));
                }
            }

            // Insert one row per unit (legacy behavior — each piece is a separate purchase record)
            for ($unit = 0; $unit < $quantity; ++$unit) {
                $this->connection->executeStatement(
                    'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
                     VALUES (1, :productId, :year, :price, NOW())',
                    [
                        'productId' => $productId,
                        'year'      => $year,
                        'price'     => $product['cena_aktualni'],
                    ],
                );
            }

            $soldItems += $quantity;
            $totalPrice += $price * $quantity;
        }

        return new KfcSaleOutputDto(
            soldItems: $soldItems,
            totalPrice: (string) $totalPrice,
        );
    }
}
