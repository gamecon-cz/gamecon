<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ai;

use Gamecon\Ai\Exceptions\ChybaApiAi;
use Gamecon\Ai\OpenAiDavkyAi;
use Gamecon\Ai\PoskytovatelAi;
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
class OpenAiDavkyAiTest extends TestCase
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
    public function pozadavkyJdouJakoNahranyJsonlSPokynyJakoInstrukcemi(): void
    {
        $this->odpovedi[] = $this->json([
            'id'      => 'file-test',
            'object'  => 'file',
            'purpose' => 'batch',
        ]);
        $this->odpovedi[] = $this->json($this->davka('validating'));

        $idDavky = $this->davkyAi('jiny-model')->odesli('Pokyny pro korektora', [
            'korektura-1' => "<gc-aktivita id=\"11\">\n📅 Termín</gc-aktivita>",
            'korektura-2' => '<gc-aktivita id="12"></gc-aktivita>',
        ]);

        self::assertSame('batch_test', $idDavky);
        self::assertCount(2, $this->pozadavky);

        $nahrani = $this->pozadavky[0];
        self::assertSame('POST', $nahrani->getMethod());
        self::assertSame('https://api.openai.com/v1/files', (string) $nahrani->getUri());
        self::assertSame('Bearer token-testu', $nahrani->getHeaderLine('Authorization'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $nahrani->getHeaderLine('Content-Type'));
        $telo = (string) $nahrani->getBody();
        self::assertMatchesRegularExpression('~name="purpose"(\r\n[^\r\n]+)*\r\n\r\nbatch\r\n~', $telo);
        self::assertStringContainsString('filename="korektury.jsonl"', $telo);
        $radky = array_map(
            static fn (string $radek) => json_decode($radek, true, flags: JSON_THROW_ON_ERROR),
            array_values(array_filter(
                explode("\n", $telo),
                static fn (string $radek) => str_starts_with($radek, '{"custom_id"'),
            )),
        );
        self::assertSame(['korektura-1', 'korektura-2'], array_column($radky, 'custom_id'), 'Jeden řádek na požadavek, konce řádků v textu escapované');
        self::assertSame('POST', $radky[0]['method']);
        self::assertSame('/v1/responses', $radky[0]['url']);
        $parametry = $radky[0]['body'];
        self::assertSame('jiny-model', $parametry['model']);
        self::assertSame('Pokyny pro korektora', $parametry['instructions']);
        self::assertSame("<gc-aktivita id=\"11\">\n📅 Termín</gc-aktivita>", $parametry['input']);
        self::assertSame('high', $parametry['reasoning']['effort']);
        self::assertGreaterThan(0, $parametry['max_output_tokens']);

        $zalozeni = $this->pozadavky[1];
        self::assertSame('POST', $zalozeni->getMethod());
        self::assertSame('https://api.openai.com/v1/batches', (string) $zalozeni->getUri());
        self::assertSame('Bearer token-testu', $zalozeni->getHeaderLine('Authorization'));
        self::assertSame('application/json', $zalozeni->getHeaderLine('Content-Type'));
        self::assertSame([
            'input_file_id'     => 'file-test',
            'endpoint'          => '/v1/responses',
            'completion_window' => '24h',
        ], json_decode((string) $zalozeni->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @test
     */
    public function bezModeluJdeVychozi(): void
    {
        $this->odpovedi[] = $this->json([
            'id' => 'file-test',
        ]);
        $this->odpovedi[] = $this->json($this->davka('validating'));

        $this->davkyAi()->odesli('Pokyny', [
            'korektura-1' => 'text',
        ]);

        self::assertStringContainsString('"model":"' . OpenAiDavkyAi::VYCHOZI_MODEL . '"', (string) $this->pozadavky[0]->getBody());
        self::assertSame(PoskytovatelAi::OPENAI, $this->davkyAi()->poskytovatel());
    }

    /**
     * @test
     */
    public function beziciDavkaNemaOdpovedi(): void
    {
        $stavy = ['validating', 'in_progress', 'finalizing', 'cancelling'];
        foreach ($stavy as $poradi => $stav) {
            $this->odpovedi = [$this->json($this->davka($stav))];

            self::assertNull($this->davkyAi()->dejOdpovedi('batch_test'), $stav);
            self::assertCount($poradi + 1, $this->pozadavky, "{$stav}: na soubory běžící dávky se neptá");
        }
        self::assertSame(
            array_fill(0, count($stavy), 'https://api.openai.com/v1/batches/batch_test'),
            array_map(static fn (RequestInterface $pozadavek) => (string) $pozadavek->getUri(), $this->pozadavky),
        );
    }

    /**
     * @test
     */
    public function skoncenaDavkaDaTextZVystupuNeboDuvodZChyb(): void
    {
        $this->odpovedi[] = $this->json($this->davka('completed', 'file-vystup', 'file-chyby'));
        $this->odpovedi[] = $this->jsonl([
            $this->vysledek('korektura-1', $this->zprava(['<gc-aktivita id="11">', '</gc-aktivita>'])),
            $this->vysledek('korektura-2', $this->zprava([], odmitnuti: true)),
            $this->vysledek('korektura-3', $this->zprava([], duvodNedokonceni: 'max_output_tokens')),
        ]);
        $this->odpovedi[] = $this->jsonl([
            [
                'id'        => 'batch_req_4',
                'custom_id' => 'korektura-4',
                'response'  => null,
                'error'     => [
                    'code'    => 'batch_expired',
                    'message' => 'This request could not be executed before the completion window expired.',
                ],
            ],
            [
                'id'        => 'batch_req_5',
                'custom_id' => 'korektura-5',
                'response'  => [
                    'status_code' => 500,
                    'request_id'  => 'req_test',
                    'body'        => [
                        'error' => [
                            'message' => 'The server had an error',
                            'type'    => 'server_error',
                        ],
                    ],
                ],
                'error' => null,
            ],
        ]);

        $odpovedi = $this->davkyAi()->dejOdpovedi('batch_test');

        self::assertNotNull($odpovedi);
        self::assertSame('<gc-aktivita id="11"></gc-aktivita>', $odpovedi['korektura-1']->text, 'Texty zprávy za sebou, bez přemýšlení');
        self::assertSame('AI korekturu odmítla', $odpovedi['korektura-2']->chyba);
        self::assertStringContainsString('max_output_tokens', (string) $odpovedi['korektura-3']->chyba);
        self::assertSame('AI nestihla odpovědět do 24 hodin', $odpovedi['korektura-4']->chyba);
        self::assertStringContainsString('The server had an error', (string) $odpovedi['korektura-5']->chyba);
        self::assertSame('https://api.openai.com/v1/files/file-vystup/content', (string) $this->pozadavky[1]->getUri());
        self::assertSame('https://api.openai.com/v1/files/file-chyby/content', (string) $this->pozadavky[2]->getUri());
        self::assertSame('Bearer token-testu', $this->pozadavky[2]->getHeaderLine('Authorization'));
    }

    /**
     * An expired batch has the answers that made it in the output file and the rest in the error file,
     * a batch where nothing made it has no output file at all.
     *
     * @test
     */
    public function propadlaDavkaBezVystupuDaJenChyby(): void
    {
        $this->odpovedi[] = $this->json($this->davka('expired', null, 'file-chyby'));
        $this->odpovedi[] = $this->jsonl([[
            'id'        => 'batch_req_1',
            'custom_id' => 'korektura-1',
            'response'  => null,
            'error'     => [
                'code'    => 'batch_expired',
                'message' => 'expired',
            ],
        ]]);

        $odpovedi = $this->davkyAi()->dejOdpovedi('batch_test');

        self::assertCount(2, $this->pozadavky);
        self::assertSame(['korektura-1'], array_keys((array) $odpovedi));
    }

    /**
     * JSONL is split at line ends only: 📅 ends with the byte 0x85, which `\R` without `/u` takes for a line end.
     *
     * @test
     */
    public function vysledekSeZnakyObsahujicimiBajt0x85SeNerozdeli(): void
    {
        $text = '<gc-aktivita id="11"><gc-popis>📅 Termín, 😅, 🍅 a Ålandy</gc-popis></gc-aktivita>';
        $this->odpovedi[] = $this->json($this->davka('completed', 'file-vystup'));
        $this->odpovedi[] = $this->jsonl([$this->vysledek('korektura-1', $this->zprava([$text]))]);

        self::assertSame($text, $this->davkyAi()->dejOdpovedi('batch_test')['korektura-1']->text ?? null);
    }

    /**
     * @test
     */
    public function odmitnutaDavkaJeZtracenaSDuvodem(): void
    {
        $davka = $this->davka('failed');
        $davka['errors'] = [
            'object' => 'list',
            'data'   => [[
                'code'    => 'invalid_request',
                'line'    => 2,
                'message' => 'Model not supported in batch',
                'param'   => 'body.model',
            ]],
        ];
        $this->odpovedi[] = $this->json($davka);

        try {
            $this->davkyAi()->dejOdpovedi('batch_test');
            self::fail('Čekána ChybaApiAi');
        } catch (ChybaApiAi $chybaApiAi) {
            self::assertTrue($chybaApiAi->jeDavkaZtracena(), 'Odmítnutá dávka nikdy neodpoví, texty jdou poslat znovu');
            self::assertFalse($chybaApiAi->jeChybaCelehoApi());
            self::assertStringContainsString('Model not supported in batch (řádek 2)', $chybaApiAi->getMessage());
        }
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
            'neznámý stav dávky' => [
                $this->json($this->davka('nevim')),
            ],
            'výsledek bez custom_id' => [
                $this->json($this->davka('completed', 'file-vystup')),
                new Response(200, [], '{"id":"batch_req_1"}' . "\n"),
            ],
            'hotová dávka bez souborů' => [
                $this->json($this->davka('completed')),
            ],
        ];
        foreach ($pripady as $pripad => $odpovedi) {
            $this->odpovedi = $odpovedi;
            try {
                $this->davkyAi()->dejOdpovedi('batch_test');
                self::fail("{$pripad}: čekána ChybaApiAi");
            } catch (ChybaApiAi $chybaApiAi) {
                self::assertFalse($chybaApiAi->jeDavkaZtracena(), $pripad);
                self::assertFalse($chybaApiAi->jeChybaCelehoApi(), "{$pripad}: chyba té dávky, ne celého API");
            }
        }

        $this->odpovedi = [
            $this->json([
                'object' => 'file',
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
            new Response(404, [], '{"error":{"message":"No batch found with id batch_test","type":"invalid_request_error"}}'),
            new Response(429, [], '{"error":{"message":"Rate limit reached","type":"requests"}}'),
            new ConnectException('Connection refused', new Request('GET', 'https://api.openai.com')),
        ];
        $chyby = [];
        for ($pokus = 1; $pokus <= 3; ++$pokus) {
            try {
                $this->davkyAi()->dejOdpovedi('batch_test');
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
            'x-request-id' => 'req_test',
        ], json_encode([
            'error' => [
                'message' => 'Incorrect API key provided',
                'type'    => 'invalid_request_error',
                'code'    => 'invalid_api_key',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(ChybaApiAi::class);
        $this->expectExceptionMessage('API AI vrátilo chybu 401: Incorrect API key provided (request-id req_test)');
        $this->davkyAi()->overSpojeni();
    }

    /**
     * @test
     */
    public function overeniSpojeniSePtaNaZvolenyModel(): void
    {
        $this->odpovedi[] = $this->json([
            'id'     => 'gpt-test',
            'object' => 'model',
        ]);
        $this->odpovedi[] = new Response(404, [], '{"error":{"message":"The model `gpt-preklep` does not exist","type":"invalid_request_error"}}');

        $this->davkyAi('gpt-test')->overSpojeni();

        self::assertSame('GET', $this->pozadavky[0]->getMethod());
        self::assertSame('https://api.openai.com/v1/models/gpt-test', (string) $this->pozadavky[0]->getUri());
        self::assertSame('Bearer token-testu', $this->pozadavky[0]->getHeaderLine('Authorization'));

        $this->expectException(ChybaApiAi::class);
        $this->expectExceptionMessage('Token platí, ale model „gpt-preklep“ API nezná.');
        $this->davkyAi('gpt-preklep')->overSpojeni();
    }

    /**
     * @test
     */
    public function zruseniJdeNaCancelDavky(): void
    {
        $this->odpovedi[] = $this->json($this->davka('cancelling'));

        $this->davkyAi()->zrus('batch_test');

        self::assertSame('POST', $this->pozadavky[0]->getMethod());
        self::assertSame('https://api.openai.com/v1/batches/batch_test/cancel', (string) $this->pozadavky[0]->getUri());
    }

    private function davkyAi(string $model = OpenAiDavkyAi::VYCHOZI_MODEL): OpenAiDavkyAi
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

        return new OpenAiDavkyAi('token-testu', $model, $transporter);
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
     * @param array<array<string, mixed>> $radky
     */
    private function jsonl(array $radky): ResponseInterface
    {
        return new Response(200, [
            'Content-Type' => 'application/octet-stream',
        ], implode("\n", array_map(
            static fn (array $radek) => json_encode($radek, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $radky,
        )) . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function davka(string $stav, ?string $vystup = null, ?string $chyby = null): array
    {
        return [
            'id'                => 'batch_test',
            'object'            => 'batch',
            'endpoint'          => '/v1/responses',
            'errors'            => null,
            'input_file_id'     => 'file-test',
            'completion_window' => '24h',
            'status'            => $stav,
            'output_file_id'    => $vystup,
            'error_file_id'     => $chyby,
            'created_at'        => 1795435200,
            'request_counts'    => [
                'total'     => 0,
                'completed' => 0,
                'failed'    => 0,
            ],
            'metadata' => null,
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
            'id'        => 'batch_req_' . $idPozadavku,
            'custom_id' => $idPozadavku,
            'response'  => [
                'status_code' => 200,
                'request_id'  => 'req_test',
                'body'        => $zprava,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string> $texty
     *
     * @return array<string, mixed>
     */
    private function zprava(array $texty, bool $odmitnuti = false, ?string $duvodNedokonceni = null): array
    {
        $obsah = array_map(static fn (string $text) => [
            'type'        => 'output_text',
            'text'        => $text,
            'annotations' => [],
        ], $texty);
        if ($odmitnuti) {
            $obsah[] = [
                'type'    => 'refusal',
                'refusal' => 'I cannot help with that.',
            ];
        }

        return [
            'id'     => 'resp_test',
            'object' => 'response',
            'status' => $duvodNedokonceni !== null
                ? 'incomplete'
                : 'completed',
            'incomplete_details' => $duvodNedokonceni !== null
                ? [
                    'reason' => $duvodNedokonceni,
                ]
                : null,
            'model'  => OpenAiDavkyAi::VYCHOZI_MODEL,
            'output' => [
                [
                    'id'      => 'rs_test',
                    'type'    => 'reasoning',
                    'summary' => [],
                ],
                ...($obsah !== []
                    ? [[
                        'id'      => 'msg_test',
                        'type'    => 'message',
                        'role'    => 'assistant',
                        'status'  => 'completed',
                        'content' => $obsah,
                    ]]
                    : []),
            ],
        ];
    }
}
