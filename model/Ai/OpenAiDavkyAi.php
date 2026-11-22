<?php

declare(strict_types=1);

namespace Gamecon\Ai;

use Gamecon\Ai\Exceptions\ChybaApiAi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Batches of the OpenAI API (Batch API over the Responses API): half the price of single requests, and the admin
 * does not wait for the AI. Unlike Anthropic the requests do not go in the body: they are uploaded as a JSONL file
 * first, and the answers come back as files too, the successful ones in the output file, the failed ones in the
 * error file. Plain HTTP for the same reason as {@see AnthropicDavkyAi}.
 *
 * @see https://developers.openai.com/api/docs/guides/batch
 */
class OpenAiDavkyAi implements DavkyAi
{
    // the flagship for careful work, as Opus is for Claude
    public const string VYCHOZI_MODEL = 'gpt-6.2-astra';

    private const string URL_API = 'https://api.openai.com/v1';

    private const string ENDPOINT = '/v1/responses';

    // the flagship refuses effort "none", "high" matches what Claude gets
    private const string EFFORT = 'high';

    // one activity: its texts once more and the reasoning before them
    private const int MAX_TOKENS = 32000;

    private const int TIMEOUT_SEKUND = 30;

    /**
     * Batch states in which the answers are still to come.
     */
    private const array BEZICI_STAVY = ['validating', 'in_progress', 'finalizing', 'cancelling'];

    /**
     * Batch states with result files: a completed batch has all answers, an expired or cancelled one those
     * that made it, the rest is in the error file.
     */
    private const array SKONCENE_STAVY = ['completed', 'expired', 'cancelled'];

    public function __construct(
        private readonly string $token,
        private readonly string $model = self::VYCHOZI_MODEL,
        private readonly ClientInterface $http = new Client([
            'timeout'         => self::TIMEOUT_SEKUND,
            'connect_timeout' => 10,
        ]),
    ) {
    }

    public function poskytovatel(): PoskytovatelAi
    {
        return PoskytovatelAi::OPENAI;
    }

    /**
     * The model's own page: a wrong token ends with 401, a model the API does not know with 404, before any batch
     * waits a day for nothing.
     */
    public function overSpojeni(): void
    {
        try {
            $this->posli('GET', self::URL_API . '/models/' . rawurlencode($this->model));
        } catch (ChybaApiAi $chybaApiAi) {
            throw $chybaApiAi->kodHttp === 404 ? new ChybaApiAi("Token platí, ale model „{$this->model}“ API nezná.", 404, $chybaApiAi) : $chybaApiAi;
        }
    }

    public function odesli(string $pokyny, array $zpravy): string
    {
        $radky = [];
        foreach ($zpravy as $idPozadavku => $zprava) {
            $radky[] = $this->doJson([
                'custom_id' => $idPozadavku,
                'method'    => 'POST',
                'url'       => self::ENDPOINT,
                'body'      => [
                    'model'        => $this->model,
                    'instructions' => $pokyny,
                    'input'        => $zprava,
                    'reasoning'    => [
                        'effort' => self::EFFORT,
                    ],
                    'max_output_tokens' => self::MAX_TOKENS,
                ],
            ]);
        }

        $multipart = new MultipartStream([
            [
                'name'     => 'purpose',
                'contents' => 'batch',
            ],
            [
                'name'     => 'file',
                'contents' => implode("\n", $radky) . "\n",
                'filename' => 'korektury.jsonl',
                'headers'  => [
                    'Content-Type' => 'application/jsonl',
                ],
            ],
        ]);
        $soubor = $this->json($this->posli(
            'POST',
            self::URL_API . '/files',
            $multipart,
            'multipart/form-data; boundary=' . $multipart->getBoundary(),
        ));
        if (! is_string($soubor['id'] ?? null) || $soubor['id'] === '') {
            throw new ChybaApiAi('API AI nevrátilo ID nahraného souboru s texty.', 200);
        }

        $davka = $this->json($this->posli('POST', self::URL_API . '/batches', $this->doJson([
            'input_file_id'     => $soubor['id'],
            'endpoint'          => self::ENDPOINT,
            'completion_window' => '24h',
        ])));
        if (! is_string($davka['id'] ?? null) || $davka['id'] === '') {
            throw new ChybaApiAi('API AI nevrátilo ID dávky.', 200);
        }

        return $davka['id'];
    }

    public function dejOdpovedi(string $idDavky): ?array
    {
        $davka = $this->json($this->posli('GET', $this->urlDavky($idDavky)));
        $stav = $davka['status'] ?? null;
        if (in_array($stav, self::BEZICI_STAVY, true)) {
            return null;
        }
        if ($stav === 'failed') {
            // the input file did not pass validation, none of its requests ran
            throw ChybaApiAi::davkaSkoncilaBezOdpovedi('API AI dávku odmítlo' . $this->popisChybDavky($davka) . '.');
        }
        if (! in_array($stav, self::SKONCENE_STAVY, true)) {
            throw new ChybaApiAi('API AI poslalo dávku v neznámém stavu ' . json_encode($stav) . '.', 200);
        }

        $odpovedi = [];
        foreach ([$davka['output_file_id'] ?? null, $davka['error_file_id'] ?? null] as $idSouboru) {
            if (! is_string($idSouboru) || $idSouboru === '') {
                continue;
            }
            $obsah = (string) $this->posli('GET', self::URL_API . '/files/' . rawurlencode($idSouboru) . '/content')->getBody();
            // JSONL ends a line with \n only, a JSON string has its line ends escaped; \R without /u would also cut
            // the byte 0x85 inside UTF-8 characters such as 📅 or Å
            foreach (explode("\n", $obsah) as $radek) {
                if (trim($radek) === '') {
                    continue;
                }
                $vysledek = $this->dekoduj($radek, 200);
                if (! is_string($vysledek['custom_id'] ?? null)) {
                    throw new ChybaApiAi('API AI poslalo výsledek dávky v neznámém tvaru.', 200);
                }
                $odpovedi[$vysledek['custom_id']] = $this->odpovedZVysledku($vysledek);
            }
        }
        if ($odpovedi === [] && $stav === 'completed') {
            // a completed batch has a result for each request, an empty one is not its answer
            throw new ChybaApiAi('API AI neposlalo výsledky skončené dávky.', 200);
        }

        return $odpovedi;
    }

