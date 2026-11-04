<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Požadavky prohlížeče na nahrávání po částech, viz @see NahravaniPoCastech a admin/files/nahravani-po-castech.js.
 * Modul s formulářem je obslouží na vlastní adrese s parametrem `nahravaniPoCastech`:
 * - `zahaj` (POST `velikost`, `nazev`) vrátí `id` a velikost části,
 * - `cast` (POST, v adrese `nahrani` (jeho `id`) a `index`, tělo je část souboru) vrátí, kolik bajtů je přijato,
 * - `zrus` (POST `id`) smaže rozpracované nahrávání.
 * Odpověď je JSON s `ok`; chyba, kterou má vidět člověk, je `ok: false` se `zprava` a kód 422.
 */
final class NahravaniPoCastechPozadavek
{
    public const PARAMETR = 'nahravaniPoCastech';
    /** Cizí web hlavičku bez povolení od nás poslat nemůže, takže ji nezná, a nezneužije přihlášeného správce */
    public const HLAVICKA = 'HTTP_X_GAMECON_NAHRAVANI';

    public function __construct(private readonly NahravaniPoCastech $nahravani)
    {
    }

    public static function jePozadavek(): bool
    {
        return isset($_GET[self::PARAMETR]);
    }

    /**
     * Pro začátek modulu s formulářem: požadavek skriptu pro nahrávání po částech obslouží a skončí. Skript čeká jen
     * odpověď, nic z administrace okolo ní, proto `exit` a ne `$BEZ_DEKORACE`.
     */
    public static function obslouzPokudJe(NahravaniPoCastech $nahravani): void
    {
        if (!self::jePozadavek()) {
            return;
        }
        (new self($nahravani))->odpovez();

        exit;
    }

    /**
     * Obslouží požadavek podle `$_GET`, `$_POST`, `$_SERVER` a těla požadavku a vypíše odpověď. Volající skončí `exit`.
     */
    public function odpovez(): void
    {
        $vstup = fopen('php://input', 'rb');
        $odpoved = $this->zpracuj(
            (string) ($_GET[self::PARAMETR] ?? ''),
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            isset($_SERVER[self::HLAVICKA]),
            $_GET,
            $_POST,
            $vstup === false ? fopen('php://memory', 'rb') : $vstup,
        );
        http_response_code($odpoved['kod']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($odpoved['telo'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param resource $telo
     * @return array{kod: int, telo: array<string, mixed>}
     */
    public function zpracuj(
        string $krok,
        string $metoda,
        bool $maHlavicku,
        array $get,
        array $post,
        $telo,
    ): array {
        if ($metoda !== 'POST' || !$maHlavicku) {
            return ['kod' => 400, 'telo' => ['ok' => false, 'zprava' => 'Neplatný požadavek.']];
        }
        try {
            $vysledek = match ($krok) {
                'zahaj' => $this->zahaj($post),
                'cast'  => $this->prijmiCast($get, $telo),
                'zrus'  => $this->zrus($post),
                default => throw new NahravaniObrazkuException('Neznámý krok nahrávání.'),
            };
        } catch (NahravaniObrazkuException $chyba) {
            return ['kod' => 422, 'telo' => ['ok' => false, 'zprava' => $chyba->getMessage()]];
        }

        return ['kod' => 200, 'telo' => ['ok' => true, ...$vysledek]];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{id: string, castBajtu: int}
     */
    private function zahaj(array $post): array
    {
        $velikost = $post['velikost'] ?? null;
        $id = $this->nahravani->zahaj(
            is_numeric($velikost) ? (int) $velikost : 0,
            is_string($post['nazev'] ?? null) ? $post['nazev'] : '',
        );

        return ['id' => $id, 'castBajtu' => $this->nahravani->getCastBajtu()];
    }

    /**
     * @param array<string, mixed> $get
     * @param resource $telo
     * @return array{prijato: int}
     */
    private function prijmiCast(array $get, $telo): array
    {
        $index = $get['index'] ?? null;
        if (!is_string($index) || !ctype_digit($index)) {
            throw new NahravaniObrazkuException('Neplatné pořadí části souboru.');
        }

        return [
            'prijato' => $this->nahravani->prijmiCast(is_string($get['nahrani'] ?? null) ? $get['nahrani'] : '', (int) $index, $telo),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{}
     */
    private function zrus(array $post): array
    {
        $this->nahravani->smaz(is_string($post['id'] ?? null) ? $post['id'] : '');

        return [];
    }
}
