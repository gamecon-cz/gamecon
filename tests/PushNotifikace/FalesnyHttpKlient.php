<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Push služba v testech: žádný požadavek neopustí proces. Pamatuje si, co dostala, a na endpoint odpoví stavem,
 * který jí test určil (výchozí 201 jako skutečná služba, která zprávu převzala).
 */
final class FalesnyHttpKlient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    public array $pozadavky = [];

    /**
     * @var array<string, int|null> endpoint => HTTP stav, null = služba neodpoví
     */
    private array $odpovedi = [];

    public function odpovezNa(string $endpoint, ?int $httpKod): void
    {
        $this->odpovedi[$endpoint] = $httpKod;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->pozadavky[] = $request;
        $endpoint = (string) $request->getUri();
        if (array_key_exists($endpoint, $this->odpovedi) && $this->odpovedi[$endpoint] === null) {
            // jako skutečný klient: zpráva výjimky nese celou adresu
            throw new ConnectException('cURL error 6: Could not resolve host for ' . $endpoint, $request);
        }

        return new Response($this->odpovedi[$endpoint] ?? 201);
    }

    /**
     * @return list<string> endpointy, na které šel požadavek
     */
    public function getEndpointy(): array
    {
        return array_map(static fn (RequestInterface $pozadavek): string => (string) $pozadavek->getUri(), $this->pozadavky);
    }
}
