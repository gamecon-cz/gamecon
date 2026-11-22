<?php

declare(strict_types=1);

namespace Gamecon\Ai;

use Gamecon\Ai\Exceptions\ChybaApiAi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Batches of the Claude API (Message Batches): half the price of single requests, and the admin does not wait
 * for the AI. Plain HTTP rather than the SDK, which would bring thousands of files into the committed vendor/
 * for these four calls. No refusal fallback to another model, the Batches API does not take it, a refused text
 * stays for another try or for the chat.
 *
 * @see https://platform.claude.com/docs/en/build-with-claude/batch-processing
 */
class AnthropicDavkyAi implements DavkyAi
{
    // proofreading has to be careful, and a year of texts costs a few dollars even so
    public const string VYCHOZI_MODEL = 'claude-opus-5-5';

    public const string VERZE_API = '2026-10-21';

    private const string URL_DAVEK = 'https://api.anthropic.com/v1/messages/batches';

    private const string URL_MODELU = 'https://api.anthropic.com/v1/models';

    private const string EFFORT = 'high';

    // one activity: its texts once more and the thinking before them, a batch is not cut by an HTTP timeout
    private const int MAX_TOKENS = 32000;

    // a request of the admin page, the batch itself runs at Anthropic
    private const int TIMEOUT_SEKUND = 30;

    public function __construct(
        private readonly string $klicApi,
        private readonly string $model = self::VYCHOZI_MODEL,
        private readonly ClientInterface $http = new Client([
            'timeout'         => self::TIMEOUT_SEKUND,
            'connect_timeout' => 10,
        ]),
    ) {
    }

    public function poskytovatel(): PoskytovatelAi
    {
        return PoskytovatelAi::ANTHROPIC;
    }

    /**
     * The model's own page: a wrong token ends with 401, a model the API does not know with 404, before any batch
     * waits a day for nothing.
     */
    public function overSpojeni(): void
    {
        try {
            $this->posli('GET', self::URL_MODELU . '/' . rawurlencode($this->model));
        } catch (ChybaApiAi $chybaApiAi) {
            throw $chybaApiAi->kodHttp === 404 ? new ChybaApiAi("Token platí, ale model „{$this->model}“ API nezná.", 404, $chybaApiAi) : $chybaApiAi;
        }
    }

    public function odesli(string $pokyny, array $zpravy): string
    {
        $pozadavky = [];
        foreach ($zpravy as $idPozadavku => $zprava) {
            $pozadavky[] = [
                'custom_id' => $idPozadavku,
                'params'    => [
                    'model'         => $this->model,
                    'max_tokens'    => self::MAX_TOKENS,
                    'output_config' => [
                        'effort' => self::EFFORT,
                    ],
                    'system'   => $pokyny,
                    'messages' => [
                        [
                            'role'    => 'user',
                            'content' => $zprava,
                        ],
                    ],
                ],
            ];
        }

        $davka = $this->json($this->posli('POST', self::URL_DAVEK, [
            'requests' => $pozadavky,
        ]));
        if (! is_string($davka['id'] ?? null) || $davka['id'] === '') {
            throw new ChybaApiAi('API AI nevrátilo ID dávky.', 200);
        }

        return $davka['id'];
    }

    public function dejOdpovedi(string $idDavky): ?array
    {
        $davka = $this->json($this->posli('GET', $this->urlDavky($idDavky)));
        if (($davka['processing_status'] ?? null) !== 'ended') {
            return null;
        }

        // our own URL rather than the batch's results_url, the key goes to the API's address only
        $vysledky = (string) $this->posli('GET', $this->urlDavky($idDavky) . '/results')->getBody();
        $odpovedi = [];
        // JSONL ends a line with \n only, a JSON string has its line ends escaped; \R without /u would also cut
        // the byte 0x85 inside UTF-8 characters such as 📅 or Å
        foreach (explode("\n", $vysledky) as $radek) {
            if (trim($radek) === '') {
                continue;
            }
            $vysledek = $this->dekoduj($radek, 200);
            if (! is_string($vysledek['custom_id'] ?? null) || ! is_array($vysledek['result'] ?? null)) {
                throw new ChybaApiAi('API AI poslalo výsledek dávky v neznámém tvaru.', 200);
            }
            $odpovedi[$vysledek['custom_id']] = $this->odpovedZVysledku($vysledek['result']);
        }
        if ($odpovedi === []) {
            // an ended batch has a result for each request, an empty body is not its answer
            throw new ChybaApiAi('API AI neposlalo výsledky skončené dávky.', 200);
        }

        return $odpovedi;
    }

