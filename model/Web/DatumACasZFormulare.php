<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * A moment entered in <input type="datetime-local">, which sends it as 2027-07-17T14:30 (seconds only when the field has a step under a minute).
 */
class DatumACasZFormulare
{
    private const string FORMAT = 'Y-m-d\TH:i';
    private const string FORMAT_SE_SEKUNDAMI = 'Y-m-d\TH:i:s';

    /**
     * @return \DateTimeImmutable|null null for an empty field
     *
     * @throws \InvalidArgumentException when the value is not a moment
     */
    public static function zVstupu(?string $hodnota): ?\DateTimeImmutable
    {
        $hodnota = trim((string) $hodnota);
        if ($hodnota === '') {
            return null;
        }
        foreach ([self::FORMAT, self::FORMAT_SE_SEKUNDAMI] as $format) {
            $datum = \DateTimeImmutable::createFromFormat('!' . $format, $hodnota);
            // createFromFormat rolls 2027-02-30 over to March, the round trip rejects it
            if ($datum !== false && $datum->format($format) === $hodnota) {
                return $datum;
            }
        }

        throw new \InvalidArgumentException("'{$hodnota}' není platné datum a čas.");
    }

    public static function doVstupu(?\DateTimeInterface $datum): string
    {
        return $datum?->format(self::FORMAT) ?? '';
    }
}
