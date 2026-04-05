<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Kfc\KfcGridInputDto;
use App\Dto\Kfc\KfcGridOutputDto;
use Doctrine\DBAL\Connection;

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
    ) {
    }

    /**
     * @return KfcGridOutputDto[]
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
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
                    'INSERT INTO obchod_bunky (typ, text, barva, barva_text, cil_id, mrizka_id) VALUES (:typ, :text, :barva, :barvaText, :cilId, :gridId)',
                    [
                        'typ'       => $cellInput->typ,
                        'text'      => $cellInput->text,
                        'barva'     => $cellInput->barva,
                        'barvaText' => $cellInput->barvaText,
                        'cilId'     => $targetId,
                        'gridId'    => $gridId,
                    ],
                );
            }
        }

        // Return fresh grid data
        return $this->gridProvider->provide($operation);
    }
}
