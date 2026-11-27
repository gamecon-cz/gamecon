<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Výsledek odeslání jedné notifikace jednomu zařízení. Nenese endpoint ani klíče, takže ho jde ukázat i uložit.
 */
final readonly class VysledekOdeslani
{
    public function __construct(
        public int $idSubscription,
        public string $zarizeni,
        public string $pushSluzba,
        public StavOdeslani $stav,
        public ?int $httpKod,
        public string $duvod = '',
    ) {
    }

    /**
     * @return array{idSubscription: int, zarizeni: string, pushSluzba: string, stav: string, httpKod: int|null, duvod: string}
     */
    public function toArray(): array
    {
        return [
            'idSubscription' => $this->idSubscription,
            'zarizeni'       => $this->zarizeni,
            'pushSluzba'     => $this->pushSluzba,
            'stav'           => $this->stav->value,
            'httpKod'        => $this->httpKod,
            'duvod'          => $this->duvod,
        ];
    }

    /**
     * @param array<string, mixed> $data z {@see self::toArray()}
     */
    public static function zArray(array $data): ?self
    {
        $stav = StavOdeslani::tryFrom((string) ($data['stav'] ?? ''));
        if ($stav === null) {
            return null;
        }

        return new self(
            idSubscription: (int) ($data['idSubscription'] ?? 0),
            zarizeni: (string) ($data['zarizeni'] ?? ''),
            pushSluzba: (string) ($data['pushSluzba'] ?? ''),
            stav: $stav,
            httpKod: isset($data['httpKod'])
                ? (int) $data['httpKod']
                : null,
            duvod: (string) ($data['duvod'] ?? ''),
        );
    }
}
