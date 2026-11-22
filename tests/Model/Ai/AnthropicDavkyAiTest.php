<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ai;

use Gamecon\Ai\AnthropicDavkyAi;
use Gamecon\Ai\Exceptions\ChybaApiAi;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An HTTP client of the test instead of the API: what goes out, and how the answers are read.
 */
class AnthropicDavkyAiTest extends TestCase
{
    /**
     * @var array<RequestInterface>
     */
    private array $pozadavky = [];

    /**
     * @var array<ResponseInterface|\Throwable> what the client answers each request with, or throws
     */
    private array $odpovedi = [];

    /**
     * @test
     */
    public function davkaMaProKazdouZpravuPozadavekSPokynyJakoSystemovymPromptem(): void
    {
        $this->odpovedi[] = $this->json($this->davka('in_progress'));

        $idDavky = $this->davkyAi()->odesli('Pokyny pro korektora', [
            'korektura-1' => '<gc-aktivita id="11"></gc-aktivita>',
            'korektura-2' => '<gc-aktivita id="12"></gc-aktivita>',
        ]);

        self::assertSame('msgbatch_test', $idDavky);
        self::assertCount(1, $this->pozadavky);
        self::assertSame('POST', $this->pozadavky[0]->getMethod());
        self::assertSame('https://api.anthropic.com/v1/messages/batches', (string) $this->pozadavky[0]->getUri());
        self::assertSame('klic-testu', $this->pozadavky[0]->getHeaderLine('x-api-key'));
        self::assertSame(AnthropicDavkyAi::VERZE_API, $this->pozadavky[0]->getHeaderLine('anthropic-version'));
        self::assertSame('application/json', $this->pozadavky[0]->getHeaderLine('content-type'));
        $telo = json_decode((string) $this->pozadavky[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['korektura-1', 'korektura-2'], array_column($telo['requests'], 'custom_id'));
        $parametry = $telo['requests'][1]['params'];
        self::assertSame(AnthropicDavkyAi::VYCHOZI_MODEL, $parametry['model']);
        self::assertSame('Pokyny pro korektora', $parametry['system']);
        self::assertSame([[
            'role'    => 'user',
            'content' => '<gc-aktivita id="12"></gc-aktivita>',
        ]], $parametry['messages']);
        self::assertSame('high', $parametry['output_config']['effort']);
        self::assertGreaterThan(0, $parametry['max_tokens']);
    }

    /**
     * @test
     */
    public function bezicDavkaNemaOdpovedi(): void
    {
        $this->odpovedi[] = $this->json($this->davka('in_progress'));

        self::assertNull($this->davkyAi()->dejOdpovedi('msgbatch_test'));
        self::assertCount(1, $this->pozadavky, 'Na výsledky běžící dávky se neptá');
    }

    /**
     * @test
     */
    public function skoncenaDavkaDaTextNeboDuvodProcNeni(): void
    {
        $this->odpovedi[] = $this->json($this->davka('ended'));
        $this->odpovedi[] = new Response(200, [
            'Content-Type' => 'application/x-jsonl',
        ], implode("\n", array_map(
            static fn (array $radek) => json_encode($radek, JSON_THROW_ON_ERROR),
            [
                $this->vysledek('korektura-1', $this->zprava('end_turn', ['<gc-aktivita id="11">', '</gc-aktivita>'])),
                $this->vysledek('korektura-2', $this->zprava('refusal', [])),
                [
                    'custom_id' => 'korektura-3',
                    'result'    => [
                        'type'  => 'errored',
                        'error' => [
                            'type'  => 'error',
                            'error' => [
                                'type'    => 'overloaded_error',
                                'message' => 'Overloaded',
                            ],
                            'request_id' => null,
                        ],
                    ],
                ],
                [
                    'custom_id' => 'korektura-4',
                    'result'    => [
                        'type' => 'expired',
                    ],
                ],
            ],
        )) . "\n");

        $odpovedi = $this->davkyAi()->dejOdpovedi('msgbatch_test');

        self::assertNotNull($odpovedi);
        self::assertSame('<gc-aktivita id="11"></gc-aktivita>', $odpovedi['korektura-1']->text, 'Textové bloky za sebou, bez přemýšlení');
        self::assertNull($odpovedi['korektura-2']->text);
        self::assertSame('AI korekturu odmítla', $odpovedi['korektura-2']->chyba);
        self::assertStringContainsString('Overloaded', (string) $odpovedi['korektura-3']->chyba);
        self::assertNotNull($odpovedi['korektura-4']->chyba);
        self::assertSame('https://api.anthropic.com/v1/messages/batches/msgbatch_test/results', (string) $this->pozadavky[1]->getUri());
        self::assertSame('klic-testu', $this->pozadavky[1]->getHeaderLine('x-api-key'));
    }

    /**
     * JSONL is split at line ends only: 📅 ends with the byte 0x85, which `\R` without `/u` takes for a line end.
     *
     * @test
     */
    public function vysledekSeZnakyObsahujicimiBajt0x85SeNerozdeli(): void
    {
        $text = '<gc-aktivita id="11"><gc-popis>📅 Termín, 😅, 🍅 a Ålandy</gc-popis></gc-aktivita>';
        $this->odpovedi[] = $this->json($this->davka('ended'));
        $this->odpovedi[] = new Response(200, [], json_encode(
            $this->vysledek('korektura-1', $this->zprava('end_turn', [$text])),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ) . "\n");

        self::assertSame($text, $this->davkyAi()->dejOdpovedi('msgbatch_test')['korektura-1']->text ?? null);
    }

    /**
     * @test
     */
    public function odpovedApiVNeznamemTvaruJeChybaApi(): void
    {
        $pripady = [
            'stránka proxy místo JSON' => [
                new Response(200, [
                    'Content-Type' => 'text/html',
                ], '<html>Údržba</html>'),
            ],
            'výsledek bez result' => [
                $this->json($this->davka('ended')),
                new Response(200, [], '{"custom_id":"korektura-1"}' . "\n"),
            ],
            'skončená dávka bez výsledků' => [
                $this->json($this->davka('ended')),
                new Response(200, [], ''),
            ],
        ];
        foreach ($pripady as $pripad => $odpovedi) {
            $this->odpovedi = $odpovedi;
            try {
                $this->davkyAi()->dejOdpovedi('msgbatch_test');
                self::fail("{$pripad}: čekána ChybaApiAi");
            } catch (ChybaApiAi $chybaApiAi) {
                self::assertFalse($chybaApiAi->jeDavkaZtracena(), $pripad);
                self::assertFalse($chybaApiAi->jeChybaCelehoApi(), "{$pripad}: chyba té dávky, ne celého API");
            }
        }

        $this->odpovedi = [
            $this->json([
                'type' => 'message_batch',
            ]),
        ];
        $this->expectException(ChybaApiAi::class);
        $this->davkyAi()->odesli('Pokyny', [
            'korektura-1' => 'text',
        ]);
    }

    /**
     * @test
     */
    public function chybaRikaJestliJdeODavkuNeboOCeleApi(): void
    {
        $this->odpovedi = [
            new Response(404, [], '{"type":"error","error":{"type":"not_found_error","message":"batch not found"}}'),
            new Response(529, [], '{"type":"error","error":{"type":"overloaded_error","message":"Overloaded"}}'),
            new ConnectException('Connection refused', new Request('GET', 'https://api.anthropic.com')),
        ];
        $chyby = [];
        for ($pokus = 1; $pokus <= 3; ++$pokus) {
            try {
                $this->davkyAi()->dejOdpovedi('msgbatch_test');
            } catch (ChybaApiAi $chybaApiAi) {
                $chyby[] = $chybaApiAi;
            }
        }

        self::assertCount(3, $chyby);
        self::assertTrue($chyby[0]->jeDavkaZtracena());
        self::assertFalse($chyby[0]->jeChybaCelehoApi());
        self::assertTrue($chyby[1]->jeChybaCelehoApi());
        self::assertTrue($chyby[2]->jeChybaCelehoApi());
        self::assertStringContainsString('API AI neodpovídá', $chyby[2]->getMessage());
    }

    /**
     * @test
     */
    public function chybuApiPoznaKorektorZeZpravy(): void
    {
        $this->odpovedi[] = new Response(401, [
            'Content-Type' => 'application/json',
            'request-id'   => 'req_test',
        ], json_encode([
            'type'  => 'error',
            'error' => [
                'type'    => 'authentication_error',
                'message' => 'invalid x-api-key',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(ChybaApiAi::class);
        $this->expectExceptionMessage('API AI vrátilo chybu 401: invalid x-api-key (request-id req_test)');
        $this->davkyAi()->odesli('Pokyny', [
            'korektura-1' => 'text',
        ]);
    }

    /**
     * @test
     */
    public function zruseniJdeNaCancelDavky(): void
    {
        $this->odpovedi[] = $this->json($this->davka('canceling'));

        $this->davkyAi()->zrus('msgbatch_test');

        self::assertSame('POST', $this->pozadavky[0]->getMethod());
        self::assertSame('https://api.anthropic.com/v1/messages/batches/msgbatch_test/cancel', (string) $this->pozadavky[0]->getUri());
    }

    /**
     * @test
     */
    public function overeniSpojeniSePtaNaZvolenyModel(): void
    {
        $this->odpovedi[] = $this->json([
            'type' => 'model',
            'id'   => AnthropicDavkyAi::VYCHOZI_MODEL,
        ]);
        $this->odpovedi[] = new Response(404, [], '{"type":"error","error":{"type":"not_found_error","message":"model: claude-preklep"}}');

        $this->davkyAi()->overSpojeni();

        self::assertSame('GET', $this->pozadavky[0]->getMethod());
        self::assertSame('https://api.anthropic.com/v1/models/' . AnthropicDavkyAi::VYCHOZI_MODEL, (string) $this->pozadavky[0]->getUri());
        self::assertSame('klic-testu', $this->pozadavky[0]->getHeaderLine('x-api-key'));

        $this->expectException(ChybaApiAi::class);
        $this->expectExceptionMessage('Token platí, ale model „claude-preklep“ API nezná.');
        $this->davkyAi('claude-preklep')->overSpojeni();
    }

    private function davkyAi(string $model = AnthropicDavkyAi::VYCHOZI_MODEL): AnthropicDavkyAi
    {
        $transporter = new class($this->pozadavky, $this->odpovedi) implements ClientInterface {
            /**
             * @param array<RequestInterface>             $pozadavky
             * @param array<ResponseInterface|\Throwable> $odpovedi
             */
            public function __construct(
                private array &$pozadavky,
                private array &$odpovedi,
            ) {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->pozadavky[] = $request;
                $odpoved = array_shift($this->odpovedi)
                    ?? throw new \LogicException('Test nečekal další požadavek na ' . $request->getUri());
                if ($odpoved instanceof \Throwable) {
                    throw $odpoved;
                }

                return $odpoved;
            }
        };

        return new AnthropicDavkyAi('klic-testu', $model, $transporter);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): ResponseInterface
    {
        return new Response(200, [
            'Content-Type' => 'application/json',
        ], json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function davka(string $stav): array
    {
        return [
            'id'                => 'msgbatch_test',
            'type'              => 'message_batch',
            'processing_status' => $stav,
            'request_counts'    => [
                'processing' => 0,
                'succeeded'  => 0,
                'errored'    => 0,
                'canceled'   => 0,
                'expired'    => 0,
            ],
            'created_at' => '2026-11-18T12:00:00Z',
            'expires_at' => '2026-11-19T12:00:00Z',
            'ended_at'   => $stav === 'ended'
                ? '2026-11-18T12:30:00Z'
                : null,
            'archived_at'         => null,
            'cancel_initiated_at' => null,
            'results_url'         => $stav === 'ended'
                ? 'https://api.anthropic.com/v1/messages/batches/msgbatch_test/results'
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $zprava
     *
     * @return array<string, mixed>
     */
    private function vysledek(string $idPozadavku, array $zprava): array
    {
        return [
            'custom_id' => $idPozadavku,
            'result'    => [
                'type'    => 'succeeded',
                'message' => $zprava,
            ],
        ];
    }

    /**
     * @param array<string> $texty
     *
     * @return array<string, mixed>
     */
    private function zprava(string $duvodKonce, array $texty): array
    {
        return [
            'id'      => 'msg_test',
            'type'    => 'message',
            'role'    => 'assistant',
            'model'   => AnthropicDavkyAi::VYCHOZI_MODEL,
            'content' => [
                [
                    'type'      => 'thinking',
                    'thinking'  => '',
                    'signature' => 'podpis',
                ],
                ...array_map(static fn (string $text) => [
                    'type'      => 'text',
                    'text'      => $text,
                    'citations' => null,
                ], $texty),
            ],
            'stop_reason'   => $duvodKonce,
            'stop_sequence' => null,
            'stop_details'  => $duvodKonce === 'refusal'
                ? [
                    'type'        => 'refusal',
                    'category'    => null,
                    'explanation' => null,
                ]
                : null,
            'container' => null,
            'usage'     => [
                'input_tokens'                => 100,
                'output_tokens'               => 50,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens'     => 0,
                'server_tool_use'             => null,
                'service_tier'                => 'batch',
            ],
        ];
    }
}
