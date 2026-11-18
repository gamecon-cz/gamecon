<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\Exceptions\ChybaApiAi;

/**
 * Batches of requests to an AI through its API: the texts go out at once and the answers come back later,
 * usually within an hour, at the latest within a day. A batch leaves the web request short, whatever the AI takes.
 *
 * @see AnthropicDavkyAi
 */
interface DavkyAi
{
    /**
     * @param array<string, string> $zpravy the message of each request by its ID
     *
     * @return string ID of the batch
     *
     * @throws ChybaApiAi
     */
    public function odesli(string $pokyny, array $zpravy): string;

    /**
     * @return array<string, OdpovedAiNaPozadavek>|null by request ID, null while the batch is running
     *
     * @throws ChybaApiAi
     */
    public function dejOdpovedi(string $idDavky): ?array;

    /**
     * @throws ChybaApiAi
     */
    public function zrus(string $idDavky): void;
}
