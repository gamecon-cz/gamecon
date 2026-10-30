<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

/**
 * The designer works on a draft, previews it on the web and publishes it, visitors see only the published colours.
 */
enum StavBarevWebuEnum: string
{
    case NAVRH = 'navrh';
    case ZVEREJNENO = 'zverejneno';
}