    public function zrus(string $idDavky): void
    {
        $this->posli('POST', $this->urlDavky($idDavky) . '/cancel');
    }

    /**
     * @param array<string, mixed> $vysledek
     */
    private function odpovedZVysledku(array $vysledek): OdpovedAiNaPozadavek
    {
        return match ($vysledek['type'] ?? null) {
            'succeeded' => is_array($vysledek['message'] ?? null)
                ? $this->odpovedZeZpravy($vysledek['message'])
                : OdpovedAiNaPozadavek::chyba('odpověď AI bez zprávy'),
            'errored' => OdpovedAiNaPozadavek::chyba(
                'chyba API: ' . ($vysledek['error']['error']['message'] ?? 'bez popisu'),
            ),
            'canceled' => OdpovedAiNaPozadavek::chyba('dávka byla zrušena'),
            'expired'  => OdpovedAiNaPozadavek::chyba('AI nestihla odpovědět do 24 hodin'),
            default    => OdpovedAiNaPozadavek::chyba('neznámý výsledek ' . json_encode($vysledek['type'] ?? null)),
        };
    }

    /**
     * @param array<string, mixed> $zprava
     */
    private function odpovedZeZpravy(array $zprava): OdpovedAiNaPozadavek
    {
        if (($zprava['stop_reason'] ?? null) === 'refusal') {
            return OdpovedAiNaPozadavek::chyba('AI korekturu odmítla');
        }
        $text = '';
        // thinking blocks come before the text, only the text is the answer
        foreach (is_array($zprava['content'] ?? null) ? $zprava['content'] : [] as $blok) {
            if (is_array($blok) && ($blok['type'] ?? null) === 'text' && is_string($blok['text'] ?? null)) {
                $text .= $blok['text'];
            }
        }

        // a text cut by max_tokens ends inside its block, the parser warns about it
        return OdpovedAiNaPozadavek::text($text);
    }

    private function urlDavky(string $idDavky): string
    {
        return self::URL_DAVEK . '/' . rawurlencode($idDavky);
    }

    /**
     * @param array<string, mixed>|null $telo
     */
    private function posli(string $metoda, string $url, ?array $telo = null): ResponseInterface
    {
        try {
            $json = $telo !== null
                ? json_encode($telo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;
        } catch (\JsonException $jsonException) {
            throw new ChybaApiAi("Texty pro AI nejdou zapsat jako JSON: {$jsonException->getMessage()}", null, $jsonException);
        }
        $pozadavek = new Request(
            $metoda,
            $url,
            [
                'x-api-key'         => $this->klicApi,
                'anthropic-version' => self::VERZE_API,
                'content-type'      => 'application/json',
            ],
            $json,
        );
        try {
            $odpoved = $this->http->sendRequest($pozadavek);
        } catch (ClientExceptionInterface $clientException) {
            throw new ChybaApiAi("API AI neodpovídá: {$clientException->getMessage()}", null, $clientException);
        }

        if ($odpoved->getStatusCode() >= 300) {
            $chyba = json_decode((string) $odpoved->getBody(), true);
            $popis = is_array($chyba) && is_string($chyba['error']['message'] ?? null)
                ? $chyba['error']['message']
                : '';
            throw new ChybaApiAi(sprintf('API AI vrátilo chybu %d%s%s', $odpoved->getStatusCode(), $popis !== '' ? ": {$popis}" : '', $odpoved->hasHeader('request-id') ? " (request-id {$odpoved->getHeaderLine('request-id')})" : ''), $odpoved->getStatusCode());
        }

        return $odpoved;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $odpoved): array
    {
        return $this->dekoduj((string) $odpoved->getBody(), $odpoved->getStatusCode());
    }

    /**
     * A body that is no JSON object (a page of a proxy, a cut answer) is an error of the API, not of our code.
     *
     * @return array<string, mixed>
     */
    private function dekoduj(string $json, int $kodHttp): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new ChybaApiAi("API AI neposlalo platný JSON: {$jsonException->getMessage()}", $kodHttp, $jsonException);
        }
        if (! is_array($data)) {
            throw new ChybaApiAi('API AI neposlalo JSON objekt.', $kodHttp);
        }

        return $data;
    }
}
