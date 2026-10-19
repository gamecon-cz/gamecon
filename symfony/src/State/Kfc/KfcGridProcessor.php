<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Kfc\KfcGridInputDto;
use App\Dto\Kfc\KfcGridOutputDto;
use App\Exception\InvalidRequestException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Saves KFC grid configuration. Handles negative IDs for newly created grids.
 *
 * @implements ProcessorInterface<KfcGridInputDto, KfcGridOutputDto[]>
 */
readonly class KfcGridProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
        private KfcGridProvider $gridProvider,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return KfcGridOutputDto[]
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Before anything is written: the grids are saved statement by statement, so a refusal
        // halfway would leave some of them saved.
        $this->refuseVariantsOfOtherProducts($data);

        $idMapping = []; // maps negative (temp) IDs → real DB IDs

        foreach ($data->grids as $gridInput) {
            $gridId = $gridInput->id;

            if ($gridId === null || $gridId < 0) {
                // New grid — insert
                $this->connection->executeStatement(
                    'INSERT INTO obchod_mrizky (text) VALUES (:text)',
                    [
                        'text' => $gridInput->text,
                    ],
                );
                $realId = (int) $this->connection->lastInsertId();
                if ($gridId !== null) {
                    $idMapping[$gridId] = $realId;
                }
                $gridId = $realId;
            } else {
                // Existing grid — update
                $this->connection->executeStatement(
                    'UPDATE obchod_mrizky SET text = :text WHERE id = :id',
                    [
                        'text' => $gridInput->text,
                        'id'   => $gridId,
                    ],
                );
            }

            // Delete existing cells for this grid and re-insert
            $this->connection->executeStatement(
                'DELETE FROM obchod_bunky WHERE mrizka_id = :gridId',
                [
                    'gridId' => $gridId,
                ],
            );

            foreach ($gridInput->bunky as $cellInput) {
                // Resolve target ID — if it references a newly created grid (negative ID), map it
                $targetId = $cellInput->cilId;
                if ($targetId !== null && $targetId < 0 && isset($idMapping[$targetId])) {
                    $targetId = $idMapping[$targetId];
                }

                $this->connection->executeStatement(
                    'INSERT INTO obchod_bunky (typ, text, barva, barva_text, cil_id, variant_id, mrizka_id) VALUES (:typ, :text, :barva, :barvaText, :cilId, :variantId, :gridId)',
                    [
                        'typ'       => $cellInput->typ,
                        'text'      => $cellInput->text,
                        'barva'     => $cellInput->barva,
                        'barvaText' => $cellInput->barvaText,
                        'cilId'     => $targetId,
                        'variantId' => $cellInput->variantId,
                        'gridId'    => $gridId,
                    ],
                );
            }
        }

        // Return fresh grid data
        return $this->gridProvider->provide($operation);
    }

    private function refuseVariantsOfOtherProducts(KfcGridInputDto $data): void
    {
        $productOfVariant = [];
        foreach ($data->grids as $gridInput) {
            foreach ($gridInput->bunky as $cellInput) {
                if ($cellInput->variantId !== null) {
                    $productOfVariant[$cellInput->variantId] = null;
                }
            }
        }
        if ($productOfVariant === []) {
            return;
        }
        $productOfVariant = array_map('intval', $this->connection->fetchAllKeyValue(
            'SELECT id, product_id FROM product_variant WHERE id IN (:ids)',
            [
                'ids' => array_keys($productOfVariant),
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ],
        ));

        foreach ($data->grids as $gridInput) {
            foreach ($gridInput->bunky as $cellInput) {
                if ($cellInput->variantId !== null
                    && (! isset($productOfVariant[$cellInput->variantId]) || $productOfVariant[$cellInput->variantId] !== $cellInput->cilId)
                ) {
                    throw new InvalidRequestException($this->translator->trans('kfc.variant_of_other_product', [
                        '%variant%' => $cellInput->variantId, '%product%' => $cellInput->cilId,
                    ], 'errors'));
                }
            }
        }
    }
}
