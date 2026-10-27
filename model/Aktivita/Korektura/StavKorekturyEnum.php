<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

enum StavKorekturyEnum: string
{
    // exported in a package, waiting for the AI's answer
    case EXPORTOVANO = 'exportovano';
    // the AI's answer is imported, waiting for the proofreader
    case NAVRZENO = 'navrzeno';
    case PRIJATO = 'prijato';
    case ZAMITNUTO = 'zamitnuto';

    /**
     * @return array<string>
     */
    public static function otevrene(): array
    {
        return [self::EXPORTOVANO->value, self::NAVRZENO->value];
    }
}