    public function zrus(string $idDavky): void
    {
        $this->posli('POST', $this->urlDavky($idDavky) . '/cancel');
    }

    /**
     * @param array<string, mixed> $vysledek a line of the output or the error file
     */
    private function odpovedZVysledku(array $vysledek): OdpovedAiNaPozadavek
    {
        if (is_array($vysledek['error'] ?? null)) {
            return OdpovedAiNaPozadavek::chyba(match ($vysledek['error']['code'] ?? null) {
                'batch_expired'   => 'AI nestihla odpovědět do 24 hodin',
                'batch_cancelled' => 'dávka byla zrušena',
                default           => 'chyba API: ' . (is_string($vysledek['error']['message'] ?? null)
                    ? $vysledek['error']['message']
                    : 'bez popisu'),
            });
        }
        $odpoved = $vysledek['response'] ?? null;
        if (! is_array($odpoved) || ! is_array($odpoved['body'] ?? null)) {
            return OdpovedAiNaPozadavek::chyba('odpověď AI bez obsahu');
        }
        if (($odpoved['status_code'] ?? null) !== 200) {
            $popis = $odpoved['body']['error']['message'] ?? null;

            return OdpovedAiNaPozadavek::chyba(sprintf(
                'chyba API %s: %s',
                json_encode($odpoved['status_code'] ?? null),
                is_string($popis)
                    ? $popis
                    : 'bez popisu',
            ));
        }

        return $this->odpovedZeZpravy($odpoved['body']);
    }

    /**
     * @param array<string, mixed> $zprava the Response object, as the endpoint returns it on its own
     */
    private function odpovedZeZpravy(array $zprava): OdpovedAiNaPozadavek
    {
        $text = '';
        $odmitnuti = false;
        // reasoning items come before the message, only the message's text is the answer
        foreach (is_array($zprava['output'] ?? null) ? $zprava['output'] : [] as $polozka) {
            if (! is_array($polozka) || ($polozka['type'] ?? null) !== 'message' || ! is_array($polozka['content'] ?? null)) {
                continue;
            }
            foreach ($polozka['content'] as $obsah) {
                if (! is_array($obsah)) {
                    continue;
                }
                if (($obsah['type'] ?? null) === 'output_text' && is_string($obsah['text'] ?? null)) {
                    $text .= $obsah['text'];
                } elseif (($obsah['type'] ?? null) === 'refusal') {
                    $odmitnuti = true;
                }
            }
        }
        if ($text === '' && $odmitnuti) {
            return OdpovedAiNaPozadavek::chyba('AI korekturu odmítla');
        }
        $duvodNedokonceni = $zprava['incomplete_details']['reason'] ?? null;
        if ($text === '' && is_string($duvodNedokonceni)) {
            return OdpovedAiNaPozadavek::chyba("AI odpověď nedokončila ({$duvodNedokonceni})");
        }

        // a text cut by max_output_tokens ends inside its block, the parser warns about it
        return OdpovedAiNaPozadavek::text($text);
    }

    /**
     * @param array<string, mixed> $davka
     */
    private function popisChybDavky(array $davka): string
    {
        $popisy = [];
        foreach (is_array($davka['errors']['data'] ?? null) ? $davka['errors']['data'] : [] as $chyba) {
            if (is_array($chyba) && is_string($chyba['message'] ?? null)) {
                $popisy[] = $chyba['message'] . (is_int($chyba['line'] ?? null)
                    ? " (řádek {$chyba['line']})"
                    : '');
            }
        }

        return $popisy !== []
            ? ': ' . implode('; ', $popisy)
            : '';
    }

    private function urlDavky(string $idDavky): string
    {
        return self::URL_API . '/batches/' . rawurlencode($idDavky);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function doJson(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $jsonException) {
            throw new ChybaApiAi("Texty pro AI nejdou zapsat jako JSON: {$jsonException->getMessage()}", null, $jsonException);
        }
    }

    private function posli(
        string $metoda,
        string $url,
        string|StreamInterface|null $telo = null,
        string $typObsahu = 'application/json',
    ): ResponseInterface {
        $hlavicky = [
            'Authorization' => 'Bearer ' . $this->token,
        ];
        if ($telo !== null) {
            $hlavicky['Content-Type'] = $typObsahu;
        }
        try {
            $odpoved = $this->http->sendRequest(new Request($metoda, $url, $hlavicky, $telo));
        } catch (ClientExceptionInterface $clientException) {
            throw new ChybaApiAi("API AI neodpovídá: {$clientException->getMessage()}", null, $clientException);
        }

        if ($odpoved->getStatusCode() >= 300) {
            $chyba = json_decode((string) $odpoved->getBody(), true);
            $popis = is_array($chyba) && is_string($chyba['error']['message'] ?? null)
                ? $chyba['error']['message']
                : '';
            throw new ChybaApiAi(sprintf('API AI vrátilo chybu %d%s%s', $odpoved->getStatusCode(), $popis !== '' ? ": {$popis}" : '', $odpoved->hasHeader('x-request-id') ? " (request-id {$odpoved->getHeaderLine('x-request-id')})" : ''), $odpoved->getStatusCode());
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
