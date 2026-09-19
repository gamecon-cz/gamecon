<?php

declare(strict_types=1);

namespace Gamecon\Shop;

/**
 * Pořadí, ve kterém se účastníkovi nabízejí typy ubytování: koleje od nejlevnější, pak
 * spacák, pak hotely. Názvy se mezi ročníky mění („Dvojlůžák" vs. „Postel na 2L koleji"),
 * proto se typ rozpoznává podle předpony a stejná úroveň má víc zápisů.
 */
final class RazeniTypuUbytovani
{
    private const PORADI_TYPU = [
        'jednolůžák' => 10,
        'postel na "1l" koleji' => 10,
        'postel na 1l koleji' => 10,
        'dvoulůžák' => 20,
        'postel na 2l koleji' => 20,
        'dvojlůžák' => 30,
        'trojlůžák' => 40,
        'postel na 3l koleji' => 40,
        'spacák' => 50,
        'hotelový jednolůžák standard' => 60,
        'postel na 1l hotelu se snídaní' => 60,
        'hotelový dvoulůžák standard' => 70,
        'hotelový dvojlůžák standard' => 70,
        'postel na 2l hotelu se snídaní' => 70,
        'hotelový jednolůžák deluxe (buňka)' => 80,
        'postel na 1l hotelu deluxe se snídaní - dvojbuňka' => 80,
        'hotelový jednolůžák deluxe' => 90,
        'postel na 1l hotelu deluxe se snídaní' => 90,
        'hotelový dvoulůžák deluxe' => 100,
        'hotelový dvojlůžák deluxe' => 100,
        'postel na 2l hotelu deluxe se snídaní' => 100,
    ];

    /**
     * @param array<string, mixed> $typyPodleNazvu klíčem je název typu ubytování
     *
     * @return array<string, mixed> tytéž položky, seřazené
     */
    public function serad(array $typyPodleNazvu): array
    {
        uksort($typyPodleNazvu, $this->porovnej(...));

        return $typyPodleNazvu;
    }

    public function porovnej(string $prvniTyp, string $druhyTyp): int
    {
        $poradiPrvniho = $this->poradi($prvniTyp);
        $poradiDruheho = $this->poradi($druhyTyp);
        if ($poradiPrvniho !== $poradiDruheho) {
            return $poradiPrvniho <=> $poradiDruheho;
        }

        return strcmp($prvniTyp, $druhyTyp);
    }

    /**
     * @return int neznámý typ jde na konec
     */
    public function poradi(string $typ): int
    {
        $rozpoznanyTyp = $this->rozpoznanyTyp(mb_strtolower(trim($typ)));

        return $rozpoznanyTyp !== null
            ? self::PORADI_TYPU[$rozpoznanyTyp]
            : PHP_INT_MAX;
    }

    private function rozpoznanyTyp(string $normalizovanyTyp): ?string
    {
        foreach ($this->znameTypyOdNejdelsiho() as $znamyTyp) {
            if ($normalizovanyTyp === $znamyTyp) {
                return $znamyTyp;
            }
            if (!str_starts_with($normalizovanyTyp, $znamyTyp)) {
                continue;
            }

            // „Dvojlůžák deluxe" nesmí spadnout pod „dvojlůžák" — za předponou musí končit
            // slovo, jinak je to jiný typ.
            $znakZaTypem = mb_substr($normalizovanyTyp, mb_strlen($znamyTyp), 1);
            if ($znakZaTypem === ' ' || $znakZaTypem === '(') {
                return $znamyTyp;
            }
        }

        return null;
    }

    /**
     * Od nejdelšího, aby „hotelový jednolůžák deluxe" vyhrálo nad „hotelový jednolůžák".
     *
     * @return string[]
     */
    private function znameTypyOdNejdelsiho(): array
    {
        static $znameTypy = null;
        if ($znameTypy === null) {
            $znameTypy = array_keys(self::PORADI_TYPU);
            usort(
                $znameTypy,
                static fn(string $prvni, string $druhy): int => mb_strlen($druhy) <=> mb_strlen($prvni),
            );
        }

        return $znameTypy;
    }
}
