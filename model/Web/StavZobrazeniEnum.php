<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Where something shown on the web for a period (an announcement, a special theme) is at a given moment.
 */
enum StavZobrazeniEnum: string
{
    case ZOBRAZUJE_SE = 'zobrazuje-se';
    case NAPLANOVANO = 'naplanovano';
    case SKONCILO = 'skoncilo';

    public static function zObdobi(?\DateTimeInterface $od, ?\DateTimeInterface $do, \DateTimeInterface $kdy): self
    {
        if ($do !== null && $do <= $kdy) {
            return self::SKONCILO;
        }
        if ($od !== null && $od > $kdy) {
            return self::NAPLANOVANO;
        }

        return self::ZOBRAZUJE_SE;
    }

    public function getNazev(): string
    {
        return match ($this) {
            self::ZOBRAZUJE_SE => 'na webu',
            self::NAPLANOVANO  => 'naplánováno',
            self::SKONCILO     => 'skončilo',
        };
    }
}
